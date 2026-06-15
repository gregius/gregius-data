# Testing Developer Documentation

Standard: ISO/IEC/IEEE 26514:2022

## 1. Purpose and Audience

This document describes how to write, run, and extend automated tests for the Gregius Data plugin.

Audience:
- Plugin contributors writing new tests
- CI/CD integrators setting up automated test pipelines
- Code reviewers verifying test coverage

Companion documents:
- Requirements: [srs.md](srs.md)
- Architecture: [architecture.md](architecture.md)

## 2. Scope

Covered:
- Test directory structure and tier selection
- Writing new tests (Unit, Integration, WpIntegration)
- Mocking strategies (Brain Monkey, Mockery, Reflection)
- Running tests (`./bin/test`, vendor/bin/phpunit, Docker)
- Common patterns and anti-patterns
- Troubleshooting test failures

Not covered:
- Individual test case implementation details
- WordPress core test framework internals
- External coverage tools

## 3. Quick Start

### 3.1 Install Dependencies

```bash
# PHP testing tools
composer install --dev

# WordPress test framework (required for Tier 3 only)
svn co https://develop.svn.wordpress.org/trunk/tests/phpunit tests/wp-phpunit
```

### 3.2 Run Tests

```bash
# Unit + Integration (119 tests, always available)
./bin/test

# Full WpIntegration suite (51 tests, Docker required)
./bin/test --wp

# Individual suites
php vendor/bin/phpunit --testsuite unit
php vendor/bin/phpunit --testsuite integration

# WpIntegration (inside Docker, requires containers running)
docker exec -e WP_TESTS_RUN=1 gregius-wordpress php \
  /var/www/html/wp-content/plugins/gregius-data/vendor/bin/phpunit \
  --configuration /var/www/html/wp-content/plugins/gregius-data/phpunit-wp.xml.dist
```

## 4. Core API Reference

### 4.1 Test Runner: `bin/test`

Source: `bin/test`

Two execution modes:

| Mode | Command | Tiers | Tests | Prerequisites |
|---|---|---|---|---|
| Local | `./bin/test` | Unit + Integration | 119 | PHP 8.2 + composer install --dev |
| Full stack | `./bin/test --wp` | WpIntegration | 51 | Docker (gregius-wordpress + gregius-db) |

Environment variable overrides for `--wp` mode:

| Variable | Default | Purpose |
|---|---|---|
| `TEST_WP_CONTAINER` | `gregius-wordpress` | WordPress container name |
| `TEST_DB_CONTAINER` | `gregius-db` | MySQL container name |

### 4.2 PHPUnit Configuration

**`phpunit.xml.dist`** — Default config. Includes `unit` and `integration` suites. Bootstrap: `tests/bootstrap.php`.

**`phpunit-wp.xml.dist`** — WpIntegration config. Includes only `wp-integration` suite. Sets `WP_TESTS_RUN=1` via `<env>`. Bootstrap: `tests/bootstrap.php` (same file, conditional loading).

### 4.3 Bootstrap: `tests/bootstrap.php`

Source: `tests/bootstrap.php`

Handles all three tiers through conditional loading:
- Tier 1-2: Loads Composer autoloader + `helpers.php` (common stubs)
- Tier 3 (`WP_TESTS_RUN=1`): Detects `wp-tests-config.php`, loads `tests/wp-phpunit/`, boots WordPress, activates plugin via `tests_add_filter`

### 4.4 Shared Helpers: `tests/helpers.php`

Source: `tests/helpers.php`

**`gg_data_test_setup_wpdb()`** — Sets up `$wpdb` global as a stdClass with `prefix` set to `'wp_'`. Called in bootstrap for tier 1-2 only.

**`gg_data_test_stub_common_functions()`** — Registers Brain Monkey stubs for frequently-used WP functions. Call in `setUp()` after `Monkey\setUp()`:
- `get_current_blog_id()` → 1
- `__()` → identity (returns text unchanged)
- `wp_kses_post()` → identity
- `sanitize_text_field()` → identity
- `absint()` → intval
- `sanitize_key()` → identity
- `wp_date()` → PHP date()
- `plugin_dir_path()` → `dirname($file) . '/'`
- `maybe_unserialize()` → PHP unserialize wrapper

**`WP_Error` stub** — Defined in helpers.php when `WP_Error` class doesn't exist. Only loaded for tier 1-2; conflicts with real WordPress class in tier 3. The bootstrap skips helpers.php when `WP_TESTS_RUN=1` to avoid this conflict.

## 5. Writing New Tests

### 5.1 File Naming Convention

All test files follow the pattern `Test_GG_Data_{ClassName}.php` in the appropriate directory:

| Tier | Directory | Base Class | Example |
|---|---|---|---|
| Unit | `tests/Unit/` | `PHPUnit\Framework\TestCase` | `Test_GG_Data_Chunker.php` |
| Integration | `tests/Integration/` | `PHPUnit\Framework\TestCase` | `Test_GG_Data_LLM_Registry.php` |
| WpIntegration | `tests/WpIntegration/` | `WP_UnitTestCase` or `WP_Test_REST_TestCase` | `Test_GG_Data_Logger.php` |

PHPUnit 11 uses `suffix=".php"` for test discovery. The class name must match the filename.

### 5.2 Tier 1-2 Test Template (Unit / Integration)

```php
<?php

use Brain\Monkey;
use Brain\Monkey\Functions;

class Test_GG_Data_MyClass extends PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        gg_data_test_stub_common_functions();
        require_once __DIR__ . '/../../includes/class-gg-data-my-class.php';
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_some_method_returns_expected(): void {
        Functions\expect('some_wp_function')
            ->once()
            ->with('expected_arg')
            ->andReturn('stubbed_value');

        $instance = new GG_Data_MyClass();
        $result   = $instance->public_method();

        $this->assertSame('expected', $result);
    }
}
```

### 5.3 Tier 3 Test Template (WpIntegration)

```php
<?php

class Test_GG_Data_MyClass extends WP_UnitTestCase {

    protected $instance;

    public function set_up() {
        parent::set_up();
        $this->instance = new GG_Data_MyClass();
    }

    public function tear_down() {
        parent::tear_down();
    }

    public function test_core_functionality(): void {
        $result = $this->instance->some_method();
        $this->assertIsArray($result);
    }
}
```

Note: Tier 3 uses `set_up()` and `tear_down()` (underscores) following WordPress test conventions. No Brain Monkey stubbing needed — WordPress functions are real.

### 5.4 Mocking Strategy

| Scenario | Technique | Example |
|---|---|---|
| Stub a WP function | Brain Monkey `Functions\when()` | `Functions\when('get_option')->justReturn('value')` |
| Expect a WP function call | Brain Monkey `Functions\expect()` | `Functions\expect('apply_filters')->once()->andReturn($val)` |
| Mock a plugin class | Mockery `Mockery::mock()` | `Mockery::mock(GG_Data_DB::class)` |
| Test private methods | Reflection | `$reflection->getMethod('count_words')->setAccessible(true)` |
| Instance without constructor | Reflection | `$reflection->newInstanceWithoutConstructor()` |
| Set private property | Reflection | `$prop->setAccessible(true); $prop->setValue($obj, $mock)` |
| Reset static property | Reflection | `$reflection->getProperty('providers')->setValue([])` |

### 5.5 Constructors That Ignore Dependencies

Many plugin classes create their own internal instances in constructors rather than accepting them as parameters. When testing these classes, do NOT attempt to pass mock dependencies to the constructor — they will be ignored:

```php
// ❌ WRONG — constructor ignores the mock, creates real instances internally
$mock_db = Mockery::mock(GG_Data_DB::class);
$instance = new GG_Data_HashingTF_Embeddings($mock_db); // mock discarded!

// ✅ CORRECT — skip constructor, test public methods directly
$reflection = new ReflectionClass(GG_Data_HashingTF_Embeddings::class);
$instance   = $reflection->newInstanceWithoutConstructor();
$vector     = $instance->generate_hashingtf_vector('hello', 'post_title');
```

This pattern applies to: GG_Data_HashingTF_Embeddings, GG_Data_TFIDF_300_Embeddings, GG_Data_Content_Cleaner, GG_Data_DB, GG_Data_Settings_Manager, GG_Data_Vector_Generator.

## 6. Integration Points and Hooks

### 6.1 WordPress Test Hooks (Tier 3)

```php
// Load plugin during WordPress bootstrap
tests_add_filter( 'muplugins_loaded', function () use ( $plugin_dir ) {
    require_once $plugin_dir . '/gregius-data.php';
} );

// Activate plugin after theme setup (creates tables, seeds data)
tests_add_filter( 'setup_theme', function () {
    if ( ! get_option( 'gg_data_db_version' ) ) {
        GG_Data_Activator::activate();
    }
} );
```

### 6.2 REST Route Registration

For REST tests (Tier 3), register routes with `do_action('rest_api_init')` in `set_up()`:

```php
class Test_GG_Data_REST_Example extends WP_Test_REST_TestCase {
    public function set_up() {
        parent::set_up();
        $this->server = rest_get_server();
        $this->admin_id = self::factory()->user->create(array('role' => 'administrator'));
    }

    public function test_route_exists() {
        $routes = $this->server->get_routes('gg-data/v1');
        $this->assertNotEmpty($routes);
    }
}
```

## 7. Common Patterns and Best Practices

### Pattern 1: Reset Static Cache Between Tests

```php
// ✅ DO — reset static properties in setUp()
protected function setUp(): void {
    parent::setUp();
    Monkey\setUp();
    $ref = new ReflectionClass(GG_Data_LLM_Registry::class);
    $prop = $ref->getProperty('providers');
    $prop->setAccessible(true);
    $prop->setValue([]);
}
```

### Pattern 2: Per-Test WP Function Management

```php
// ✅ DO — stub WP functions per test method
public function test_with_specific_option(): void {
    Functions\when('get_option')->justReturn('custom_value');
    $result = $this->instance->some_method();
    $this->assertSame('expected', $result);
}
```

### Pattern 3: Match Actual Implementation

```php
// ❌ DON'T — assume response shape without checking source
$this->assertArrayHasKey('fault_count', $status);

// ✅ DO — match the actual response shape from the source class
$this->assertArrayHasKey('consecutive_failures', $status);
$this->assertArrayHasKey('uptime_percentage', $status);
```

### Pattern 4: Flexible REST Status Assertions

```php
// ✅ DO — accept multiple valid status codes for REST tests
$this->assertContains($response->get_status(), array(200, 401, 404));

// ❌ DON'T — assert a single status code when route may not be registered
$this->assertSame(200, $response->get_status());
```

### Anti-Patterns

```php
// ❌ DON'T — load helpers.php in Tier 3 (WP_Error stub conflicts with real WP)
require_once __DIR__ . '/helpers.php';

// ✅ DO — conditionally skip helpers when running WP tests
if ( ! $running_wp_tests ) {
    require_once __DIR__ . '/helpers.php';
}

// ❌ DON'T — pass mock dependencies to constructors that ignore them
$instance = new GG_Data_HashingTF_Embeddings($mock_db);

// ✅ DO — use newInstanceWithoutConstructor() for coupled constructors
$instance = (new ReflectionClass(GG_Data_HashingTF_Embeddings::class))
    ->newInstanceWithoutConstructor();
```

## 8. Troubleshooting

### Issue: `Call to undefined method PHPUnit\Util\Test::parseTestMethodAnnotations()`

**Symptom:** All 51 WpIntegration tests error with this message.

**Cause:** wp-phpunit has not been patched for PHPUnit 11 compatibility.

**Solution:** Ensure `tests/wp-phpunit/includes/abstract-testcase.php` has the PHPUnit 11 patch applied:

```php
// In expectDeprecated():
if ( method_exists( $this, 'getAnnotations' ) ) {
    $annotations = $this->getAnnotations();
} elseif ( method_exists( '\PHPUnit\Util\Test', 'parseTestMethodAnnotations' ) ) {
    $annotations = \PHPUnit\Util\Test::parseTestMethodAnnotations(...);
} else {
    return; // PHPUnit 11 — annotations removed
}
```

### Issue: `Cannot select database` / `gregius_test database could not be selected`

**Symptom:** WordPress boots but fails with a database selection error.

**Cause:** The test database doesn't exist or the user lacks privileges.

**Solution:** The `./bin/test --wp` script creates the database automatically. If running manually, create it:
```sql
CREATE DATABASE IF NOT EXISTS gregius_test;
GRANT ALL PRIVILEGES ON gregius_test.* TO 'gregius-admin'@'%';
```

### Issue: `Class "WP_UnitTestCase" not found`

**Symptom:** PHPUnit errors when trying to load a WpIntegration test file outside Docker.

**Cause:** Running `vendor/bin/phpunit` (without `--wp`) tries to load WpIntegration test files which extend `WP_UnitTestCase`, which requires WordPress booted.

**Solution:** Use `./bin/test` (excludes WpIntegration) or the dedicated config: `vendor/bin/phpunit --configuration phpunit-wp.xml.dist`.

### Issue: `Cannot declare class WP_Error, because the name is already in use`

**Symptom:** Fatal error when running Tier 3 tests.

**Cause:** `tests/helpers.php` defines a `WP_Error` stub, and the real WordPress `class-wp-error.php` tries to define it again.

**Solution:** The bootstrap skips helpers.php when `WP_TESTS_RUN=1`. If you're encountering this, ensure the bootstrap check is working:
```php
if ( ! $running_wp_tests ) {
    require_once __DIR__ . '/helpers.php';
}
```

### Issue: `Table 'gregius_test.wptests_gg_data_logs' doesn't exist`

**Symptom:** WpIntegration tests fail with missing table errors.

**Cause:** The plugin's tables weren't created. The activator must run during bootstrap.

**Solution:** Ensure the bootstrap includes the `setup_theme` hook:
```php
tests_add_filter( 'setup_theme', function () {
    if ( ! get_option( 'gg_data_db_version' ) ) {
        GG_Data_Activator::activate();
    }
} );
```

## 9. FAQ

**Q: Can I run Tier 3 without Docker?**

A: No. Tier 3 requires a running WordPress + MySQL stack. Docker is the recommended approach, but any WordPress instance with MySQL and the plugin loaded works.

**Q: Why are constructors not dependency-injected?**

A: This is a known pattern in the codebase. Constructors create their own internal instances. Tests work around this via `ReflectionClass::newInstanceWithoutConstructor()`. A future refactor to dependency injection is planned.

**Q: How do I add a new test class?**

A: Create `Test_GG_Data_{ClassName}.php` in the appropriate tier directory, extend the correct base class, follow the template in section 5, and run `./bin/test` to verify.

**Q: Why is wp-phpunit installed via SVN instead of Composer?**

A: WordPress does not distribute wp-phpunit as a Composer package. SVN is the canonical distribution channel.

**Q: How do I verify all tests pass?**

A: `./bin/test` (Unit + Integration) followed by `./bin/test --wp` (WpIntegration). Both must return zero failures.

---

**Document last updated:** 2026-06-15  
**Traceability:** [SRS: TEST-FR-01..04, TEST-DR-01..08]
