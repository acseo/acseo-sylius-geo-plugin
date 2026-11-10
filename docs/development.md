# Local Development

The plugin follows the [Sylius PluginSkeleton](https://github.com/Sylius/PluginSkeleton) development setup: Docker Compose (`compose.yml` and a local, git-ignored `compose.override.yml` created from `compose.override.dist.yml`) and `make` as the entry point.

Prerequisite: Docker with the Compose plugin.

Install the dependencies and start the test application:

```bash
make init
make database-init
make load-fixtures
```

App: `http://localhost:8084`. To avoid clashes between the ACSEO plugins, this plugin publishes ports 8084 (nginx), 3310 (MySQL) and 8029 (Mailhog) by default. When another stack already uses them, choose other host ports:

```bash
HTTP_PORT=8090 MYSQL_PORT=3320 MAILHOG_PORT=8040 make up
```

The app is then served on `http://localhost:8090`. `make phpstan`, `make ecs` and the first two steps of `make ci` start the `php` service alone and need no free port.

No `.env` file is required: the test application variables (`SYLIUS_TEST_APP_*`, `BEHAT_BASE_URL`) are declared in `compose.override.dist.yml`.

## Quality checks

```bash
make tests
make ci
```

`make tests` runs PHPStan (level max, strict rules, banned code), ECS, PHPUnit and Behat; `make ci` adds `composer validate --strict`, the YAML and Twig lint and `lint:container`, like the GitHub Actions workflow.

Complementary checks, each wired in the Makefile:

| Target | Tool | Purpose |
|--------|------|---------|
| `make ecs-fix` | ECS | Fix the coding standard |
| `make rector` | Rector (dry run) | Keep the code modern |
| `make phpmd` | PHPMD (`phpmd.xml`) | Size and complexity |
| `make deptrac` | Deptrac (`deptrac.yaml`) | Allowed dependencies between the folders of `src/` |
| `make duplication` | jscpd (`.jscpd.json`) | Duplicated code |
| `make eslint`, `make stylelint` | ESLint, Stylelint | Lint the JavaScript and SCSS assets |
| `make audit` | `composer audit` | Known vulnerabilities of the dependencies |
| `make composer-update` | Composer | Update the dependencies |
| `make screenshots` | headless Chrome (Docker) | Regenerate `docs/screenshots` from the running test application (load the fixtures first) |

The plugin only ships two JavaScript entrypoints and the Twig templates that load them, and has no `package.json`, so there is no `npm audit`.

## Tests

- `tests/Unit` holds the unit tests (no kernel, no database).
- `tests/Integration` boots the Sylius Test Application kernel to check the container wiring and the translation catalogs.
- `features/shop` holds the Behat scenarios; the suite is declared in `tests/Behat/Resources/suites.yml`. They run through Symfony BrowserKit, so no browser, web server or compiled asset is needed.
- `tests/TestApplication/config/config.yaml` sets the exclusion rules used by the scenarios.

## CI matrix

Before a release, replay the CI matrix locally. Each Sylius and Symfony combination of `.github/workflows/build.yaml` is installed in a copy under `var/ci-matrix/`, with its own database and log:

```bash
make ci-matrix
make ci-matrix KEEP=1
```

`KEEP=1` keeps the copies of passing combinations. Other PHP versions, MariaDB, PostgreSQL and `@javascript` scenarios are not replayed.

As in the CI, PHPStan runs only on the highest Symfony version of the matrix: older Symfony versions lack the generic types of the configuration builder that the maximum level relies on. The tests run on every combination.

`tests/TestApplication/package.json` supplies frontend dependencies needed by the test application across the configured matrix. Review these overrides whenever the skeleton or compatibility matrix changes.

The repository has no `bin/console`; the Sylius test application exposes the console as `vendor/bin/console` inside the `php` Docker Compose service. Use the Makefile targets from the host so commands run in the expected Docker context.

## Without Docker

The [Sylius Test Application](https://docs.sylius.com/plugins-development-guide/test-application) also runs with a local PHP, a database and the Symfony CLI. Export the variables that `compose.override.dist.yml` declares, adapting `DATABASE_URL` to your database:

```bash
export DATABASE_URL="mysql://root@127.0.0.1/sylius_%kernel.environment%"
export SYLIUS_TEST_APP_BUNDLES_PATH="tests/TestApplication/config/bundles.php"
export SYLIUS_TEST_APP_CONFIGS_TO_IMPORT="@ACSEOSyliusGeoPlugin/tests/TestApplication/config/config.yaml"
export SYLIUS_TEST_APP_ROUTES_TO_IMPORT="@ACSEOSyliusGeoPlugin/tests/TestApplication/config/routes.yaml"
composer install
composer test-app-init
symfony serve -d
```

The CI (`.github/workflows/build.yaml`) declares the same variables for the GitHub Actions jobs.

External documentation:

- [Sylius Plugin Development Guide](https://docs.sylius.com/plugins-development-guide/how-to-create-a-plugin-for-sylius)
