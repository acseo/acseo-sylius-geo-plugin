# Contributing

## Local Setup

```bash
make init
make database-init
make load-fixtures
```

Use the Makefile targets instead of running Composer, PHPUnit, PHPStan, or Behat directly. Commands run inside the Docker Compose `php` service.

## Quality Checks

Before opening a pull request, run the same checks as the CI:

```bash
make ci
```

`make ci` validates `composer.json`, lints the container, then runs `make tests` (PHPStan, ECS, PHPUnit, Behat). Each tool also has its own target: `make phpstan`, `make ecs`, `make phpunit`, `make behat`. Before a release, `make ci-matrix` replays the Sylius and Symfony combinations of the CI matrix locally.

## Standards

- Keep changes focused and covered by tests when behavior changes.
- Do not commit secrets, API keys, credentials, or local environment files.
- Keep the code self-explanatory: no code comments, except the PHPStan annotations that the native types cannot express.
- Keep classes, methods and templates small and single-purpose, and reuse existing code instead of duplicating it.
- All text shown to users or written for crawlers goes through the translation files; the source strings are English.
- Run `make rector`, `make phpmd`, `make deptrac`, `make duplication` and `make audit` next to `make ci`.

## Releases

Releases should follow Semantic Versioning:

- Patch for bug fixes and documentation corrections.
- Minor for backward-compatible features.
- Major for backward-incompatible changes.
