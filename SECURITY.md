# Security policy

## Supported versions

Security fixes are released for the latest minor version only.

## Reporting a vulnerability

Please do not open a public issue, pull request or discussion for a security problem.

Report it privately through GitHub: open the **Security** tab of the repository and choose **Report a vulnerability** ([direct link](https://github.com/acseo/SyliusGeoPlugin/security/advisories/new)).

Include, when you can:

- the plugin, Sylius, Symfony and PHP versions;
- the page, command or endpoint involved;
- the steps to reproduce, and the impact you observed.

ACSEO will acknowledge your report, keep you informed of the progress and credit you in the release notes if you wish.

## Scope

This plugin runs inside a Sylius shop. The most sensitive areas are access control, CSRF protection, input validation, and the handling of secrets and personal data.

Vulnerabilities in Sylius, Symfony or other dependencies should be reported to their own maintainers.
