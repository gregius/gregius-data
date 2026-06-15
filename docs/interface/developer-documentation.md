# Interface Developer Documentation

Standard: ISO/IEC/IEEE 26514:2022

## 1. Purpose and Audience

This document describes how developers maintain and extend the Gregius Data interface subsystem.

Audience:
- Plugin contributors implementing dashboard features
- Integrators wiring interface behavior to existing backend routes
- QA/support engineers validating interface workflows

Companion documents:
- Requirements: [srs.md](srs.md)
- Architecture: [architecture.md](architecture.md)

## 2. Scope

Covered:
- Dashboard bootstrap and admin container contracts
- App shell tab/page composition
- Implemented store patterns and API integration
- Feature integration workflow for new interface tabs/pages
- Troubleshooting startup and runtime UI/API failures

Not covered:
- REST endpoint semantics (see `docs/rest-api/`)
- WP-CLI command semantics (see `docs/wpcli/`)
- Update/lifecycle internals (see `docs/updates/`, `docs/lifecycle/`)

## 3. Quick Start

### 3.1 Run the Dashboard in Development

```bash
npm install
npm run start
```

### 3.2 Build Production Assets

```bash
npm run build
```

### 3.3 Verify Dashboard Mount

1. Open plugin admin page.
2. Confirm `#gg-data-react-dashboard` exists in rendered HTML.
3. Confirm app shell loads tabs and no fatal JS errors occur.

[SRS: IF-FR-01, IF-FR-03]

## 4. Core Interface Contracts

### 4.1 Admin Container

Source: `includes/class-gg-data-admin.php`

Contract:
- `render_settings_page()` must output `#gg-data-react-dashboard` within `.wrap.gg-data-admin`.

[SRS: IF-FR-01]

### 4.2 Dashboard Bootstrap

Source: `assets/src/scripts/dashboard.js`

Contract:
- Wait for `domReady()`.
- Configure `apiFetch` nonce/root middleware.
- Render `<App />` into primary container.
- Fallback: create container under `.wrap.gg-data-admin` when needed.
- Emit failure diagnostics on render exceptions.

[SRS: IF-FR-02, IF-OR-01, IF-OR-02]

### 4.3 App Shell and Tabs

Source: `assets/src/scripts/dashboard/components/App.js`

Implemented tab registry:
- `models`
- `connections`
- `sync`
- `vectors`
- `prompts`
- `search`
- `logs`

Runtime contract:
- Startup API connectivity check via `checkApiConnection()`.
- Loading/error states before normal tab rendering.
- Custom `gg-navigate-to-tab` event handling for cross-component navigation.
- Active page receives `settings`, `isLoading`, `error`, and `apiStatus` props.

[SRS: IF-FR-03, IF-FR-04, IF-FR-05, IF-FR-07, IF-DR-04]

### 4.4 Stores and Shared State

Sources:
- `assets/src/scripts/dashboard/stores/settings/index.js`
- `assets/src/scripts/dashboard/stores/connections/index.js`
- `assets/src/scripts/dashboard/stores/`

Implemented stores:
- `gg-data/settings`
- `gg-data/connections`
- `gg-data/logs`
- `gg-data/selectedConnection`
- `gg-data/searchConnection`
- `gg-data/connectionSelection`

Store pattern expectations:
- Register store via `registerStore`.
- Provide selectors/actions and reducers for loading/data/error state.
- Use controls for `apiFetch` integration when action generators require it.

[SRS: IF-FR-06, IF-DR-02, IF-DR-03]

### 4.5 Interface API Utility Layer

Source: `assets/src/scripts/dashboard/utils/api.js`

Contract:
- Base API path uses `/gg-data/v1`.
- Utility wrappers call `apiFetch` with consistent path composition.
- `checkApiConnection()` uses `/wp/v2/users/me` to validate runtime API access.
- `handleAPIError()` normalizes interface error objects.

[SRS: IF-DR-01, IF-QR-04]

## 5. Feature Integration Workflow

Use this sequence for adding a new dashboard feature without breaking architecture consistency:

1. Add or confirm backend route/controller in canonical backend subsystem.
2. Add or extend an interface store if shared state is needed.
3. Create a page component under `assets/src/scripts/dashboard/pages/`.
4. Register tab in `App.js` and map to page component.
5. Pass/consume shell props consistently (`settings`, loading, errors, `apiStatus`).
6. Validate error/loading behavior and event-driven navigation impacts.

Guideline:
- Keep interface docs focused on how UI integrates with backend contracts.
- Link to backend canonical docs instead of duplicating endpoint details.

[SRS: IF-FR-08, IF-QR-03, IF-QR-05]

## 6. Accessibility and UX Baseline

Current implementation direction:
- `@wordpress/components` is the primary component source.
- Tabbed admin workflows follow WordPress admin interaction patterns.
- Loading and notice states provide visible user feedback.

Recommended verification checks:
1. Keyboard navigation across tabs and controls.
2. Notice visibility for error states.
3. No hidden hard failures on initial render.

[SRS: IF-QR-01, IF-QR-04]

## 7. Known Gaps and Deferred Areas

Documented deferred/non-parity areas:
- Interactions backend has canonical subsystem docs and route support, but no dedicated dashboard tab/page/store in current interface surface.
- Monolith-era testing guidance references planned Jest coverage; treat as roadmap until test baseline is established.

Do not represent deferred items as implemented behavior in interface docs.

[SRS: IF-OR-05]

## 8. Troubleshooting

### Dashboard does not render

Check:
1. `#gg-data-react-dashboard` container exists.
2. Assets loaded successfully in admin page.
3. Browser console for render exceptions.

### API requests fail immediately

Check:
1. Nonce/root middleware initialization in `dashboard.js`.
2. User authentication/session status.
3. REST route availability under `/wp-json/gg-data/v1/`.

### Tab content not switching

Check:
1. Tab registry entry in `App.js` has unique `name`.
2. Component import path is valid.
3. Custom event payload for `gg-navigate-to-tab` includes valid tab key.

### Store selectors return empty state unexpectedly

Check:
1. Store import side effects are loaded before `useSelect` calls.
2. Resolver/action path is correct for target endpoint.
3. Reducer action names align with dispatched action types.

## 9. Parity Coverage Snapshot

Documented and implementation-backed:
- Dashboard admin container + bootstrap behavior
- Middleware setup and startup API connection checks
- Current tab/page surface and shell prop passing
- Current store registrations and shared-state patterns
- Feature integration workflow aligned to existing architecture
