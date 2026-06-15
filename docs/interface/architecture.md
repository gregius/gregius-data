# Architecture Description: Interface Subsystem

Standard: ISO/IEC/IEEE 42010:2022
Component: Interface Subsystem
Version: 1.0.0
Date: 2026-04-04
Upstream Specification: [srs.md](srs.md)

---

## 1. Architecture Overview

### 1.1 Purpose and Scope

This architecture describes how the Gregius Data interface layer provides a WordPress-admin dashboard that composes feature pages, integrates with REST services, and manages interface state.

Scope includes:
- dashboard bootstrap and container rendering,
- app shell and tab composition,
- interface state-store integration,
- REST client middleware and client-side API usage,
- interface-level error/loading/fallback behavior,
- extension pattern for new dashboard features.

Explicitly excluded:
- backend endpoint semantics and controller internals,
- WP-CLI command internals,
- lifecycle/update orchestration internals,
- provider/database implementation internals.

### 1.2 Stakeholders and Concerns

| Stakeholder | Concern | Priority |
|---|---|---|
| Site administrators | Reliable admin dashboard operations and clear failure states | High |
| Developers | Predictable UI architecture and integration patterns | High |
| QA/Support | Traceable UI behavior and known-gap visibility | High |
| Platform maintainers | Boundary discipline between interface and backend docs | Medium |

---

## 2. Context View (AV-01)

```
Admin User
   |
   v
WordPress Admin Page (gg-data menu)
   |
   v
React Dashboard Bootstrap (dashboard.js)
   |
   v
App Shell (tabs/pages + stores + utilities)
   |
   +--> apiFetch client calls (gg-data/v1)
   |
   v
Backend REST Controllers (canonical rest-api package)
```

Rationale:
- The interface subsystem should be modeled as a presentation and orchestration client over canonical backend contracts.

Mapping:
- AV-01 -> IF-FR-01..08
- AV-01 -> IF-DR-01..05

---

## 3. Component View (AV-02)

### 3.1 Component Decomposition

1. **Admin Container Layer (`GG_Data_Admin`)**
- Registers admin menu pages.
- Renders `#gg-data-react-dashboard` container within plugin admin page.

2. **Dashboard Bootstrap Layer (`dashboard.js`)**
- Waits for DOM readiness.
- Configures nonce/root URL middleware for `apiFetch`.
- Renders React app and handles fallback container strategy.
- Emits success/failure signals for integration diagnostics.

3. **App Shell Layer (`App.js`)**
- Defines tab configuration and selected tab state.
- Performs startup API connectivity check.
- Injects settings/loading/error context into page components.
- Handles custom navigation events.

4. **Page Layer (`pages/*`)**
- Encapsulates feature-specific UI surfaces for implemented tabs (Models, Connections, Sync, Vectors, Prompts, Search, Logs).

5. **Store Layer (`stores/*`)**
- Registers interface stores and selectors/actions/resolvers for settings/connections/logs/selection state.

6. **Interface Utilities Layer (`utils/api.js` and related utilities)**
- Defines interface API helper paths and standardized error handling helpers.

### 3.2 Responsibility Mapping

| Component | Primary Responsibilities | Requirement Links |
|---|---|---|
| Admin Container | Dashboard host container in admin page | IF-FR-01 |
| Bootstrap | Middleware configuration, render, fallback, failure signaling | IF-FR-02, IF-OR-01, IF-OR-02 |
| App Shell | Tab composition, startup checks, page prop contract | IF-FR-03..05, IF-FR-07, IF-OR-04 |
| Page Layer | Feature-level UI behavior | IF-FR-03, IF-FR-08 |
| Store Layer | Interface shared state contracts | IF-FR-06, IF-DR-02, IF-DR-03 |
| Utility Layer | REST path helpers and interface error formatting | IF-DR-01, IF-QR-04 |

---

## 4. Runtime View (AV-03)

### 4.1 Dashboard Initialization Flow

```
Admin page load
   -> domReady()
   -> configure apiFetch middleware (nonce/root)
   -> locate #gg-data-react-dashboard
   -> render <App />
   -> on render failure: log error + trigger failure signal
```

### 4.2 App Shell Runtime Flow

```
App mount
   -> run API connectivity check
   -> set API status + loading state
   -> subscribe to gg-navigate-to-tab event
   -> render tab panel with configured pages
   -> pass settings/loading/error/apiStatus props to active page
```

### 4.3 Store + API Interaction Flow

```
Page requests state/data
   -> selector read from store
   -> resolver/action performs apiFetch path call
   -> reducer updates loading/data/error state
   -> page rerenders with updated state
```

### 4.4 Fallback and Error Flow

```
Missing primary container
   -> create fallback container under .wrap.gg-data-admin
   -> attempt render
   -> if still failing: emit failure signal and diagnostics
```

Mapping:
- AV-03 -> IF-FR-01..07
- AV-03 -> IF-OR-01..04
- AV-03 -> IF-QR-02, IF-QR-04

---

## 5. Architectural Decisions (ADRs)

### AD-01: React + WordPress Component Baseline

Decision:
- Build the admin interface with React (`@wordpress/element`) and `@wordpress/components`.

Alternatives considered:
- Legacy PHP-rendered admin pages.
- Mixed custom component library approach.

Rationale:
- Aligns with modern WordPress admin UX and component accessibility defaults.

Consequences:
- Build pipeline and JavaScript dependency management are required.

Linked requirements:
- AD-01 -> IF-FR-01, IF-FR-03, IF-QR-01

### AD-02: Store-Integrated Client State Model

Decision:
- Use `@wordpress/data` stores for shared interface state where implemented.

Alternatives considered:
- Local component state only.
- External non-WordPress state libraries.

Rationale:
- Enables predictable selectors/actions and shared tab/page state semantics.

Consequences:
- Store lifecycle and parity must be maintained as feature surface evolves.

Linked requirements:
- AD-02 -> IF-FR-06, IF-DR-02, IF-DR-03, IF-QR-02

### AD-03: API Middleware-First Integration

Decision:
- Configure global nonce/root middleware before dashboard operations.

Alternatives considered:
- Per-request auth/root setup in each component.

Rationale:
- Centralized setup reduces repeated boilerplate and auth drift.

Consequences:
- Bootstrap reliability is critical for all interface data operations.

Linked requirements:
- AD-03 -> IF-FR-02, IF-DR-01, IF-QR-04

### AD-04: Tab-Composed App Shell

Decision:
- Use a centralized tab registry in `App.js` to compose page components.

Alternatives considered:
- Independent page routing per module without shared shell.

Rationale:
- Keeps navigation logic centralized and predictable for maintainers.

Consequences:
- Changes to tab surface require coordinated updates in app shell and page modules.

Linked requirements:
- AD-04 -> IF-FR-03, IF-OR-04, IF-FR-08

### AD-05: Boundary-by-Reference Documentation Strategy

Decision:
- Keep interface docs focused on UI/client integration and reference canonical backend subsystem docs for API, CLI, updates, lifecycle, and providers.

Alternatives considered:
- Duplicating backend contracts in interface docs.

Rationale:
- Reduces drift and conflicting contract statements across subsystems.

Consequences:
- Interface docs require maintained cross-links to backend canonical packages.

Linked requirements:
- AD-05 -> IF-QR-03, IF-OR-05

---

## 6. Constraints and Risks

### 6.1 Constraints

| ID | Constraint | Rationale |
|---|---|---|
| C-01 | Dashboard render depends on admin container output by `GG_Data_Admin`. | Missing container requires fallback path. |
| C-02 | Interface API workflows depend on initialized middleware and REST route availability. | Initialization order affects usability. |
| C-03 | Current tab surface includes implemented pages only; some backend features have no dedicated UI page yet. | Prevents false assumption of complete UI parity. |

### 6.2 Risks

| ID | Risk | Impact | Mitigation |
|---|---|---|---|
| R-01 | Stale monolith guidance can imply implemented UI features that do not exist. | Medium | Maintain explicit deferred capability notes in canonical docs. |
| R-02 | Store coverage drift across feature areas can create inconsistent integration patterns. | Medium | Keep developer docs explicit about current stores and extension patterns. |
| R-03 | Initialization or middleware regressions can fail all dashboard API interactions. | High | Preserve startup checks, error notices, and fallback diagnostics. |

---

## 7. Architecture Traceability

| Architecture Item | Requirement IDs Covered |
|---|---|
| AV-01 | IF-FR-01..08, IF-DR-01..05 |
| AV-02 | IF-FR-01..08, IF-DR-01..05, IF-OR-01..05 |
| AV-03 | IF-FR-01..07, IF-OR-01..04, IF-QR-02, IF-QR-04 |
| AD-01 | IF-FR-01, IF-FR-03, IF-QR-01 |
| AD-02 | IF-FR-06, IF-DR-02, IF-DR-03, IF-QR-02 |
| AD-03 | IF-FR-02, IF-DR-01, IF-QR-04 |
| AD-04 | IF-FR-03, IF-OR-04, IF-FR-08 |
| AD-05 | IF-QR-03, IF-OR-05 |

---

## 8. Readiness Checklist

- Scope and exclusions are explicit.
- Dashboard bootstrap, shell, pages, and stores are represented.
- Runtime flows include initialization, interaction, and fallback/error behavior.
- Architecture decisions include alternatives and consequences.
- Requirement-linked traceability is present.
- Companion docs are available:
  - [srs.md](srs.md)
  - [developer-documentation.md](developer-documentation.md)
