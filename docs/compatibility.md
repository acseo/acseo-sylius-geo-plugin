# Compatibility policy

The plugin requires PHP 8.3 or higher, the XMLWriter extension, and one of these Sylius and Symfony combinations:

| Sylius | Symfony |
|--------|---------|
| 2.0.x | 6.4.x |
| 2.1.x | 6.4.x or 7.4.x |
| 2.2.x | 6.4.x or 7.4.x |
| 2.3.x | 6.4.x or 7.4.x |

These combinations are the ones declared in `composer.json` and in the matrix of `.github/workflows/build.yaml`. Composer constraints alone are not evidence that a combination passes: support for a release is validated by a successful run of that CI matrix. Symfony 8, which Sylius 2.3 also allows, is not supported. The CI matrix runs PHP 8.3 only; the `^8.3` constraint also allows newer PHP versions, which are not exercised by the CI.

## Runtime requirements

Production requirements are PHP, XMLWriter, Sylius, `psr/clock`, `symfony/clock` and Symfony FrameworkBundle. The plugin adds no cache backend, no Elasticsearch dependency, no database table and no front-end asset. It relies on HTTP cache headers and ETags only.

## Development

The Sylius Test Application and the development tools (PHPStan at the maximum level, ECS, PHPUnit, Behat, Rector, PHPMD, Deptrac) are declared in `require-dev`. Behat scenarios run through Symfony BrowserKit without JavaScript, Chrome or a web server. See [development](development.md).
