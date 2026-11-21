# Contributing to Gregius Data

## Development setup

```bash
composer install
```

This installs the dev dependencies: PHPUnit (with Brain Monkey, Mockery, and the WordPress PHPUnit polyfills) and PHP_CodeSniffer with the WordPress Coding Standards.

## Running the tests

```bash
composer test       # unit + integration tests — no database needed
composer test:wp    # WordPress integration tests — needs a reachable MySQL
```

`composer test:wp` uses the canonical `bin/install-wp-tests.sh` bootstrap and requires a MySQL database that already exists. Configure the connection with:

- `TEST_DB_NAME` (default `wp_tests`)
- `TEST_DB_USER` (required)
- `TEST_DB_PASSWORD` (required)
- `TEST_DB_HOST` (default `127.0.0.1`)

## Coding standards

Code follows the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/), enforced with PHP_CodeSniffer:

```bash
composer lint       # check
composer lint:fix   # auto-fix what can be fixed
```

## Merge criteria

A pull request can be merged once it:

- is a worthwhile change to the codebase;
- passes CI (unit + integration, and WordPress integration);
- adds or updates tests where behavior changed;
- includes a changelog entry where the change is user-facing;
- is reviewed by someone other than the author;
- is rebased on the latest `main`.
