# Software Requirements Specification (SRS)

Standard: ISO/IEC/IEEE 29148:2018

## Document Information

| Field | Value |
|---|---|
| Project | Gregius Data |
| Software Component | Testing Subsystem |
| Version | 1.0 |
| Date | 2026-06-15 |
| Author | Gregius |
| StRS Reference | N/A |
| SyRS Reference | N/A |
| Status | Implemented |

## 1. Introduction

### 1.1 System Purpose

This SRS defines what the Gregius Data testing subsystem must do to provide automated test coverage across three tiers — unit, integration, and full WordPress integration — with a single-command test runner suitable for local development and CI/CD pipelines.

### 1.2 System Scope

The testing subsystem includes:
- Three-tier test architecture (Unit, Integration, WpIntegration)
- Shared test infrastructure (bootstrap, helpers, composer dependencies)
- Test runner script (`bin/test`) with two execution modes
- PHPUnit configuration for all tiers
- wp-phpunit integration (SVN checkout, PHPUnit 11 compat patch)
- Test data management (test database, container config generation)

Software identifier: gregius-data-testing

Repository path: `public/wp-content/plugins/gregius-data`

### 1.3 System Overview

#### 1.3.1 System Context

The testing subsystem provides regression prevention for the Gregius Data plugin. It runs locally during development and in CI/CD pipelines for pull request validation.

#### 1.3.2 System Functions Summary

- Execute unit tests for pure-logic classes (no WordPress required).
- Execute integration tests with mocked WordPress functions (Brain Monkey).
- Execute full WordPress integration tests in a Docker container with real WordPress + MySQL.
- Provide a single-command runner (`./bin/test`) for all test tiers.
- Exclude test artifacts from production plugin distributions.
- Support CI/CD execution via GitHub Actions or similar platforms.

#### 1.3.3 User Characteristics

| User Class | Technical Level | Primary Interaction Point |
|---|---|---|
| Plugin Contributor | Technical | `./bin/test`, IDE test runner |
| CI/CD Integrator | Technical | `./bin/test --wp` in Docker context |
| Code Reviewer | Technical | PHPUnit output, assertion results |

## 2. References

- `docs/testing/architecture.md`
- `docs/testing/developer-documentation.md`
- `tests/bootstrap.php`
- `tests/helpers.php`
- `bin/test`
- `phpunit.xml.dist`
- `phpunit-wp.xml.dist`

## 3. Software Requirements

Requirement notation:
- MUST: mandatory
- SHOULD: strongly recommended
- MAY: optional

### 3.1 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| TEST-FR-01 | The software MUST support unit-level test execution without WordPress loaded, targeting 8 classes with 69 test methods. | Must |
| TEST-FR-02 | The software MUST support integration-level test execution using Brain Monkey for WP function mocking, targeting 7 classes with 50 test methods. | Must |
| TEST-FR-03 | The software MUST support full WordPress integration test execution inside a Docker container with real WordPress and MySQL, targeting 11 classes with 51 test methods. | Must |
| TEST-FR-04 | The software MUST provide a single-command test runner (`./bin/test`) that executes all available test tiers without manual configuration. | Must |
| TEST-FR-05 | The software MUST support running individual test suites via PHPUnit CLI (`--testsuite unit`, `--testsuite integration`). | Must |
| TEST-FR-06 | The software MUST support environment variable overrides for Docker container names (`TEST_WP_CONTAINER`, `TEST_DB_CONTAINER`). | Must |
| TEST-FR-07 | The software MUST auto-create the test database (`gregius_test`) and grant privileges before WpIntegration execution. | Must |
| TEST-FR-08 | The software MUST generate container-compatible test configuration (`wp-tests-config.php`) at runtime for WpIntegration tests. | Must |

### 3.2 Data and Contract Requirements

| ID | Requirement | Priority |
|---|---|---|
| TEST-DR-01 | Tier 1 and Tier 2 tests MUST use Brain Monkey for WordPress function mocking and Mockery for class mocking. | Must |
| TEST-DR-02 | Tier 3 tests MUST use real WordPress functions and database without Brain Monkey stubbing. | Must |
| TEST-DR-03 | Tests for classes with internally-coupled constructors MUST use `ReflectionClass::newInstanceWithoutConstructor()` for instance creation. | Must |
| TEST-DR-04 | WpIntegration tests MUST run from a separate PHPUnit configuration (`phpunit-wp.xml.dist`) to avoid loading `WP_UnitTestCase` when WordPress is not bootstrapped. | Must |
| TEST-DR-05 | The wp-phpunit test framework MUST be installed via SVN into `tests/wp-phpunit/` and excluded from version control via `.gitignore`. | Must |
| TEST-DR-06 | The `expectDeprecated()` method in wp-phpunit MUST be patched for PHPUnit 11 compatibility (`parseTestMethodAnnotations` removal). | Must |
| TEST-DR-07 | All test files MUST follow the naming convention `Test_GG_Data_{ClassName}.php` in their respective tier directory. | Must |
| TEST-DR-08 | Test files, PHPUnit configurations, vendor dependencies, and Composer metadata MUST be excluded from the production plugin zip. | Must |

### 3.3 Software Operations and Control Requirements

| ID | Requirement | Priority |
|---|---|---|
| TEST-OR-01 | The `./bin/test` command (without `--wp`) MUST execute Unit and Integration tests only, with zero Docker dependency. | Must |
| TEST-OR-02 | The `./bin/test --wp` command MUST execute WpIntegration tests after verifying Docker containers are running and the test database exists. | Must |
| TEST-OR-03 | WpIntegration tests MUST bootstrap WordPress via `tests_add_filter('muplugins_loaded')` and activate the plugin via `tests_add_filter('setup_theme')`. | Must |
| TEST-OR-04 | The test bootstrap MUST skip loading `tests/helpers.php` (which defines a `WP_Error` stub) when running WpIntegration tests to avoid class redeclaration conflicts. | Must |
| TEST-OR-05 | Tier 1-2 tests MUST call `Monkey\setUp()` in setUp and `Monkey\tearDown()` in tearDown for proper function stub isolation. | Must |
| TEST-OR-06 | LLM_Registry static cache MUST be reset via reflection in setUp before each test method. | Must |

### 3.4 Quality and Non-Functional Requirements

| ID | Requirement | Verification Method |
|---|---|---|
| TEST-QR-01 | Unit tests MUST complete in under 1 second. | Timing baseline |
| TEST-QR-02 | Integration tests MUST complete in under 2 seconds. | Timing baseline |
| TEST-QR-03 | WpIntegration tests MUST complete in under 30 seconds with warm Docker containers. | Timing baseline |
| TEST-QR-04 | All 170 tests MUST produce zero failures on the `main` branch. | `./bin/test && ./bin/test --wp` |
| TEST-QR-05 | Production plugin zip MUST be under 1 MB (excluding test and dev artifacts). | `node bin/plugin-zip.js` output |

## 4. Test Coverage Matrix

### 4.1 Tier 1 — Unit Tests (tests/Unit/)

| Source Class | Test File | Assertions |
|---|---|---|
| GG_Data_Activator | Test_GG_Data_Activator.php | 6 |
| GG_Data_Chunker | Test_GG_Data_Chunker.php | 12 |
| GG_Data_Content_Processor | Test_GG_Data_Content_Processor.php | 8 |
| GG_Data_Error_Handler | Test_GG_Data_Error_Handler.php | 13 |
| GG_Data_Format_Json_Output | Test_GG_Data_Format_Json_Output.php | 8 |
| GG_Data_Prompt_Resolver | Test_GG_Data_Prompt_Resolver.php | 8 |
| GG_Data_Table_Prefix_Resolver | Test_GG_Data_Table_Prefix_Resolver.php | 8 |
| GG_Data_Token_Counter | Test_GG_Data_Token_Counter.php | 6 |
| **Total** | **8 files** | **69 tests** |

### 4.2 Tier 2 — Integration Tests (tests/Integration/)

| Source Class | Test File | Assertions |
|---|---|---|
| GG_Data_Content_Cleaner | Test_GG_Data_Content_Cleaner.php | 10 |
| GG_Data_DB | Test_GG_Data_DB.php | 4 |
| GG_Data_HashingTF_Embeddings | Test_GG_Data_HashingTF_Embeddings.php | 9 |
| GG_Data_LLM_Registry | Test_GG_Data_LLM_Registry.php | 5 |
| GG_Data_Settings_Manager | Test_GG_Data_Settings_Manager.php | 13 |
| GG_Data_TFIDF_300_Embeddings | Test_GG_Data_TFIDF_300_Embeddings.php | 5 |
| GG_Data_Vector_Generator | Test_GG_Data_Vector_Generator.php | 6 |
| **Total** | **7 files** | **50 tests** |

### 4.3 Tier 3 — WpIntegration Tests (tests/WpIntegration/)

| Source Class | Test File | Assertions |
|---|---|---|
| GG_Data_Abilities_Manager | Test_GG_Data_Abilities_Manager.php | 4 |
| GG_Data_Connection_Health | Test_GG_Data_Connection_Health.php | 7 |
| GG_Data_Logger | Test_GG_Data_Logger.php | 11 |
| GG_Data (Main Plugin) | Test_GG_Data_Main_Plugin.php | 5 |
| GG_Data_Activator | Test_GG_Data_Plugin_Activation.php | 5 |
| GG_Data_RAG_Service | Test_GG_Data_RAG_Service.php | 4 |
| GG_Data_REST_Connections_Controller | Test_GG_Data_REST_Connections.php | 4 |
| GG_Data_REST_Prompts_Controller | Test_GG_Data_REST_Prompts.php | 3 |
| GG_Data_REST_Search_Controller | Test_GG_Data_REST_Search.php | 3 |
| GG_Data_REST_Sync_Controller | Test_GG_Data_REST_Sync.php | 2 |
| GG_Data_Sync_Service | Test_GG_Data_Sync_Service.php | 2 |
| **Total** | **11 files** | **51 tests** |

### 4.4 Exclusions

| Class | Reason |
|---|---|
| GG_Data_Citation_Resolver | Class file does not exist in the codebase |
| AI Provider classes (OpenAI, Anthropic, Gemini, DeepSeek, Cohere, Voyage) | Deferred — require external API mocking |
| GG_Data_PostgREST_Provider | Deferred — requires Supabase/PostgREST endpoint mocking |

## 5. Verification and Acceptance

| Requirement Group | Verification Method | Tool or Evidence |
|---|---|---|
| Functional requirements | Test execution across all tiers | `./bin/test && ./bin/test --wp` |
| Data/contract requirements | File inspection + test pattern audit | Code review of tests/ directory |
| Operations requirements | Mode execution verification | `./bin/test` (local), `./bin/test --wp` (Docker) |
| Quality requirements | Zip size + test timing | `node bin/plugin-zip.js`, PHPUnit timing output |
