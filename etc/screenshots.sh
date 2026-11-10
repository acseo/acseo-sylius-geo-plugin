#!/usr/bin/env bash

set -eu

cd "$(dirname "$0")/.."

DC="${DOCKER_COMPOSE:-docker compose}"
PROJECT="$(basename "$PWD" | tr '[:upper:]' '[:lower:]')"
NETWORK="${PROJECT}_default"
BASE="http://nginx"
CHANNEL="${CHANNEL:-FASHION_WEB}"
LOCALE="${LOCALE:-en_US}"
OUT="docs/screenshots"
WORK="etc/build/screenshots"
CURL_IMAGE="curlimages/curl:8.10.1"
CHROME_IMAGE="zenika/alpine-chrome:latest"

mkdir -p "$WORK" "$OUT"

http() {
    docker run --rm --network "$NETWORK" "$CURL_IMAGE" "$@"
}

console() {
    ENV="${ENV:-dev}" DOCKER_USER="${DOCKER_USER:-$(id -u):$(id -g)}" $DC run --rm -T php vendor/bin/console "$@" 2>/dev/null | grep -v '^export HOME='
}

escape() {
    sed -e 's/&/\&amp;/g' -e 's/</\&lt;/g' -e 's/>/\&gt;/g'
}

render() {
    local name="$1" title="$2" command_line="$3"
    local body="$WORK/$name.txt"
    local lines
    lines=$(awk '{ n += int(length($0) / 185) + 1 } END { print n + 3 }' "$body")
    local height=$((lines * 21 + 100))

    {
        cat <<HTML
<!doctype html><html><head><meta charset="utf-8"><style>
html,body{margin:0;background:#1e1e1e}
.bar{height:38px;background:#2b2b2b;position:relative}
.dot{position:absolute;top:13px;width:12px;height:12px;border-radius:50%}
.title{position:absolute;left:300px;right:300px;top:5px;height:28px;line-height:30px;text-align:center;background:#3a3a3a;border-radius:8px;color:#d4d4d4;font:13px "DejaVu Sans Mono",monospace}
pre{margin:0;padding:18px 10px;color:#e6e6e6;font:14px/21px "DejaVu Sans Mono",monospace;white-space:pre-wrap;word-break:break-all}
</style></head><body>
<div class="bar"><span class="dot" style="left:16px;background:#ff5f57"></span><span class="dot" style="left:34px;background:#febc2e"></span><span class="dot" style="left:52px;background:#28c840"></span><div class="title">$title</div></div>
<pre>
HTML
        printf '$ %s\n' "$command_line" | escape
        escape < "$body"
        printf '</pre></body></html>\n'
    } > "$WORK/$name.html"

    docker run --rm --user "$(id -u):$(id -g)" --entrypoint chromium-browser -v "$PWD/$WORK:/work" "$CHROME_IMAGE" \
        --headless --no-sandbox --disable-gpu --hide-scrollbars \
        --force-device-scale-factor=2 --window-size=1460,"$height" \
        --screenshot="/work/$name.png" "file:///work/$name.html" > /dev/null 2>&1
    cp "$WORK/$name.png" "$OUT/$name.png"
}

http -s "$BASE/$LOCALE/llms.txt" | head -n 46 > "$WORK/llm-txt-endpoint.txt"
render llm-txt-endpoint "$BASE/$LOCALE/llms.txt" "curl -s $BASE/$LOCALE/llms.txt | head -n 46"

PRODUCT_SLUG="$(http -s "$BASE/$LOCALE/geo/sitemap.xml" | sed -n 's#.*<loc>.*/geo/products/\(.*\)\.md</loc>.*#\1#p' | head -n 1)"
http -s "$BASE/$LOCALE/geo/products/$PRODUCT_SLUG.md" | head -n 60 > "$WORK/product-markdown.txt"
render product-markdown "$BASE/$LOCALE/geo/products/$PRODUCT_SLUG.md" "curl -s $BASE/$LOCALE/geo/products/$PRODUCT_SLUG.md | head -n 60"

{
    http -sI -H 'Accept: text/html' "$BASE/$LOCALE/products/$PRODUCT_SLUG" | grep -iE '^(HTTP|content-type|cache-control|vary)'
    printf '\n$ curl -s -D - -H %s %s | grep -v -iE %s | head -n 24\n' "'Accept: text/markdown'" "$BASE/$LOCALE/products/$PRODUCT_SLUG" "'^(server|date|connection|transfer-encoding|x-[a-z-]+):'"
    http -s -D - -H 'Accept: text/markdown' "$BASE/$LOCALE/products/$PRODUCT_SLUG" | grep -v -iE '^(server|date|connection|transfer-encoding|x-[a-z-]+):' | head -n 24
} > "$WORK/accept-negotiation.txt"
render accept-negotiation "Terminal" "curl -sI -H 'Accept: text/html' $BASE/$LOCALE/products/$PRODUCT_SLUG | grep -iE '^(HTTP|content-type|cache-control|vary)'"

TAXON_PATH="$(http -s "$BASE/$LOCALE/geo/sitemap.xml" | sed -n "s#.*<loc>.*/$LOCALE/taxons/\(.*\)</loc>.*#\1#p" | head -n 1)"
http -s -H 'Accept: text/markdown' "$BASE/$LOCALE/taxons/$TAXON_PATH" > "$WORK/taxon-markdown.txt"
render taxon-markdown "Terminal" "curl -s -H 'Accept: text/markdown' $BASE/$LOCALE/taxons/$TAXON_PATH"

http -s "$BASE/$LOCALE/geo/sitemap.xml" | sed 's#><#>\n<#g' | head -n 24 > "$WORK/geo-sitemap.txt"
render geo-sitemap "$BASE/$LOCALE/geo/sitemap.xml" "curl -s $BASE/$LOCALE/geo/sitemap.xml | sed 's#><#>\\n<#g' | head -n 24"

console acseo_sylius_geo_plugin:audit --channel="$CHANNEL" --locale="$LOCALE" --no-ansi | sed -n '1,20p;/^Exportable taxons/,/^- product /p' > "$WORK/audit-command.txt"
render audit-command "Terminal" "bin/console acseo_sylius_geo_plugin:audit --channel=$CHANNEL --locale=$LOCALE | sed -n '1,20p;/^Exportable taxons/,/^- product /p'"
