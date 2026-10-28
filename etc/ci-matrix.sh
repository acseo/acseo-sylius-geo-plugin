#!/usr/bin/env bash

set -uo pipefail

cd "$(dirname "$0")/.."

DC="${DOCKER_COMPOSE:-docker compose}"
ROOT="var/ci-matrix"
WORKFLOW=".github/workflows/build.yaml"
FILTER_SYLIUS="${SYLIUS:-}"
FILTER_SYMFONY="${SYMFONY:-}"

[ -d vendor ] || { echo "vendor/ is missing: run make init first." >&2; exit 1; }
[ -f "$WORKFLOW" ] || { echo "$WORKFLOW is missing." >&2; exit 1; }
command -v rsync >/dev/null || { echo "rsync is required." >&2; exit 1; }

dc_run() {
    ENV="${ENV:-dev}" DOCKER_USER="${DOCKER_USER:-$(id -u):$(id -g)}" $DC run --rm -T "$@" < /dev/null
}

remove_copy() {
    [ -e "$1" ] || return 0
    dc_run --user root php rm -rf "/srv/sylius/$1" >/dev/null 2>&1
}

COMBOS=$(dc_run php php -r '
require "vendor/autoload.php";
$matrix = Symfony\Component\Yaml\Yaml::parseFile($argv[1])["jobs"]["tests"]["strategy"]["matrix"];
$combos = [];
foreach ($matrix["sylius"] as $sylius) {
    foreach ($matrix["symfony"] as $symfony) {
        foreach ($matrix["exclude"] ?? [] as $exclude) {
            if (isset($exclude["php"]) || isset($exclude["database"]) || isset($exclude["dependencies"])) {
                continue;
            }
            if (($exclude["sylius"] ?? $sylius) === $sylius && ($exclude["symfony"] ?? $symfony) === $symfony) {
                continue 2;
            }
        }
        $combos[$sylius . " " . $symfony] = true;
    }
}
foreach ($matrix["include"] ?? [] as $include) {
    $combos[$include["sylius"] . " " . $include["symfony"]] = true;
}
echo implode("\n", array_keys($combos)), "\n";
' "$WORKFLOW" 2>/dev/null | grep -E '^[~^0-9]')

[ -n "$COMBOS" ] || { echo "No combination read from $WORKFLOW." >&2; exit 1; }

mkdir -p "$ROOT/.composer"
if ! mkdir "$ROOT/.lock" 2>/dev/null; then
    echo "Another make ci-matrix is running ($ROOT/.lock exists). Remove it if no run is in progress." >&2
    exit 1
fi
trap 'rmdir "$ROOT/.lock" 2>/dev/null' EXIT
RESULTS=()
FAILED=0
INDEX=0

while read -r SY SF; do
    [ -z "$FILTER_SYLIUS" ] || [ "$SY" = "$FILTER_SYLIUS" ] || continue
    [ -z "$FILTER_SYMFONY" ] || [ "$SF" = "$FILTER_SYMFONY" ] || continue
    INDEX=$((INDEX + 1))

    SLUG="sylius-$(printf '%s' "$SY" | tr -cd '0-9.')-symfony-$(printf '%s' "$SF" | tr -cd '0-9.')"
    DIR="$ROOT/$SLUG"
    LOG="$ROOT/$SLUG.log"
    DB="ci_matrix_$(printf '%s' "$SLUG" | tr -c 'a-z0-9\n' '_')"
    NO_SECURITY_BLOCKING=0
    [ "$SY" = "~2.0.0" ] && NO_SECURITY_BLOCKING=1
    remove_copy "$DIR"
    rsync -a --exclude=/vendor/ --exclude=/var/ --exclude=/.git \
        --exclude=/composer.lock --exclude=/compose.override.yml --exclude=/etc/build/ ./ "$DIR/"
    : > "$LOG"

    echo "==> Sylius $SY / Symfony $SF ($DIR)"

    node_step() {
        local name="$1"; shift
        printf '    %-16s' "$name"
        {
            echo "### $name"
            dc_run -w "/srv/sylius/$DIR/vendor/sylius/test-application" nodejs "$*"
        } >> "$LOG" 2>&1
        local status=$?
        if [ $status -eq 0 ]; then echo "OK"; else echo "KO (see $LOG)"; fi
        return $status
    }

    step() {
        local name="$1" user="$2"; shift 2
        printf '    %-16s' "$name"
        {
            echo "### $name"
            dc_run ${user:+--user "$user"} -w "/srv/sylius/$DIR" \
                -e XDEBUG_MODE=off \
                -e APP_ENV=test \
                -e COMPOSER_HOME="/srv/sylius/$ROOT/.composer" \
                -e COMPOSER_ROOT_VERSION=dev-main \
                -e COMPOSER_NO_SECURITY_BLOCKING="$NO_SECURITY_BLOCKING" \
                -e APP_CACHE_DIR="/srv/sylius/$DIR/var/cache" \
                -e APP_LOG_DIR="/srv/sylius/$DIR/var/log" \
                -e DATABASE_URL="mysql://root@mysql/${DB}_%kernel.environment%" \
                php sh -c "$*"
        } >> "$LOG" 2>&1
        local status=$?
        if [ $status -eq 0 ]; then echo "OK"; else echo "KO (see $LOG)"; fi
        return $status
    }

    STATUS="OK"
    FAILED_STEP=""
    run_step() {
        [ "$STATUS" = "OK" ] || return 0
        if ! step "$@"; then STATUS="KO"; FAILED_STEP="$1"; fi
    }

    run_node_step() {
        [ "$STATUS" = "OK" ] || return 0
        if ! node_step "$@"; then STATUS="KO"; FAILED_STEP="$1"; fi
    }

    run_step dependencies "" "composer global config --no-plugins allow-plugins.symfony/flex true \
        && composer global require --no-progress --no-scripts --no-plugins --no-interaction 'symfony/flex:^2.4' \
        && composer config extra.symfony.require '$SF' \
        && composer require 'sylius/sylius:$SY' --no-update --no-scripts --no-interaction \
        && composer require --dev 'sylius/test-application:$SY@alpha >=2.0.0-alpha.7' --no-update --no-scripts --no-interaction \
        && composer update --prefer-stable --no-install --no-scripts --no-interaction \
        && { composer install --no-interaction --no-progress \
            || composer install --no-interaction --no-progress; }"
    run_step validate "" "composer validate --ansi --strict --no-check-lock"
    run_step lint-container "" "vendor/bin/console lint:container"
    if [ "$SF" = "^7.4" ]; then
        run_step phpstan "" "vendor/bin/phpstan analyse -c phpstan.neon --no-progress"
    fi
    run_step ecs "" "vendor/bin/ecs check --no-progress-bar"
    run_step database "" "vendor/bin/console doctrine:database:drop --force --if-exists \
        && vendor/bin/console doctrine:database:create \
        && vendor/bin/console doctrine:migrations:migrate -n"
    run_node_step assets "yarn install --non-interactive && yarn build"
    run_step phpunit "" "vendor/bin/phpunit"
    if find "$DIR/features" -name '*.feature' 2>/dev/null | grep -q .; then
        run_step behat root "php -d memory_limit=1G vendor/bin/behat --strict --no-interaction -f progress --tags='~@javascript'"
    fi

    step drop-database "" "vendor/bin/console doctrine:database:drop --force --if-exists" >/dev/null || true

    if [ "$STATUS" = "OK" ]; then
        RESULTS+=("OK   Sylius $SY / Symfony $SF")
        [ -n "${KEEP:-}" ] || remove_copy "$DIR"
    else
        RESULTS+=("KO   Sylius $SY / Symfony $SF : $FAILED_STEP (log: $LOG, copy kept in $DIR)")
        FAILED=1
    fi
done <<< "$COMBOS"

[ "$INDEX" -gt 0 ] || { echo "No combination matches SYLIUS='$FILTER_SYLIUS' SYMFONY='$FILTER_SYMFONY'." >&2; echo "Available:" >&2; echo "$COMBOS" >&2; exit 1; }

echo
echo "=== CI matrix ==="
printf '%s\n' "${RESULTS[@]}"
echo "Not replayed: other PHP versions, MariaDB/PostgreSQL, @javascript scenarios (Chrome)."
exit $FAILED
