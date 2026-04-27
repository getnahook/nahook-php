# Contributing to nahook-php

Thanks for considering a contribution! A few important things to know first.

## Source of truth

This repository is a **subtree-split mirror** of the PHP SDK from our private monorepo `getnahook/nahook`. PRs filed directly here **cannot be merged** — the next subtree-push from the monorepo will force-overwrite this branch.

## What we welcome

- **Bug reports** — open a GitHub issue with: reproduction steps, SDK version, PHP version (`php --version`), OS, and your composer dependencies.
- **Feature requests** — open an issue describing the use case and the API surface you'd want.
- **Small code suggestions** — paste a snippet in an issue and describe intent; we'll port it into the monorepo and credit you in the resulting commit.
- **Substantial patches** — email `support@nahook.com` first; we'll either discuss read access to the monorepo or hand-port your change with credit.

## Local development

```bash
git clone https://github.com/getnahook/nahook-php
cd nahook-php
composer install
vendor/bin/phpunit              # 351 tests
vendor/bin/phpstan analyse src  # static analysis (default level)
```

`composer.json` declares `"php": ">=8.1"`. SDK supports PHP 8.1 through latest stable.

### Code style

- PSR-4 autoloading: `Nahook\\` → `src/`, `Nahook\\Tests\\` → `tests/`
- PHPUnit 10 for unit tests
- Guzzle 7 for HTTP

## License

By contributing, you agree your changes are released under the [MIT License](LICENSE).
