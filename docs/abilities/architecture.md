# Architecture Description: Abilities Subsystem

**Standard:** ISO/IEC/IEEE 42010:2022  
**Component:** Abilities API Integration  
**Version:** 1.0.0  
**Date:** 2026-04-04  
**Upstream Specification:** [Software Requirements Specification](srs.md)

---

## 1. Architecture Overview

### 1.1 Purpose and Scope

This architecture describes how Gregius Data exposes plugin capabilities through WordPress Abilities API registration and callback execution.

**Scope:**
- Abilities registration lifecycle and API-availability guards
- Category and ability contract definitions
- Permission boundaries for ability execution
- Runtime callback delegation to RAG, connections, and model services
- Metadata exposure for REST and MCP-facing clients

**Explicitly Excluded:**
- Internal implementation details of RAG, settings, or model services
- MCP adapter runtime implementation details outside plugin boundaries

### 1.2 Stakeholders and Concerns

| Stakeholder | Concern | Priority |
|---|---|---|
| AI Tooling Clients | Discoverable and stable ability contracts | High |
| Site Administrators | Secure control over operational abilities | High |
| Authenticated Users | Safe use of answer capability | High |
| Plugin Developers | Predictable registration and callback behavior | High |

---

## 2. Context View (AV-01)

### 2.1 Context

```
WordPress Runtime
   |
   +--> wp_abilities_api_categories_init ---> register_categories()
   |
   +--> wp_abilities_api_init -------------> register_abilities()
                                              |
                                              v
                         Registered Abilities in WP Abilities Registry
                             - gregius-data/answer
                             - gregius-data/list-connections
                             - gregius-data/list-models
                                              |
                                              v
                          Clients via Abilities REST / MCP adapters
                                              |
                                              v
                           Execute callbacks in GG_Data_Abilities_Manager
                                              |
                   +--------------------------+-------------------------+
                   |                          |                         |
                   v                          v                         v
            RAG Service                Settings Manager            Model Registry
```

### 2.2 Context Rationale

The subsystem decouples discovery contracts from implementation services by using a thin ability-manager boundary. This keeps ability schemas and permissions explicit while delegating domain work to established plugin components.

**Mapping to Requirements:**
- AV-01 -> ABIL-FR-01..10
- AV-01 -> ABIL-OR-01..04

---

## 3. Component View (AV-02)

### 3.1 Major Components

1. **Abilities Manager (`GG_Data_Abilities_Manager`)**
- Registers categories and abilities via Abilities hooks
- Defines schemas, metadata, and permission callbacks
- Executes ability callbacks and input normalization

2. **Ability Registry Contracts**
- `ai` category
- `gregius-data/answer`
- `gregius-data/list-connections`
- `gregius-data/list-models`

3. **Delegated Service Adapters**
- RAG service for answer execution
- Settings manager for connection enumeration
- Model registry for model inventory and filtering

### 3.2 Responsibility Mapping

| Component | Responsibilities | Requirement Links |
|---|---|---|
| Abilities Manager | Lifecycle registration, schema/meta contracts, permission checks, callback orchestration | ABIL-FR-01..10, ABIL-DR-01..06, ABIL-OR-01..04 |
| Registry Contracts | Stable IDs/categories and discoverable contracts | ABIL-FR-03..07, ABIL-DR-01..05, ABIL-QR-02 |
| Delegated Services | Domain execution and normalized ability outputs | ABIL-FR-08..10, ABIL-QR-03 |

---

## 4. Runtime View (AV-03)

### 4.1 Registration Flow

```
Plugin init -> GG_Data_Abilities_Manager::init()
   |
   +--> hook categories callback on wp_abilities_api_categories_init
   +--> hook abilities callback on wp_abilities_api_init

categories hook fires
   -> if wp_register_ability_category exists
   -> register ai and gregius-data categories

abilities hook fires
   -> if wp_register_ability exists
   -> register answer/list-connections/list-models with contracts
```

### 4.2 Execution Flow: `gregius-data/answer`

```
Ability call with args
   -> normalize required query/connection/model inputs plus optional orchestration inputs
   -> validate required query and model argument combinations
   -> resolve system/security prompts internally through prompt subsystem fallback
   -> instantiate RAG service
   -> map ability args to internal options
   -> invoke generate_answer(query, answer_model, options)
   -> return result or WP_Error
```

### 4.3 Execution Flow: operational abilities

```
list-connections
   -> instantiate settings manager
   -> fetch all connections
   -> normalize output fields

list-models
   -> instantiate model registry
   -> accept optional type filter only (no connection argument)
   -> map optional type filter
   -> fetch models from global/provider registry scope
   -> normalize output fields
```

**Mapping to Requirements:**
- AV-03 -> ABIL-FR-01..10
- AV-03 -> ABIL-DR-04..06

---

## 5. Architectural Decisions

### AD-01: Hook-Aligned Registration Lifecycle

**Decision:** Register categories and abilities on their dedicated Abilities API hooks instead of eager registration.

**Alternatives Considered:**
- Register at generic plugin init hook
- Conditional late registration in request handlers

**Rationale:**
- Aligns with Abilities API contract expectations.
- Avoids race conditions with registry initialization timing.

**Consequences:**
- Correct behavior depends on Abilities hook availability.

**Requirement Links:** ABIL-FR-01, ABIL-FR-02, ABIL-OR-01

### AD-02: Graceful Degradation on API Unavailability

**Decision:** No-op registration when Abilities API functions are missing.

**Alternatives Considered:**
- Fatal errors when API missing
- Soft warnings with partial registration attempts

**Rationale:**
- Preserves plugin operability on environments without Abilities API.

**Consequences:**
- Ability discovery is absent when API is unavailable.

**Requirement Links:** ABIL-OR-01

### AD-03: Stable Namespaced Ability IDs and Categories

**Decision:** Use stable, namespaced IDs and two fixed categories.

**Alternatives Considered:**
- Dynamic IDs by connection/environment
- Single catch-all category

**Rationale:**
- Supports durable client integrations.
- Keeps discovery and grouping predictable.

**Consequences:**
- Future renames risk compatibility unless versioned/migrated.

**Requirement Links:** ABIL-FR-03..07, ABIL-OR-04, ABIL-QR-02

### AD-04: Capability-Separated Permission Model

**Decision:** `answer` uses `read`; operational discovery abilities require `manage_options`.

**Alternatives Considered:**
- All abilities admin-only
- All abilities available to all logged-in users

**Rationale:**
- Balances utility for authenticated users with admin control for operational internals.

**Consequences:**
- Client expectations must account for role-dependent visibility/execution.

**Requirement Links:** ABIL-OR-02, ABIL-OR-03

### AD-05: Thin Adapter Delegation to Existing Services

**Decision:** Ability callbacks orchestrate inputs/outputs and delegate domain work to existing plugin services.

**Alternatives Considered:**
- Reimplement domain logic inside ability manager
- Separate standalone services only for abilities

**Rationale:**
- Avoids duplicated logic and keeps ability manager focused on contracts.

**Consequences:**
- Upstream service changes can impact ability outputs unless contracts are guarded.

**Requirement Links:** ABIL-FR-08..10, ABIL-QR-03

---

## 6. Constraints and Risks

### 6.1 Constraints

| ID | Constraint | Rationale |
|---|---|---|
| C-01 | Registration depends on Abilities API function availability. | Required for cross-version resilience. |
| C-02 | Ability IDs and categories are public contract surfaces. | External clients bind to these identifiers. |
| C-03 | Permission callbacks enforce capability-based execution boundaries. | Security and operational control requirement. |

### 6.2 Risks

| ID | Risk | Impact | Mitigation |
|---|---|---|---|
| R-01 | Contract drift between schemas and delegated service outputs. | Medium | Maintain SRS traceability and developer doc parity checks. |
| R-02 | Ability metadata under-specification for deeper clients. | Medium | Expand output-schema depth in controlled revisions. |
| R-03 | Future ID/category renames breaking clients. | High | Treat IDs/categories as stable and version changes deliberately. |

---

## 7. Architecture Traceability

| Architecture Item | Requirement IDs Covered |
|---|---|
| AV-01 | ABIL-FR-01..10, ABIL-OR-01..04 |
| AV-02 | ABIL-FR-03..10, ABIL-DR-01..06, ABIL-OR-01..04 |
| AV-03 | ABIL-FR-01..10, ABIL-DR-04..06 |
| AD-01 | ABIL-FR-01, ABIL-FR-02, ABIL-OR-01 |
| AD-02 | ABIL-OR-01 |
| AD-03 | ABIL-FR-03..07, ABIL-OR-04, ABIL-QR-02 |
| AD-04 | ABIL-OR-02, ABIL-OR-03 |
| AD-05 | ABIL-FR-08..10, ABIL-QR-03 |

---

## 8. Readiness Checklist

- Scope and exclusions are explicit.
- Registration and execution flows are documented.
- Ability contract and permission boundaries are captured.
- Decisions include rationale and consequences.
- Coverage mapping to SRS requirements is complete.
- Companion docs are available:
  - [srs.md](srs.md)
  - [developer-documentation.md](developer-documentation.md)
