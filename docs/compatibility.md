# Compatibility policy

The supported target is PHP 8.3 and 8.4 with the following Sylius/Symfony combinations:

| Sylius | Symfony | PHP |
|--------|---------|-----|
| 2.0.x | 6.4.x | 8.3, 8.4 |
| 2.1.x | 6.4.x or 7.4.x | 8.3, 8.4 |
| 2.2.x | 6.4.x or 7.4.x | 8.3, 8.4 |
| 2.3.x | 6.4.x or 7.4.x | 8.3, 8.4 |

Symfony 8, which Sylius 2.3 also allows, is not supported by this plugin.

Support for a release is validated by a successful CI run of this matrix. Composer constraints alone are not evidence that a combination has passed. Other PHP and Sylius minor versions require an explicit policy and CI update before support is claimed. All installations require the XMLWriter extension in addition to Sylius prerequisites.

## Dependency resolution

CI resolves dependencies afresh for each combination, pins the test application to the corresponding Sylius minor, and runs Composer validation, YAML/container lint, ECS, PHPStan, PHPUnit (including integration tests), and Behat. The development lockfile is intentionally not committed.

The default jobs select the highest compatible stable dependencies. A separate PHP 8.3 / Sylius 2.0 / Symfony 6.4 job uses `composer update --prefer-lowest --with "symfony/flex:^2.4"` and runs the same checks. This validates the oldest resolvable dependency set permitted by Composer, including upstream constraints and the security advisory rules of the CI Composer version.

The Sylius test application currently uses alpha releases, so the alpha stability allowance is restricted to that development package. Production Sylius dependencies remain stable. On the latest stack, additional jobs exercise MySQL 8.0, MariaDB 10.11/11.4, and PostgreSQL 15/16/17 alongside the default MySQL 8.4.

## Runtime and development tools

Production requirements are PHP, XMLWriter, Sylius, and Symfony FrameworkBundle. FrameworkBundle makes the supported Symfony branches explicit; the plugin adds no separate cache backend or Elasticsearch dependency.

The plugin uses HTTP cache headers and ETags. It does not implement an application cache and does not add a direct `symfony/cache` requirement. Sylius/Symfony may themselves install that component transitively.

Behat 3.17 or higher is required for development: Behat 3.16 does not apply the Sylius price transformations, which breaks the Behat scenarios on the lowest dependency set.

Behat scenarios run through Symfony BrowserKit without JavaScript, Chrome, browser drivers, or a web server. Encore remains a development dependency for the Sylius test application; its frontend build is optional for HTTP checks and required only when browsing the full application locally. The shared Sylius Behat services still require their page-object and HTTP helper packages.

Development constraints explicitly require ECS 13.1+ for the `ECSConfig` configuration API and Payum Core 1.7.7+ for PSR Log 3 compatibility in the test application. Both packages are already part of the development dependency graph; these bounds prevent unusable lowest-version selections.

The lowest job also constrains the transitive Symfony Flex plugin to 2.4+ for compatibility with Composer 2. This temporary tool constraint does not enable recipes in the plugin repository.

The test application requires the API Platform 4.x component family and the Ramsey UUID bridge explicitly: older upstream constraints permit 3.4 components with Symfony integration 4.0, whose service constructors are incompatible, and omit the UUID bridge. These requirements apply only to development.

Sylius Test Application 2.0 starts at alpha.7, which supports the `SYLIUS_TEST_APP_*` configuration variables used by this repository. Earlier alphas do not load the plugin test configuration.
