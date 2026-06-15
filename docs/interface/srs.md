# Software Requirements Specification (SRS)

Standard: ISO/IEC/IEEE 29148:2018

## Document Information

| Field | Value |
|---|---|
| Project | Gregius Data |
| Software Component | Interface Subsystem |
| Version | 1.0 |
| Date | 2026-04-04 |
| Author | Gregius |
| StRS Reference | N/A - SRS produced in SRS-first mode for an existing subsystem |
| SyRS Reference | N/A - no standalone system-level interface package |
| Status | Draft |

## 1. Introduction

### 1.1 System Purpose

This SRS defines what the Gregius Data interface subsystem must do to provide a WordPress-admin dashboard experience that integrates safely and consistently with backend services.

### 1.2 System Scope

The interface subsystem includes:
- React dashboard bootstrap and rendering in the WordPress admin page container,
- tabbed interface composition for implemented feature pages,
- interface-level state management with `@wordpress/data` stores,
- REST client integration through `@wordpress/api-fetch` middleware,
- loading, error, and connection-status UI behavior,
- feature-integration patterns for adding new interface surfaces.

Software identifier: gregius-data-interface

Repository path: `public/wp-content/plugins/gregius-data`

### 1.3 System Overview

#### 1.3.1 System Context

The interface subsystem is the presentation layer for plugin administration. It orchestrates user interactions in wp-admin and delegates data operations to backend REST controllers and subsystem services.

#### 1.3.2 System Functions Summary

- Render a React app in `#gg-data-react-dashboard` on the plugin admin page.
- Configure API middleware for nonce and root URL handling.
- Provide tab/page navigation for implemented dashboard sections.
- Use stores/selectors/actions for shared interface state where implemented.
- Surface loading and operational error states consistently.
- Provide predictable integration steps for new dashboard features.

#### 1.3.3 User Characteristics

| User Class | Technical Level | Primary Interaction Point |
|---|---|---|
| Site Administrator | Technical/Operational | React dashboard in WordPress admin |
| Plugin Developer | Technical | Dashboard components, stores, and API integration patterns |
| QA/Support | Technical | Interface behavior checks and troubleshooting flows |

## 2. References

- `docs/interface/architecture.md`
- `docs/interface/developer-documentation.md`
- `assets/src/scripts/dashboard.js`
- `assets/src/scripts/dashboard/components/App.js`
- `assets/src/scripts/dashboard/pages/`
- `assets/src/scripts/dashboard/stores/`
- `includes/class-gg-data-admin.php`
- `includes/api/class-gg-data-rest-api.php`
- `docs/rest-api/architecture.md`
- `docs/wpcli/architecture.md`

## 3. Software Requirements

Requirement notation:
- MUST: mandatory
- SHOULD: strongly recommended
- MAY: optional

### 3.1 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| IF-FR-01 | The software MUST render the dashboard React application in the admin container `#gg-data-react-dashboard` on the plugin settings page. | Must |
| IF-FR-02 | The software MUST configure REST client middleware for nonce and root URL when WordPress API settings are available. | Must |
| IF-FR-03 | The dashboard MUST provide implemented tabs for Models, Connections, Sync, Vectors, Prompts, Search, and Logs. | Must |
| IF-FR-04 | The dashboard MUST perform startup API connectivity checks and surface connection failures to users. | Must |
| IF-FR-05 | The dashboard MUST expose loading states while initialization and settings fetch operations are in progress. | Must |
| IF-FR-06 | The interface MUST integrate with existing `@wordpress/data` stores for settings and shared connection-related state where implemented. | Must |
| IF-FR-07 | The interface MUST support event-driven tab navigation via custom navigation events used by dashboard components. | Must |
| IF-FR-08 | The interface MUST support feature extension through documented tab/page/store + REST integration patterns. | Must |

### 3.2 Data and Contract Requirements

| ID | Requirement | Priority |
|---|---|---|
| IF-DR-01 | Dashboard API calls MUST target the `gg-data/v1` REST namespace through `apiFetch` path contracts. | Must |
| IF-DR-02 | Interface store contracts MUST expose selectors for settings, loading status, and error states for settings workflows. | Must |
| IF-DR-03 | Connections store contracts MUST support loading/error and CRUD state transitions for connection records. | Must |
| IF-DR-04 | Interface components MUST consume settings/loading/error props passed from the app shell to feature pages. | Must |
| IF-DR-05 | Dashboard bootstrap MUST expose global dashboard config values required by fallback middleware and integrations. | Must |

### 3.3 Software Operations and Control Requirements

| ID | Requirement | Priority |
|---|---|---|
| IF-OR-01 | If the primary dashboard container is absent, the interface SHOULD create a fallback container under `.wrap.gg-data-admin` and attempt render. | Should |
| IF-OR-02 | Interface initialization failures MUST be caught and surfaced through console output and failure signaling hooks. | Must |
| IF-OR-03 | REST operations initiated by the interface MUST rely on backend permission enforcement and present returned permission errors to users. | Must |
| IF-OR-04 | Dashboard tab definitions MUST map deterministically to page components and remain selectable through `TabPanel`. | Must |
| IF-OR-05 | Interface documentation MUST distinguish implemented dashboard capabilities from deferred interface features to prevent parity drift. | Must |

### 3.4 Quality and Non-Functional Requirements

| ID | Requirement | Verification Method |
|---|---|---|
| IF-QR-01 | The interface SHOULD use `@wordpress/components` for consistent accessibility and admin UX behavior. | Component usage inspection |
| IF-QR-02 | Interface interactions SHOULD remain responsive by using store-based state updates and scoped page rendering. | Runtime UX inspection |
| IF-QR-03 | Interface contracts SHOULD avoid duplicating backend subsystem semantics and instead link to canonical subsystem docs. | Documentation boundary review |
| IF-QR-04 | Initialization and API error paths MUST provide actionable user/developer feedback. | Error-path inspection |
| IF-QR-05 | Feature-integration guidance MUST remain aligned with implemented dashboard architecture patterns. | Documentation parity review |

## 4. Verification and Acceptance

| Requirement Group | Verification Method | Tool or Evidence |
|---|---|---|
| Functional requirements | Dashboard load + tab behavior checks | Admin UI runtime checks |
| Data/contract requirements | Store and API path inspections | Source review + UI call inspection |
| Operations requirements | Failure/fallback behavior checks | Initialization error simulation |
| Quality requirements | Component/pattern/document boundary checks | Code and docs parity review |

Acceptance baseline:
- React dashboard boots reliably in admin.
- Implemented tabs and page integration behave as documented.
- Store and API integration contracts match source implementation.
- Documentation reflects current interface capabilities without backend contract duplication.

## 5. Traceability

| SRS Requirement IDs | Source References | Notes |
|---|---|---|
| IF-FR-01, IF-FR-02, IF-OR-01, IF-OR-02, IF-DR-05 | `assets/src/scripts/dashboard.js`, `includes/class-gg-data-admin.php` | Dashboard bootstrap, middleware, fallback container, failure signaling |
| IF-FR-03, IF-FR-04, IF-FR-05, IF-FR-07, IF-OR-04, IF-DR-04 | `assets/src/scripts/dashboard/components/App.js` | Tab shell, initialization check, event-driven navigation, page props |
| IF-FR-06, IF-DR-02, IF-DR-03 | `assets/src/scripts/dashboard/stores/settings/index.js`, `assets/src/scripts/dashboard/stores/connections/index.js`, `assets/src/scripts/dashboard/stores/` | Store registration and state contracts |
| IF-DR-01, IF-OR-03, IF-QR-04 | `assets/src/scripts/dashboard/utils/api.js`, `includes/api/class-gg-data-rest-api.php` | REST namespace usage and interface/backend integration |
| IF-FR-08, IF-OR-05, IF-QR-03, IF-QR-05 | `assets/src/scripts/dashboard/components/App.js`, `assets/src/scripts/dashboard/stores/`, `docs/interface/developer-documentation.md` | Feature integration guidance and scope-discipline expectations |

## 6. Appendices

### 6.1 Assumptions and Dependencies

- This SRS is generated in SRS-first default mode without separate BRS, StRS, OpsCon, or SyRS artifacts.
- Backend route contracts and command contracts are maintained by their canonical subsystem packages.
- Interface behavior assumes plugin assets are enqueued and REST routes are initialized.

### 6.2 Open Issues

| ID | Section | Issue | Owner | Target Date |
|---|---|---|---|---|
| IF-TBD-01 | 3.1 | Interactions backend APIs exist but no dedicated dashboard page/store is implemented in current tab surface. | Engineering | TBD |
| IF-TBD-02 | 3.4 | Monolith testing guidance references planned Jest coverage that is not yet represented as established baseline. | Engineering | TBD |

### 6.3 Decisions Log

| Date | Decision | Rationale | Decided By |
|---|---|---|---|
| 2026-04-04 | Split interface docs into canonical SRS/architecture/developer package | Align with plugin-wide canonical ISO structure and reduce monolith drift/duplication | Documentation migration initiative |
