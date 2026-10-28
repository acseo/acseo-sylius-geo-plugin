# Local Development

The plugin follows the [Sylius PluginSkeleton](https://github.com/Sylius/PluginSkeleton) development setup: Docker Compose (`compose.yml` and a local, git-ignored `compose.override.yml` created from `compose.override.dist.yml`) and `make` as the entry point.

Prerequisite: Docker with the Compose plugin.

Install the dependencies and start the test application:

```bash
make init
make database-init
make load-fixtures
```

App: `http://localhost`. When a port is already used on your machine, choose other host ports:

```bash
HTTP_PORT=8081 MYSQL_PORT=3307 MAILHOG_PORT=8026 make up
```

Run every check of the CI (Composer validation, lint, coding standards, PHPStan, PHPUnit, Behat):

```bash
make ci
```

Before a release, replay the CI matrix locally. Each Sylius and Symfony combination of `.github/workflows/build.yaml` is installed in a copy under `var/ci-matrix/`, with its own database and log:

```bash
make ci-matrix                                # every combination of the workflow matrix
make ci-matrix SYLIUS=~2.0.0 SYMFONY=^6.4     # a single combination
make ci-matrix KEEP=1                         # keep the copies of successful combinations
```

For each combination, `etc/ci-matrix.sh` resolves the dependencies like the CI (`symfony/flex`, `extra.symfony.require`, `sylius/sylius` and `sylius/test-application` constraints, `composer update`), then runs `composer validate`, `lint:container`, PHPStan (Symfony 7.4 only, as in the CI), ECS, the database migrations, the test application asset build, PHPUnit and Behat. A second `make ci-matrix` stops while one is running (`var/ci-matrix/.lock`). Logs are written to `var/ci-matrix/<combination>.log`, and the copy of a failing combination is kept. It uses the PHP version of the local container and does not replay the other PHP versions, the MariaDB and PostgreSQL jobs, the `lowest` dependencies job or `@javascript` scenarios.

Useful commands:

```bash
make help
make phpunit
make behat
make phpstan
make ecs-fix
make geo-debug-product slug=product-slug locale=fr_FR
```

See [commands](commands.md) for all Symfony commands and Makefile targets.

External documentation:

- [Sylius Plugin Development Guide](https://docs.sylius.com/plugins-development-guide/how-to-create-a-plugin-for-sylius)

Behat uses Symfony BrowserKit in process. No Chrome, browser driver, running web server, Node.js, or compiled JavaScript assets are needed for `make ci`. Node.js and Encore remain available for manually browsing the Sylius test application.

See the [compatibility policy](compatibility.md) for the CI matrix and dependency resolution strategy.
