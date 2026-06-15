# Gregius Data Security Audit Report

Status: In progress  
Owner: Security Review Team  
Date started: 2026-04-22  
Repository: gregius-data plugin  
Branch baseline: main  
Commit baseline: a93b763  
Plugin version: 1.0.0  
Requires PHP: 8.2

## 1) Audit Objective

Perform a comprehensive static and dynamic security audit of the full plugin codebase and produce a risk register ranked by exploitability and impact.

Primary output:
- Severity-ranked risk register with reproducible evidence and remediation guidance.

## 2) Scope

In scope:
- Full plugin at /home/hector/projects/gregius/public/wp-content/plugins/gregius-data
- Bootstrap and lifecycle files: gregius-data.php, uninstall.php, activation/deactivation flows
- All API, admin, hooks, sync, provider, vector, CLI, and SQL modules
- JS/build artifacts that interact with privileged endpoints

Out of scope:
- WordPress core internals
- Host/server hardening outside plugin code
- Third-party provider infrastructure (OpenAI/Supabase/PostgREST service internals)

## 3) Methodology

### 3.1 Static analysis

Run and collect evidence for:
- Plugin Check (security/compliance findings)
- PHPCS with WPCS security-relevant rules
- Semgrep PHP/WordPress security rules
- Manual source-to-sink review for high-risk modules

Static review themes:
- Authentication and authorization
- CSRF/nonces and state-changing action protection
- Input validation and sanitization
- Output escaping and sensitive data exposure
- Injection risks (SQL/command/serialization)
- External call hardening and transport assumptions
- Unsafe primitive usage (eval, unserialize, dynamic include/require, file writes)

### 3.2 Dynamic validation

Execute endpoint and behavior tests with role matrix:
- Anonymous user
- Subscriber
- Editor
- Administrator

Dynamic checks include:
- REST route access control and denial behavior
- Capability and nonce enforcement for state-changing actions
- Abuse probing for expensive endpoints (sync, vectors, RAG)
- Error handling validation (no sensitive stack traces/secrets)

## 4) Priority Surfaces

P0 focus (first pass):
- RAG endpoint permissions and effective public exposure controls
- Connection/settings endpoints that process credentials
- Sync/vector endpoints that can cause expensive operations or data integrity impacts
- Logging and error surfaces that may leak sensitive config

P1 focus:
- SQL generation/execution modules
- CLI destructive commands and privilege assumptions
- Uninstall/cleanup data handling

## 5) Severity Model

Critical:
- Unauthenticated remote exploit with high impact (RCE, auth bypass to privileged operations, secret exfiltration at scale)

High:
- Privilege escalation, significant data tampering/exfiltration, or reliable service-impact abuse

Medium:
- Security control weakness requiring specific conditions or lower impact leakage/modification

Low:
- Defense-in-depth gaps or limited-impact hardening issues

Informational:
- Non-exploitable improvements, architecture notes, and hygiene recommendations

## 6) Finding Template

Use this structure for every finding:

- ID: SEC-###
- Severity: Critical/High/Medium/Low/Info
- Title:
- CWE category:
- Affected files/symbols:
- Preconditions:
- Reproduction steps:
- Expected secure behavior:
- Actual behavior:
- Impact:
- Exploitability confidence: High/Medium/Low
- Remediation recommendation:
- Owner:
- ETA:
- Status: Open/Accepted risk/Fixed/Verified

## 7) Initial Hypotheses To Validate

- RAG route authorization may be permissive by default and rely on filters/settings.
- Some high-cost endpoints may lack abuse controls such as rate limits or stronger permission scoping.
- Credential-bearing settings flows may expose more data than intended in responses/logs under error conditions.

## 8) Evidence Log

Record command outputs, screenshots, and request/response traces here.

- [~] Static scan artifacts captured (initial reconnaissance complete, full toolchain pending)
- [~] Dynamic test matrix completed (RAG + core admin endpoint matrix complete, remaining families pending)
- [ ] False positives triaged and documented
- [ ] Re-test evidence captured for each fixed issue

### 8.1 Initial static reconnaissance (2026-04-22)

Commands executed from plugin root:
- `rg -n "register_rest_route\(" includes/api | wc -l` -> 93
- `rg -n "permission_callback" includes/api | wc -l` -> 104
- `rg -n "gg_data_rag_endpoint_permission|ACCESS_PUBLIC|ACCESS_LOGGED_IN|ACCESS_CAPABILITY" includes/hooks/class-gg-data-rag-security-hooks.php includes/api/class-gg-data-rest-rag-controller.php`
- `rg -n "wp_remote_get|wp_remote_post|wp_remote_request|wp_safe_remote_get|wp_safe_remote_post" includes`
- `rg -n "unserialize\(|maybe_unserialize\(" includes`

Code-verified notes:
- `includes/api/class-gg-data-rest-rag-controller.php` permission callback calls `apply_filters( 'gg_data_rag_endpoint_permission', true, $request )`.
- `includes/hooks/class-gg-data-rag-security-hooks.php` defaults `rag_access_level` to `public` and unknown access level also returns allow.
- `includes/class-gg-data-settings-manager.php` includes a double `maybe_unserialize` recovery path for legacy data.

### 8.2 Follow-up static validation (2026-04-22)

Code-verified notes:
- `includes/api/class-gg-data-rest-connections-controller.php` enforces admin capability through `check_admin_permission()`.
- State-changing connection endpoints verify REST nonce (`X-WP-Nonce` + `wp_verify_nonce( ..., 'wp_rest' )`).
- Sensitive connection fields are masked in API responses via `mask_sensitive_connection_fields()` for `password`, `publishable_key`, and `secret_key`.
- `includes/api/class-gg-data-rest-sync-controller.php` uses `permissions_check()` with `current_user_can( 'manage_options' )`.
- No explicit request throttling/rate-limiting controls were identified in `includes/api/class-gg-data-rest-rag-controller.php` during static pass.

### 8.3 Runtime readiness and execution path (Docker + WSL2)

Read-only smoke checks executed from `/home/hector/projects/gregius`:
- `./bin/cli wp --info` -> success (WP-CLI 2.12.0, PHP 8.2.x)
- `./bin/cli wp core is-installed` -> success
- `./bin/cli wp option get siteurl` -> `https://gregius.local`

Operational note:
- Project wrapper `./bin/cli` is the preferred command runner for WP-CLI in this environment (Docker containerized WordPress runtime).
- No immediate file permission conflict was observed during wrapper execution.

### 8.4 Dynamic auth matrix - RAG endpoints (2026-04-22)

Target site (multisite): `https://gregius.local/wordpress/`

Anonymous HTTP tests (no auth headers):
- `POST /wp-json/gg-data/v1/rag/chat` with `{}` -> `400` missing params (`query`, `connection_name`, `embedding_model_key`, `llm_model_id`)
- `POST /wp-json/gg-data/v1/rag/action` with `{}` -> `400` missing params
- `GET /wp-json/gg-data/v1/rag/actions` -> `400` missing param (`connection_name`)

Interpretation:
- `400` parameter-validation responses confirm requests passed permission callback and reached endpoint argument validation.

WP-CLI runtime permission simulation:
- Command context: `./bin/cli wp --url=https://gregius.local/wordpress/ eval ...`
- Result: `{ "anonymous": {"allowed": true}, "subscriber": {"allowed": true}, "editor": {"allowed": true}, "administrator": {"allowed": true} }`

Config state checks:
- `option get gg_data_rag_access_level` -> option not present
- Effective behavior therefore follows code default (`ACCESS_PUBLIC`).

### 8.5 Dynamic role matrix - core admin endpoints (2026-04-22)

Target site (multisite): `https://gregius.local/wordpress/`

Role-based dispatch via `rest_do_request` for:
- `GET /gg-data/v1/connections`
- `GET /gg-data/v1/sync/status` (`connection=default`)
- `GET /gg-data/v1/vector-queue` (`connection_name=default`)
- `POST /gg-data/v1/rag/chat` (required params provided)

Observed results summary:
- Anonymous / Subscriber / Editor:
	- `connections`, `sync/status`, `vector-queue` denied (`401`/`403` path as expected for admin-only routes)
	- `rag/chat` reached handler path and returned operational error (`500 invalid_security_model`) rather than auth denial
- Administrator:
	- `connections` returned `200`
	- `sync/status` returned operational `400` (`inactive_connection`)
	- `vector-queue` returned operational `500` (database connection failure)
	- `rag/chat` returned operational `500` (`invalid_security_model`)

Sensitive field masking check (admin `GET /connections`):
- Verified non-empty `password`, `publishable_key`, `secret_key` fields are masked as `***` in API response payload.

### 8.6 Dynamic burst probe - anonymous RAG chat (2026-04-22)

Probe:
- 20 sequential anonymous `POST /wp-json/gg-data/v1/rag/chat` requests with required params.

Results:
- Status distribution: `500 x 20`
- Timing: min `0.38s`, avg `0.50s`, max `0.87s`
- Representative error body:
	- `{"code":"invalid_security_model","message":"Security gatekeeper model could not be resolved."...}`

Interpretation:
- Anonymous callers can repeatedly hit non-trivial handler logic.
- No plugin-level throttling/rate-limit behavior was observed in this probe.

### 8.7 Dynamic nonce enforcement matrix - connection mutations (2026-04-22)

Target site (multisite): `https://gregius.local/wordpress/`

Method:
- `./bin/cli wp --url=https://gregius.local/wordpress/ eval ...`
- Requests dispatched via `WP_REST_Request` + `rest_do_request` as administrator.
- Each endpoint tested twice: (a) without `X-WP-Nonce`, (b) with valid `X-WP-Nonce = wp_create_nonce( 'wp_rest' )`.

Results:
- `POST /gg-data/v1/connections`
	- No nonce -> `403` (`invalid_nonce`)
	- Valid nonce -> `201` (request passed nonce gate)
- `POST /gg-data/v1/connections/nonexistent_nonce_audit` (editable route)
	- No nonce -> `403` (`invalid_nonce`)
	- Valid nonce -> `404` (`connection_not_found`)
- `DELETE /gg-data/v1/connections/nonexistent_nonce_audit`
	- No nonce -> `403` (`invalid_nonce`)
	- Valid nonce -> `404` (`connection_not_found`)

Interpretation:
- Nonce enforcement is active and effective on tested state-changing connection routes.
- With valid nonce, requests proceed to normal business-logic outcomes.

### 8.8 Logging/error-path exposure checks - connection flows (2026-04-22)

Runtime state checks:
- `gg_data_debug_mode` option -> truthy
- `gg_data_log_level` -> `info`
- Effective logger state via `GG_Data_Logger`:
	- `debug_enabled = true`
	- `logging_enabled = true`
	- `log_level = info`

Interpretation:
- Debug logging code paths are enabled by environment/options, but debug entries are currently suppressed by minimum log level `info`.

Controlled admin error-path probe:
- Temporary connection saved with marker values:
	- host: `invalid-host-for-audit`
	- username: `audit_user`
	- password: `AUDIT_SECRET_PASSWORD_123`
- Endpoint tested: `POST /gg-data/v1/connections/audit_leak_test_conn/test` with valid nonce.
- Result: status `200`, message indicated failure (`Connection test failed: could not find driver`).
- Response leak markers:
	- password -> false
	- username -> false
	- host -> false
	- DSN marker (`pgsql:host=`) -> false

Static code note:
- `GG_Data_Settings_Manager::save_connection()` writes a debug log message that includes `wp_json_encode( $connection_config )` in the message string.
- `GG_Data_Logger` masks sensitive values in structured context, but not in the freeform `message` field.
- Because current log level is `info`, this message does not appear to be emitted in the current environment; risk becomes active if log level is lowered to `debug`.

### 8.9 Static tooling availability and Plugin Check baseline (2026-04-22)

Environment/tooling observations from container-wrapper execution:
- Active plugin state includes `gregius-data` and `plugin-check` as `active-network`.
- `plugin-check` is installed and callable via `./bin/cli wp ...`.
- Local PHPCS binary not present at plugin path (`vendor/bin/phpcs` missing).
- No local semgrep/eslint/phpcs binaries found in plugin `node_modules/.bin`.
- `rg` (ripgrep) is not available on host shell in this environment; grep fallback was used.

Plugin Check run:
- Command: `./bin/cli wp --url=https://gregius.local/wordpress/ plugin check gregius-data --format=csv > /tmp/gd_plugin_check.csv`
- Output size: `40` lines
- Top findings are packaging/compliance oriented:
	- `build/gregius-data.zip` -> `ERROR compressed_files`
	- `.gitattributes` -> `ERROR hidden_files`
	- multiple `.gitignore` files -> `WARNING hidden_files`
- Grep filtering for security-related tokens (`nonce|permission|direct_file_access|sanitize|escape|sql|prepared|unserialize`) returned no matches in this run.

Interpretation:
- Current Plugin Check output is dominated by distribution packaging hygiene issues, not direct auth/input/SQL findings.
- Security assessment should continue primarily through targeted static code review and dynamic endpoint probing already in progress.

### 8.10 Expanded dynamic permission map - non-RAG surfaces (2026-04-22)

Target site (multisite): `https://gregius.local/wordpress/`

Routes tested via `rest_do_request` as anonymous and administrator:
- `GET /gg-data/v1/settings`
- `GET /gg-data/v1/models`
- `GET /gg-data/v1/logs`
- `GET /gg-data/v1/search/health`

Observed results:
- Anonymous:
	- settings -> `401 rest_forbidden`
	- models -> `403 rest_forbidden`
	- logs -> `403 rest_forbidden`
	- search/health -> `401 rest_forbidden`
- Administrator:
	- all four routes returned `200`

Interpretation:
- Admin-oriented non-RAG surfaces tested in this pass are not publicly reachable.
- This narrows access-control concern scope primarily to RAG endpoint policy defaults (SEC-001).

### 8.11 Post-remediation verification (SEC-001, SEC-005)

Applied remediation set:
- SEC-001: fail-closed defaults and explicit permission semantics in RAG security hooks/controller.
- SEC-005: removed secret-bearing debug log message patterns from connection save flow.

Verification results:
- Syntax checks: updated files pass `php -l`.
- Deterministic role test (`rest_do_request` + direct callback):
	- user `0` (anonymous): permission callback returns `WP_Error` with `401`; REST request returns `401`.
	- user `1` (administrator): permission callback returns `true`; REST request reaches handler path and returns operational `500` (expected in this env due model/config), confirming auth gate passes for admin.
- Secret-log string checks in settings manager:
	- `Saving connection '{...}'` remains as non-secret summary line.
	- `with config:` pattern no longer exists.

Implementation note:
- An initial regression denied all users after fail-closed seed change; this was corrected by removing the early boolean short-circuit in `check_access_permission()` while still honoring `WP_Error` from prior filters.

### 8.12 Post-push smoke matrix (2026-04-22)

Target site (multisite): `https://gregius.local/wordpress/`

Anonymous HTTP checks (no auth headers):
- `POST /wp-json/gg-data/v1/rag/chat` with required params -> `401 Unauthorized`
- `POST /wp-json/gg-data/v1/rag/action` -> `401 Unauthorized`
- `GET /wp-json/gg-data/v1/rag/actions?connection_name=default` -> `401 Unauthorized`
- Body code for all three: `gg_data_login_required`

Authenticated admin runtime checks (WP-CLI `rest_do_request`, user id `1`):
- `POST /gg-data/v1/rag/chat` -> `500` with `invalid_security_model` (operational/config path)
- `POST /gg-data/v1/rag/action` -> `400` with `gg_data_unknown_action` for action `x`
- `GET /gg-data/v1/rag/actions` -> `200` with empty actions list

Interpretation:
- Access-control objective is met post-push: anonymous users are denied on all tested RAG routes.
- Remaining non-2xx admin responses are functional-path outcomes, not authorization bypasses.

### 8.13 SEC-003 throttling verification (2026-04-22)

Implementation summary:
- Added fixed-window transient limiter in `includes/api/class-gg-data-rest-rag-controller.php`.
- Applied at start of `generate_answer` and `execute_action`.
- Defaults: anonymous `10/60s`, authenticated `30/60s`.
- Over-limit response: `WP_Error` code `gg_data_rate_limited`, status `429`, includes `retry_after` metadata.

Runtime verification (authenticated user id `1`, 35 sequential requests to `POST /gg-data/v1/rag/chat`):
- Status distribution: `500 x 30`, `429 x 5`
- First `429` at iteration `31`
- First `429` payload:
	- `code`: `gg_data_rate_limited`
	- `status`: `429`
	- `retry_after`: `60`
	- `scope`: `authenticated`
	- `limit`: `30`
	- `window`: `60`

Interpretation:
- Plugin-level throttling is active and enforces configured defaults for expensive RAG paths.

### 8.14 SEC-003 post-push smoke (2026-04-22)

Commit under test:
- `481d58e94b2be265eb906a7ff0526445a28f28bd`

Authenticated burst checks (user id `1`):
- `POST /gg-data/v1/rag/chat` x35:
	- `CHAT_COUNTS={"429":5,"500":30}`
	- first throttled response at request `31` with code `gg_data_rate_limited`
- `POST /gg-data/v1/rag/action` x35:
	- `ACTION_COUNTS={"400":30,"429":5}`
	- first throttled response at request `31` with code `gg_data_rate_limited`

Anonymous control check:
- `POST /gg-data/v1/rag/chat` (required params present):
	- `ANON_STATUS=401`
	- body code `gg_data_login_required`

Interpretation:
- Post-push runtime confirms endpoint-level throttling is active and consistent across both expensive RAG POST routes.

### 8.15 SEC-004 deserialization hardening verification (2026-04-22)

Implementation summary:
- Removed the second `maybe_unserialize` pass from `GG_Data_Settings_Manager::get_with_category()`.
- `get_with_category()` now performs a single deserialization pass and returns the result directly.

Runtime verification (`./bin/cli wp eval`):
- Standard roundtrip value stored as array returns as array:
	- `ROUNDTRIP_TYPE=array`
	- `ROUNDTRIP_JSON={"ok":true,"n":1}`
- Legacy double-serialized string now remains a string after single-pass decode:
	- `LEGACY_RESULT_TYPE=string`
	- `LEGACY_RESULT=a:1:{s:3:"foo";s:3:"bar";}`

Interpretation:
- Deserialization behavior is now deterministic and single-pass, reducing attack surface and parser complexity in this path.

## 9) Risk Register

| ID | Severity | Component | Summary | Impact | Status |
|---|---|---|---|---|---|
| SEC-001 | Medium (confirmed) | RAG REST auth | RAG chat/action/actions endpoints were publicly reachable by default when access option was unset | Public default removed; post-push smoke tests show anonymous denied | Fixed (pushed 79f98f4) |
| SEC-002 | Info (confirmed) | Credentials handling | Dynamic check confirms admin-only access and response masking for sensitive connection fields | Current residual risk mostly limited to deeper error-path leakage not yet fully exercised | Open (monitor) |
| SEC-003 | Medium (confirmed) | Expensive endpoints | RAG routes now enforce transient-based per-scope limits (`10/60s` anonymous, `30/60s` authenticated) | Abuse amplification risk reduced by plugin-level throttling with 429 back-pressure | Fixed (pushed 481d58e) |
| SEC-004 | Low (confirmed) | Settings deserialization | Removed double `maybe_unserialize` fallback in category-scoped retrieval path | Defense-in-depth deserialization hardening applied with single-pass behavior | Fixed (pushed f4ddde0) |
| SEC-005 | Low (confirmed) | Logging secrets hygiene | Connection save path embedded full config in debug log message before masking | Secret-bearing debug string path removed in code changes | Fixed (pushed 79f98f4) |

## 10) Findings (Current Status)

### SEC-001 (Confirmed)

- Severity: Medium (confirmed)
- Title: Permissive default access path for RAG REST endpoints
- CWE category: CWE-284 Improper Access Control
- Affected files/symbols:
	- `includes/api/class-gg-data-rest-rag-controller.php` -> `get_permission_callback`
	- `includes/hooks/class-gg-data-rag-security-hooks.php` -> `check_access_permission`, `get_access_level`, `register_settings`
- Evidence:
	- Permission callback uses `apply_filters( 'gg_data_rag_endpoint_permission', true, $request )` with allow default.
	- Access level setting defaults to `ACCESS_PUBLIC`.
	- Unknown access level path returns allow (`default: return true`).
- Dynamic validation:
	- Anonymous HTTP requests to `/rag/chat`, `/rag/action`, and `/rag/actions` reached argument validation and returned `400` missing-parameter responses rather than auth denials.
	- WP runtime callback simulation returned `allowed=true` for anonymous, subscriber, editor, and administrator users.
- Impact statement (pre-remediation evidence):
	- Public-by-default endpoint posture was active in the audited pre-fix runtime; if not intentionally required for a site, this is an access-control misconfiguration-by-default risk.
- Current state:
	- Remediated by fail-closed permission evaluation and logged-in default access policy.
- Recommended remediation:
	- Set `rag_access_level` to `logged_in` or `capability` by default, or enforce explicit installation-time choice.
	- Fail closed on unknown access level values.
- Remediation status:
	- Implemented in code, pushed (`79f98f4`), and re-verified in runtime/post-push smoke checks.

### SEC-004 (Confirmed)

- Severity: Low (confirmed)
- Title: Double deserialization recovery path in settings retrieval
- CWE category: CWE-502 Deserialization of Untrusted Data (context-dependent)
- Affected files/symbols:
	- `includes/class-gg-data-settings-manager.php` -> `get_with_category`
- Evidence:
	- Prior behavior performed a second `maybe_unserialize` pass when serialized markers were detected in a post-deserialization string.
	- Current behavior performs a single-pass `maybe_unserialize` and returns the result directly.
- Write-path validation:
	- Connection/settings write entrypoints traced through REST controllers are gated by `permission_callback` checks requiring `current_user_can( 'manage_options' )`.
	- Connection mutation routes (`create/update/delete/test`) also enforce `wp_verify_nonce( ..., 'wp_rest' )`.
	- This significantly reduces practical exploitability from untrusted actors in current architecture.
- Recommended remediation:
	- Remove double-unserialize fallback after one migration cycle, or constrain it behind stricter type/version checks.
- Remediation status:
	- Implemented in code, pushed (`f4ddde0`), and verified with runtime roundtrip checks.

### SEC-003 (Confirmed)

- Severity: Medium (confirmed)
- Title: Missing explicit endpoint throttling controls for high-cost RAG operations (pre-remediation)
- CWE category: CWE-770 Allocation of Resources Without Limits or Throttling
- Affected files/symbols:
	- `includes/api/class-gg-data-rest-rag-controller.php` -> `generate_answer`, `execute_action`
- Evidence:
	- Initial static pass did not identify rate-limit, cooldown, or quota enforcement in controller path.
	- Pre-fix dynamic burst probe (20 anonymous requests) returned `500 x 20` with sub-second response times.
	- Post-fix verification (35 authenticated requests) returned `500 x 30` and `429 x 5`, with first `429` at request `31`.
- Operational context:
	- Infrastructure-level controls (WAF/CDN/reverse proxy) may reduce practical impact; plugin-level controller throttling is now implemented and verified for RAG chat/action routes.
- Current state:
	- Route-scoped transient throttling is active in runtime for `POST /rag/chat` and `POST /rag/action`.
- Recommended remediation:
	- Add endpoint-level throttling (IP/user/token bucket) for RAG routes.
	- Consider early fail-fast auth gates before model/service initialization.
- Remediation status:
	- Implemented in code, pushed (`481d58e`), and verified with post-push runtime burst tests.

### SEC-002 (Confirmed)

- Severity: Info (confirmed)
- Title: Connection endpoints enforce admin access and mask key sensitive fields
- CWE category: N/A (control verification)
- Affected files/symbols:
	- `includes/api/class-gg-data-rest-connections-controller.php` -> `check_admin_permission`, `mask_sensitive_connection_fields`
- Dynamic validation:
	- Non-admin roles denied on `GET /connections`.
	- Admin response returned masked sensitive fields (`***`) for non-empty `password`, `publishable_key`, `secret_key`.
	- Mutation endpoints rejected missing nonce with `403 invalid_nonce`; valid nonce allowed normal route processing.
- Residual risk note:
	- Additional negative-path fuzzing is still recommended to ensure stack traces/internal diagnostics never include credential material.
	- The tested connection failure path did not echo password, username, host, or DSN markers in the REST response.

### SEC-005 (Confirmed)

- Severity: Low (confirmed)
- Title: Connection save debug log message embedded raw config values before masking (pre-remediation)
- CWE category: CWE-532 Insertion of Sensitive Information into Log File
- Affected files/symbols:
	- `includes/class-gg-data-settings-manager.php` -> `save_connection`
	- `includes/class-gg-data-logger.php` -> `log`
- Evidence:
	- `save_connection()` logs `Saving connection '{$connection_name}' with config: ` plus `wp_json_encode( $connection_config )` in the message field.
	- Logger masking is applied to structured context, not to the freeform message string.
	- Current runtime has `debug_enabled=true`, but `log_level=info`, so the leak is latent rather than active at this moment.
- Impact statement:
	- If operators lower log level to `debug`, connection credentials may be persisted to the plugin log table in plaintext within the message field.
- Current state:
	- Secret-bearing freeform message text was removed from connection-save debug logs; logging now uses non-secret summary text with masked structured context.
- Recommended remediation:
	- Remove secrets from log messages entirely.
	- If configuration logging is needed, log only an allowlisted subset of non-sensitive fields or move structured data into masked context.
- Remediation status:
	- Implemented in code and pushed (`79f98f4`); secret-bearing `with config:` message pattern removed.

## 11) Release Security Gate

Release cannot proceed unless:
- No open Critical findings
- No open High findings without explicit risk acceptance
- Every Medium finding has owner + ETA
- Re-test evidence attached for each fixed finding

## 12) Execution Checklist

- [~] Run static toolchain and collect baseline artifacts
- [~] Complete endpoint permission matrix tests (RAG + core non-RAG GET surfaces covered)
- [~] Complete nonce/capability checks for state-changing operations
- [~] Complete sensitive data exposure checks
- [~] Complete abuse-path checks
- [~] Produce final risk register and remediation plan
- [ ] Obtain release sign-off

## 13) Remediation Patch Log

Patch status:
- 13.1, 13.2, 13.3 are applied and pushed (`79f98f4`).
- 13.4 is applied, pushed (`481d58e`), and post-push verified.
- 13.5 is applied, pushed (`f4ddde0`), and verified.

### 13.1 Patch A (Applied) - SEC-001 fail-closed defaults in RAG security hooks

Target file:
- [includes/hooks/class-gg-data-rag-security-hooks.php](includes/hooks/class-gg-data-rag-security-hooks.php)

Implemented edits:
1. Change access-level default in get_access_level from public to logged_in.
2. Change register_setting default for gg_data_rag_access_level from public to logged_in.
3. Change sanitize_access_level fallback from public to logged_in.
4. Change unknown access-level switch default from allow to deny (WP_Error 403).

Applied hunk:

- return apply_filters(
- 	'gg_data_rag_access_level',
- 	$this->settings->get( 'rag_access_level', self::ACCESS_PUBLIC )
- );
+ return apply_filters(
+ 	'gg_data_rag_access_level',
+ 	$this->settings->get( 'rag_access_level', self::ACCESS_LOGGED_IN )
+ );

- 'default'           => self::ACCESS_PUBLIC,
+ 'default'           => self::ACCESS_LOGGED_IN,

- return in_array( $value, $valid_levels, true ) ? $value : self::ACCESS_PUBLIC;
+ return in_array( $value, $valid_levels, true ) ? $value : self::ACCESS_LOGGED_IN;

- default:
- 	// Unknown access level - default to public for safety.
- 	return true;
+ default:
+ 	return new WP_Error(
+ 		'gg_data_invalid_access_level',
+ 		__( 'RAG access policy is misconfigured.', 'gregius-data' ),
+ 		array( 'status' => 403 )
+ 	);

### 13.2 Patch B (Applied) - SEC-001 fail-closed permission callback in REST controller

Target file:
- [includes/api/class-gg-data-rest-rag-controller.php](includes/api/class-gg-data-rest-rag-controller.php)

Implemented edits:
1. Change filter seed in get_permission_callback from true to false.
2. Respect WP_Error returns from filter chain.
3. Allow only explicit true.

Applied hunk:

- $allowed = apply_filters( 'gg_data_rag_endpoint_permission', true, $request );
+ $allowed = apply_filters( 'gg_data_rag_endpoint_permission', false, $request );

+ if ( is_wp_error( $allowed ) ) {
+ 	return $allowed;
+ }

- if ( ! $allowed ) {
+ if ( true !== $allowed ) {
	return new WP_Error(
		'rest_forbidden',
		__( 'You do not have permission to access this endpoint.', 'gregius-data' ),
		array( 'status' => rest_authorization_required_code() )
	);
 }

### 13.3 Patch C (Applied) - SEC-005 remove secret-bearing debug log messages

Target file:
- [includes/class-gg-data-settings-manager.php](includes/class-gg-data-settings-manager.php)

Implemented edits:
1. Replace raw config/value debug messages with masked summaries.
2. Avoid logging per-setting raw values for sensitive keys.
3. Add helper for log-safe masking of config arrays.

Applied hunk:

- $this->logger->log( "GG_Data_Settings_Manager: Saving connection '{$connection_name}' with config: " . wp_json_encode( $connection_config ), 'debug', 'connection', $connection_name );
+ $this->logger->log(
+ 	"GG_Data_Settings_Manager: Saving connection '{$connection_name}'",
+ 	'debug',
+ 	'connection',
+ 	$connection_name,
+ 	array(
+ 		'config_summary' => $this->mask_connection_config_for_log( $connection_config ),
+ 	)
+ );

- $this->logger->log( 'GG_Data_Settings_Manager: Final config after defaults: ' . wp_json_encode( $connection_config ), 'debug', 'connection', $connection_name );
+ $this->logger->log(
+ 	'GG_Data_Settings_Manager: Final connection config prepared',
+ 	'debug',
+ 	'connection',
+ 	$connection_name,
+ 	array(
+ 		'config_summary' => $this->mask_connection_config_for_log( $connection_config ),
+ 	)
+ );

- $this->logger->log( "GG_Data_Settings_Manager: Saving setting '{$key}' = " . wp_json_encode( $value ), 'debug', 'connection', $connection_name );
+ $this->logger->log(
+ 	"GG_Data_Settings_Manager: Saving setting '{$key}'",
+ 	'debug',
+ 	'connection',
+ 	$connection_name,
+ 	array(
+ 		'key' => $key,
+ 	)
+ );

Add helper method (same class):

+ private function mask_connection_config_for_log( $config ) {
+ 	if ( ! is_array( $config ) ) {
+ 		return array();
+ 	}
+
+ 	$masked         = $config;
+ 	$sensitive_keys = array( 'password', 'publishable_key', 'secret_key', 'access_token', 'api_key', 'token' );
+
+ 	foreach ( $sensitive_keys as $key ) {
+ 		if ( isset( $masked[ $key ] ) && '' !== (string) $masked[ $key ] ) {
+ 			$masked[ $key ] = '***';
+ 		}
+ 	}
+
+ 	return $masked;
+ }

### 13.4 Patch D (Applied) - SEC-003 endpoint throttling

Target file:
- [includes/api/class-gg-data-rest-rag-controller.php](includes/api/class-gg-data-rest-rag-controller.php)

Proposed edits:
1. Add lightweight per-IP or per-user transient-based limiter (for example 10 requests/minute for anonymous, 30/minute for authenticated).
2. Call limiter at start of generate_answer and execute_action.
3. Return HTTP 429 with Retry-After metadata when limit exceeded.

Implemented behavior:
- Key format: gg_data_rag_rl_{scope}_{bucket}
- Window: 60 seconds
- Anonymous scope bucket: endpoint + request IP hash
- Authenticated scope bucket: endpoint + user ID
- Response on exceed: WP_Error code gg_data_rate_limited with status 429

### 13.5 Patch E (Applied) - SEC-004 remove double deserialization fallback

Target file:
- [includes/class-gg-data-settings-manager.php](includes/class-gg-data-settings-manager.php)

Implemented edits:
1. Removed legacy second-pass `maybe_unserialize` logic in `get_with_category()`.
2. Standardized `get_with_category()` to a single-pass deserialization return.

Applied hunk:

-		// Unserialize once.
-		$unserialized = maybe_unserialize( $value );
-
-		// Check if result is still a serialized string (double serialization bug).
-		// If it's a string starting with serialization markers, unserialize again.
-		if ( is_string( $unserialized ) && (
-			strpos( $unserialized, 'a:' ) === 0 ||
-			strpos( $unserialized, 's:' ) === 0 ||
-			strpos( $unserialized, 'b:' ) === 0
-		) ) {
-			$unserialized = maybe_unserialize( $unserialized );
-		}
-
-		return $unserialized;
+		// Single-pass deserialization only.
+		return maybe_unserialize( $value );

### 13.6 Verification results (current)

1. Re-run dynamic auth matrix for RAG routes as anonymous/subscriber/editor/admin.
2. Confirm anonymous requests are denied by default when access option is unset.
3. Re-run connection save/test flow and inspect logs for absence of raw secret values.
4. Run burst tests for throttled RAG routes and confirm `429` responses after threshold.
5. Run category-scoped settings roundtrip check and confirm single-pass deserialization behavior in `get_with_category()`.
