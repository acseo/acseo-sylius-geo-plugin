# Local Development

The plugin follows the [Sylius PluginSkeleton](https://github.com/Sylius/PluginSkeleton) development setup: Docker Compose (`compose.yml` and a local, git-ignored `compose.override.yml` created from `compose.override.dist.yml`) and `make` as the entry point.

Prerequisite: Docker with the Compose plugin.

Install the dependencies and start the test application:

```bash
make init
make database-init
make load-fixtures
```

App: `http://localhost`. Override the default Docker ports in a local `compose.override.yml` file if a port is already used on your machine.

Run every check of the CI (Composer validation, lint, coding standards, PHPStan, PHPUnit, Behat):

```bash
make ci
```

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

`make behat` starts a headless Chrome (`chrome` service of `compose.override.dist.yml`) that Behat reaches on `127.0.0.1:9222`. If your `compose.override.yml` predates this service, refresh it with `cp compose.override.dist.yml compose.override.yml`.
