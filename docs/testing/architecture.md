# Architecture Description: Testing Subsystem

**Standard:** ISO/IEC/IEEE 42010:2022  
**Component:** Testing  
**Version:** 1.0.0  
**Date:** 2026-10-17  
**Upstream Specification:** [Software Requirements Specification](srs.md)

---

## 1. Architecture Overview

### 1.1 Purpose and Scope

This architecture describes how the Gregius Data plugin implements automated test coverage across three tiers: unit, integration, and full WordPress integration. It covers the test runner, directory structure, bootstrap lifecycle, mocking strategy, and CI/CD integration.

**Scope:**
- Test directory structure and tier classification
- Bootstrap lifecycle for all three tiers
- Mocking strategy (Brain Monkey, Mockery, Reflection)
- Test runner design (`bin/test`)
- PHPUnit configuration and wp-phpunit integration
- CI/CD orchestration model

**Explicitly Excluded:**
- Individual test case design
- Test data fixtures
- External coverage reporting services
- WordPress core test framework internals

### 1.2 Stakeholders and Concerns

| Stakeholder | Concern | Priority |
|---|---|---|
| Plugin Contributors | Clear test writing patterns and low-friction local execution | High |
| CI/CD Integrators | One-command test execution, environment-agnostic design | High |
| Code Reviewers | High-confidence regression prevention, all tests green | High |
| Plugin Release Manager | Tests excluded from production zip, README references | Medium |

---

## 2. Context View (AV-01)

### 2.1 Context

```
                         Developer / CI Runner
                               |
                        ./bin/test [--wp]
                               |
          +--------------------+--------------------+
          |                    |                    |
    Tier 1 (Unit)      Tier 2 (Integration)   Tier 3 (WpIntegration)
          |                    |                    |
    PHPUnit alone       PHPUnit + Brain        PHPUnit + real
    No WordPress         Monkey + Mockery       WordPress + MySQL
          |                    |                    |
   tests/Unit/         tests/Integration/    tests/WpIntegration/
```

```
Tier 3 expanded:
    ./bin/test --wp
          |
   bin/install-wp-tests.sh (WordPress core → WP_CORE_DIR, wp-phpunit → WP_TESTS_DIR)
          |
   +-------------+------------------+
   |                                |
  MySQL test DB                  wp-tests-config.php (generated)
  (must already exist)                |
                                 wp-phpunit bootstrap
                                      |
                                 WordPress boots
                                      |
                                 Gregius_Data_Activator::activate()
                                      |
                                 PHPUnit runs WpIntegration suite
```

### 2.2 Context Rationale

Tier 1 and Tier 2 run locally in PHP without WordPress. They provide fast feedback during development. Tier 3 requires a reachable MySQL database and provisions a real WordPress + wp-phpunit stack via `bin/install-wp-tests.sh`, validating activation, REST routes, database operations, and full plugin lifecycle.

The `bin/test` script abstracts all three tiers behind two commands: `./bin/test` for tiers 1+2 and `./bin/test --wp` for tier 3. Test counts are tracked in the [SRS](srs.md) §4 coverage matrix.

---

## 3. Functional View (AV-02)

### 3.1 Bootstrap Flow

```
phpunit.xml.dist loads:
  └── tests/bootstrap.php
        ├── Composer autoload
        ├── helpers.php (tier 1-2 only, WP_Error stub conflicts with real WP)
        ├── $wpdb global stub (tier 1-2 only)
        └── IF WP_TESTS_RUN=1 (or WP_TESTS_CONFIG_FILE_PATH set):
              ├── Find wp-tests-config.php (WP_TESTS_DIR, /var/www/html, or tests/)
              ├── Load <wp-phpunit>/includes/functions.php
              ├── tests_add_filter('muplugins_loaded', load plugin)
              ├── tests_add_filter('setup_theme', activate plugin)
              └── Load <wp-phpunit>/includes/bootstrap.php (boots WordPress)
```

### 3.2 `bin/test` Modes

| Mode | Command | Tiers | Runtime | MySQL |
|---|---|---|---|---|
| Local | `./bin/test` | Unit + Integration | <1s | Not required |
| Full stack | `./bin/test --wp` | WpIntegration | ~5s | Required |

`--wp` mode:
1. Requires a reachable MySQL test database (default name `wp_tests`; the runner does not create it)
2. Runs `bin/install-wp-tests.sh` to download WordPress core into `WP_CORE_DIR` (default `/tmp/wordpress`) and the wp-phpunit test suite (`includes/` + `data/`) into `WP_TESTS_DIR` (default `/tmp/wordpress-tests-lib`), then generates `wp-tests-config.php` (ABSPATH pointed at `WP_CORE_DIR`)
3. Runs `WP_TESTS_RUN=1 vendor/bin/phpunit --configuration phpunit-wp.xml.dist`

Configuration is via environment variables: `TEST_DB_NAME` (default `wp_tests`), `TEST_DB_USER` and `TEST_DB_PASSWORD` (both required), `TEST_DB_HOST` (default `127.0.0.1`), and `WP_VERSION` (default `7.1.5`). See the `./bin/test` header for the full list.

---

## 4. Information View (AV-03)

### 4.1 Directory Structure

```
gregius-data/
├── bin/
│   ├── test                    # Test runner script
│   └── install-wp-tests.sh     # WordPress + wp-phpunit provisioning (wp-cli script)
├── tests/
│   ├── bootstrap.php           # Conditional bootstrap (all tiers)
│   ├── helpers.php             # Shared stubs (tier 1-2 only)
│   ├── wp-phpunit/             # Optional in-tree checkout (gitignored)
│   ├── Unit/                   # Tier 1 (see srs.md §4.1)
│   ├── Integration/            # Tier 2 (see srs.md §4.2)
│   └── WpIntegration/          # Tier 3 (see srs.md §4.3)
├── phpunit.xml.dist            # Default: unit + integration suites
├── phpunit-wp.xml.dist         # WP tests: WpIntegration suite
├── composer.json               # require-dev: phpunit ^9.6, brain/monkey, mockery, polyfills
└── vendor/                     # Composer dev dependencies (gitignored)
```

### 4.2 Class Inheritance Tree

```
PHPUnit\Framework\TestCase
├── (Tier 1 & 2 tests)     # Test_Gregius_Data_* extends this directly
│   └── Mocking via Brain Monkey + Mockery
│
└── ... (through polyfills) ...
     └── WP_UnitTestCase           # Tier 3 tests with real WordPress
          └── WP_Test_REST_TestCase  # Tier 3 REST tests
```

### 4.3 Test File Naming Convention

All test files follow `Test_Gregius_Data_{ClassName}.php` in the appropriate tier directory. PHPUnit 9.6 discovers tests via `suffix=".php"` in the suite configuration, so class names mirror filenames.

---

## 5. Development View (AV-04)

### 5.1 Contributor Setup

```bash
# 1. Install PHP dependencies
composer install --dev

# 2. Provision wp-phpunit + WordPress (required for Tier 3)
#    Handled automatically by `./bin/test --wp` via bin/install-wp-tests.sh;
#    requires a reachable MySQL test database (see bin/test header).
```

### 5.2 Local Execution

```bash
./bin/test           # Unit + Integration (no MySQL)
./bin/test --wp      # WpIntegration only (phpunit-wp.xml.dist; MySQL required)
```

### 5.3 CI/CD Integration

The CI workflow (`.github/workflows/ci.yml`) runs three jobs: `lint` (phpcs), `unit-integration` (`./bin/test`), and `wp-integration` (`./bin/test --wp` with a `mysql:8.0` service container). The `wp-integration` job sets `TEST_DB_NAME=wp_tests`, `TEST_DB_USER=root`, `TEST_DB_PASSWORD=root`, `TEST_DB_HOST=127.0.0.1`, and `WP_VERSION=7.1.5` against the MySQL service.

---

## 6. Deployment View (AV-05)

Tests are excluded from the production plugin zip via `bin/plugin-zip.js` exclusions: `tests/`, `phpunit.xml`, `phpunit.xml.dist`, `phpunit-wp.xml.dist`, `phpcs.xml.dist`, `.phpunit.result.cache`, `vendor/`, `composer.json`, `composer.lock`.

The production zip contains only runtime plugin files. Testing infrastructure lives in the git repository only.

---

## 7. Architectural Decisions

### AD-01: ReflectionClass::newInstanceWithoutConstructor for coupled constructors


**Decision:** Tests create instances via `ReflectionClass::newInstanceWithoutConstructor()` and inject mock dependencies through private property reflection.

**Alternatives considered:**
1. Refactor constructors to accept dependencies (high risk, broad scope)
2. Stub all WP functions called in constructors (fragile and test-order-dependent)

**Rationale:** Reflection avoids modifying production code while still enabling isolated testing. The constructors remain unchanged for now; a future refactor to dependency injection would simplify both the codebase and the tests.

### AD-02: Brain Monkey for WP function mocking (Tier 1-2)

**Decision:** Use Brain Monkey's `Functions\when()` for WP function stubs (`get_current_blog_id`, `__`, `plugin_dir_path`, `maybe_unserialize`, etc.) and Mockery for class mocking.

**Rationale:** Brain Monkey integrates with PHPUnit's lifecycle, handles function stubs cleanly across test isolation boundaries, and does not require WordPress loaded.

### AD-03: PHPUnit 9.6 with yoast/phpunit-polyfills

**Context:** The test suite pins PHPUnit 9.6 (`phpunit/phpunit: ^9.6`) while the `WP_UnitTestCase` base classes target an older PHPUnit API.

**Decision:** Use `yoast/phpunit-polyfills` ^3.0 to provide the forward-compatibility shims that let the wp-phpunit test base classes run under PHPUnit 9.6.

**Rationale:** Pin to a stable, widely-supported PHPUnit major rather than trunk, and let polyfills bridge the API gap instead of patching wp-phpunit.

### AD-04: LLM_Registry static cache reset between tests

**Context:** `Gregius_Data_LLM_Registry::$providers` is a static property. PHPUnit runs test methods in the same process, so the cache persists across tests.

**Decision:** Reset `self::$providers` to an empty array via reflection in `setUp()`.

**Rationale:** Avoids a class of test-order-dependent failures where a cached value from a prior test causes unexpected behavior.

### AD-05: Separate phpunit-wp.xml.dist for WpIntegration

**Decision:** The wp-integration suite runs from a separate config file (`phpunit-wp.xml.dist`) with `WP_TESTS_RUN=1` set via `<env>`. The default `phpunit.xml.dist` only includes unit and integration suites.

**Rationale:** wp-integration tests require WordPress bootstrapped, which requires a reachable MySQL database. Including them in the default config would break local runs that don't have MySQL available.

### AD-06: wp-phpunit provisioned by install-wp-tests.sh, not committed

**Decision:** WordPress core and wp-phpunit are downloaded on demand by `bin/install-wp-tests.sh` — core into `WP_CORE_DIR` (default `/tmp/wordpress`) and the test suite (only `includes/` and `data/`) into `WP_TESTS_DIR` (default `/tmp/wordpress-tests-lib`) — rather than committed or fetched via Composer.

**Rationale:** WordPress does not distribute wp-phpunit as a Composer package. The canonical `install-wp-tests.sh` script downloads and configures both WordPress and the test suite, keeping the repository free of generated test framework files.

---

## 8. Architectural Decisions by View

| View | Decision IDs |
|---|---|
| AV-02 (Functional) | AD-02, AD-05 |
| AV-04 (Development) | AD-01, AD-03, AD-04, AD-06 |
| AV-05 (Deployment) | — (exclusions in §6) |

---

## 9. Constraints

| ID | Constraint | Affected View |
|---|---|---|
| C-01 | A MySQL test database must be reachable for Tier 3 (name configurable via `TEST_DB_NAME`) | AV-02 |
| C-02 | PHP `mysqli` extension required in the test runner environment | AV-02 |
| C-03 | `curl` or `wget` (plus `tar` and `sed`) required by `bin/install-wp-tests.sh` to download WordPress and wp-phpunit | AV-04 |
| C-04 | A MySQL server must be running for Tier 3 tests (`TEST_DB_HOST`, default `127.0.0.1`) | AV-02 |
| C-05 | PHPUnit 9.6 requires composer autoload and `yoast/phpunit-polyfills` ^3.0 | AV-04 |

---

## 10. Risks

| ID | Risk | Impact | Mitigation |
|---|---|---|---|
| R-01 | Downloaded wp-phpunit/WordPress may become incompatible with a newer PHPUnit major | Medium | Pin PHPUnit to 9.6 and the tested WordPress version via `WP_VERSION` (default 7.1.5) |
| R-02 | MySQL not reachable blocks Tier 3 execution | Medium | `./bin/test` (without `--wp`) always works; --wp mode clearly documents the MySQL requirement |
| R-03 | Static cache across test methods causes order-dependent failures (LLM_Registry pattern) | Low | Documented per-class static reset in setUp(); grep for `static` properties in new testable classes |
| R-04 | Constructors creating internal instances prevents clean dependency injection | Low | New tests use `newInstanceWithoutConstructor()`; future refactor opportunity documented in AD-01 |

---

## 11. Architecture Traceability

| Architecture Item | Requirement IDs Covered |
|---|---|
| AV-01 | TEST-FR-01..03, TEST-DR-01..03 |
| AV-02 | TEST-FR-04, TEST-DR-04..06 |
| AV-03 | TEST-FR-01..03, TEST-DR-01..03 |
| AV-04 | TEST-FR-04, TEST-DR-07 |
| AV-05 | TEST-DR-08 |
| AD-01 | TEST-DR-03 |
| AD-02 | TEST-DR-01 |
| AD-03 | TEST-DR-06 |
| AD-04 | TEST-OR-06 |
| AD-05 | TEST-DR-04 |
| AD-06 | TEST-DR-05 |

---

## 12. Readiness Checklist

- Scope and exclusions are explicit.
- All three test tiers are represented.
- Bootstrap lifecycle is documented.
- Mocking strategy is documented.
- Architecture decisions include alternatives and consequences.
- Requirement-linked coverage mapping is present.
- Companion docs are available:
  - [srs.md](srs.md)
  - [developer-documentation.md](developer-documentation.md)
