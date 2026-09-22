# Contributing

## Local Setup

```bash
make init
make database-init
make load-fixtures
make ci
```

Use the Makefile targets (see `make help`) instead of running Composer, PHPUnit, PHPStan, or Behat directly. Commands run inside the Docker Compose `php` service.

## Quality Checks

Before opening a pull request, run:

```bash
make ci
```

## Standards

- Keep changes focused and covered by tests when behavior changes.
- Do not commit secrets, API keys, credentials, or local environment files.
- Write code comments in English and only when they clarify non-obvious logic.

## Releases

Releases should follow Semantic Versioning:

- Patch for bug fixes and documentation corrections.
- Minor for backward-compatible features.
- Major for backward-incompatible changes.
