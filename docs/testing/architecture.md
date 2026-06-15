# Architecture Description: Testing Subsystem

**Standard:** ISO/IEC/IEEE 42010:2022  
**Component:** Testing  
**Version:** 1.0.0  
**Date:** 2026-06-15  
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
| CI/CD Integrators | One-command test execution, container-agnostic design | High |
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
    PHPUnit alone       PHPUnit + Brain        WordPress Docker
    No WordPress         Monkey + Mockery       Container (gregius-wordpress)
          |                    |                    |
   tests/Unit/         tests/Integration/    tests/WpIntegration/
   69 tests             50 tests              51 tests
```

```
Tier 3 expanded:
    ./bin/test --wp
          |
   +------+------+
   |             |
  Docker        Docker
  gregius-db    gregius-wordpress
   |             |
  CREATE DB     wp-tests-config.php (generated)
  GRANT PRIV     |
                wp-phpunit bootstrap
                     |
                WordPress boots
                     |
                GG_Data_Activator::activate()
                     |
                PHPUnit runs WpIntegration suite
```

### 2.2 Context Rationale

Tier 1 and Tier 2 run locally in PHP without WordPress. They provide fast feedback during development. Tier 3 requires Docker for a real WordPress + MySQL stack and validates activation, REST routes, database operations, and full plugin lifecycle.

The `bin/test` script abstracts all three tiers behind two commands: `./bin/test` for tiers 1+2 (119 tests, <1s) and `./bin/test --wp` for tier 3 (51 tests, ~5s with warm containers).

---

## 3. Functional View (AV-02)

### 3.1 Bootstrap Flow

```
phpunit.xml.dist loads:
  └── tests/bootstrap.php
        ├── Composer autoload
        ├── helpers.php (tier 1-2 only, WP_Error stub conflicts with real WP)
        ├── $wpdb global stub (tier 1-2 only)
        └── IF WP_TESTS_RUN=1:
              ├── Find wp-tests-config.php (auto-detect or container-generated)
              ├── Load tests/wp-phpunit/includes/functions.php
              ├── tests_add_filter('muplugins_loaded', load plugin)
              ├── tests_add_filter('setup_theme', activate plugin)
              └── Load tests/wp-phpunit/includes/bootstrap.php (boots WordPress)
```

### 3.2 `bin/test` Modes

| Mode | Command | Tiers | Runtime | Docker |
|---|---|---|---|---|
| Local | `./bin/test` | Unit + Integration | <1s | Not required |
| Full stack | `./bin/test --wp` | WpIntegration | ~5s | Required |

`--wp` mode:
1. Creates `gregius_test` database via `docker exec gregius-db`
2. Writes container-compatible `wp-tests-config.php` via `docker exec gregius-wordpress tee`
3. Runs `vendor/bin/phpunit --configuration phpunit-wp.xml.dist` inside the container

Container names are configurable via `TEST_WP_CONTAINER` and `TEST_DB_CONTAINER` environment variables.

---

## 4. Information View (AV-03)

### 4.1 Directory Structure

```
gregius-data/
├── bin/
│   └── test                    # Test runner script
├── tests/
│   ├── bootstrap.php           # Conditional bootstrap (all tiers)
│   ├── helpers.php             # Shared stubs (tier 1-2 only)
│   ├── phpunit.xml.dist        # Default: unit + integration suites
│   ├── phpunit-wp.xml.dist     # WP tests: WpIntegration suite
│   ├── wp-phpunit/             # SVN checkout (gitignored)
│   ├── Unit/                   # Tier 1: 8 files, 69 tests
│   ├── Integration/            # Tier 2: 7 files, 50 tests
│   └── WpIntegration/          # Tier 3: 11 files, 51 tests
├── composer.json               # require-dev: phpunit, brain/monkey, mockery, polyfills
└── vendor/                     # Composer dev dependencies (gitignored)
```

### 4.2 Class Inheritance Tree

```
PHPUnit\Framework\TestCase
├── (Tier 1 & 2 tests)     # Test_GG_Data_* extends this directly
│   └── Mocking via Brain Monkey + Mockery
│
└── ... (through polyfills) ...
     └── WP_UnitTestCase           # Tier 3 tests with real WordPress
          └── WP_Test_REST_TestCase  # Tier 3 REST tests
```

### 4.3 Test File Naming Convention

All test files follow `Test_GG_Data_{ClassName}.php` in the appropriate tier directory. PHPUnit 11 uses `suffix=".php"` for test discovery since classes mirror filenames.

---

## 5. Development View (AV-04)

### 5.1 Contributor Setup

```bash
# 1. Install PHP dependencies
composer install --dev

# 2. Install wp-phpunit test framework (required for Tier 3)
svn co https://develop.svn.wordpress.org/trunk/tests/phpunit tests/wp-phpunit
```

### 5.2 Local Execution

```bash
./bin/test           # Unit + Integration (119 tests, no Docker)
./bin/test --wp      # All tiers including WpIntegration (Docker required)
```

### 5.3 CI/CD Integration

```yaml
- name: Start services
  run: docker compose up -d --wait
- name: Run full test suite
  working-directory: public/wp-content/plugins/gregius-data
  run: ./bin/test --wp
```

---

## 6. Deployment View (AV-05)

Tests are excluded from the production plugin zip via `bin/plugin-zip.js` exclusions: `tests/`, `phpunit.xml.dist`, `phpunit-wp.xml.dist`, `vendor/`, `composer.json`, `composer.lock`.

The production zip contains only runtime plugin files. Testing infrastructure lives in the git repository only.

---

## 7. Architectural Decisions

### AD-01: ReflectionClass::newInstanceWithoutConstructor for coupled constructors

**Context:** Many plugin classes (GG_Data_HashingTF_Embeddings, GG_Data_TFIDF_300_Embeddings, GG_Data_Content_Cleaner, GG_Data_DB, GG_Data_Settings_Manager, GG_Data_Vector_Generator) create their own internal dependencies in constructors rather than accepting them as parameters.

**Decision:** Tests create instances via `ReflectionClass::newInstanceWithoutConstructor()` and inject mock dependencies through private property reflection.

**Alternatives considered:**
1. Refactor constructors to accept dependencies (high risk, broad scope)
2. Stub all WP functions called in constructors (fragile and test-order-dependent)

**Rationale:** Reflection avoids modifying production code while still enabling isolated testing. The constructors remain unchanged for now; a future refactor to dependency injection would simplify both the codebase and the tests.

### AD-02: Brain Monkey for WP function mocking (Tier 1-2)

**Decision:** Use Brain Monkey's `Functions\when()` for WP function stubs (`get_current_blog_id`, `__`, `plugin_dir_path`, `maybe_unserialize`, etc.) and Mockery for class mocking.

**Rationale:** Brain Monkey integrates with PHPUnit's lifecycle, handles function stubs cleanly across test isolation boundaries, and does not require WordPress loaded.

### AD-03: PHPUnit 11 compat patch for wp-phpunit

**Context:** wp-phpunit's `expectDeprecated()` calls `PHPUnit\Util\Test::parseTestMethodAnnotations()` which was removed in PHPUnit 11.

**Decision:** Patch `tests/wp-phpunit/includes/abstract-testcase.php` to check `method_exists()` before calling the removed method, falling back to a no-op.

**Rationale:** WordPress trunk's wp-phpunit has not yet been updated for PHPUnit 11. The patch is minimal and documented.

### AD-04: LLM_Registry static cache reset between tests

**Context:** `GG_Data_LLM_Registry::$providers` is a static property. PHPUnit runs test methods in the same process, so the cache persists across tests.

**Decision:** Reset `self::$providers` to an empty array via reflection in `setUp()`.

**Rationale:** Avoids a class of test-order-dependent failures where a cached value from a prior test causes unexpected behavior.

### AD-05: Separate phpunit-wp.xml.dist for WpIntegration

**Decision:** The wp-integration suite runs from a separate config file (`phpunit-wp.xml.dist`) with `WP_TESTS_RUN=1` set via `<env>`. The default `phpunit.xml.dist` only includes unit and integration suites.

**Rationale:** wp-integration tests require WordPress bootstrapped, which requires Docker and MySQL. Including them in the default config would break local runs that don't have Docker available.

### AD-06: wp-phpunit in-tree via SVN, not Composer

**Decision:** wp-phpunit is installed via `svn co` into `tests/wp-phpunit/` rather than as a Composer dependency.

**Rationale:** WordPress does not distribute wp-phpunit as a Composer package. SVN is the canonical distribution channel. The directory is gitignored; contributors install it as a setup step.

---

## 8. Architectural Decisions by View

| View | Decision IDs |
|---|---|
| AV-02 (Functional) | AD-02, AD-05 |
| AV-04 (Development) | AD-01, AD-04, AD-06 |
| AV-05 (Deployment) | AD-03 |

---

## 9. Constraints

| ID | Constraint | Affected View |
|---|---|---|
| C-01 | MySQL must be accessible for Tier 3 (Docker container `gregius-db` running) | AV-02 |
| C-02 | PHP `mysqli` extension required in the test runner environment | AV-02 |
| C-03 | `svn` command-line tool required for wp-phpunit setup | AV-04 |
| C-04 | Docker must be running for Tier 3 tests (`gregius-wordpress`, `gregius-db`) | AV-02 |
| C-05 | PHPUnit 11 requires composer autoload and `yoast/phpunit-polyfills` ^3.0 | AV-04 |

---

## 10. Risks

| ID | Risk | Impact | Mitigation |
|---|---|---|---|
| R-01 | wp-phpunit SVN checkout may become stale or incompatible with newer PHPUnit versions | Medium | Pin to trunk revision in documentation; update patch as needed |
| R-02 | Docker containers not running blocks Tier 3 execution | Medium | `./bin/test` (without `--wp`) always works; --wp mode clearly documents Docker requirement |
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
| AD-04 | TEST-DR-02 |
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
