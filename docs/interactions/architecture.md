# Architecture Description: Interaction Subsystem

**Standard:** ISO/IEC/IEEE 42010:2022  
**Component:** Interaction Tracking  
**Version:** 1.0.0  
**Date:** 2026-04-04  
**Upstream Specification:** [Software Requirements Specification](srs.md)

---

## 1. Architecture Overview

### 1.1 Purpose and Scope

This architecture defines how Gregius Data records search and RAG runtime interactions as durable, queryable records while preserving permission-aware access and extension points for integrators.

**Scope:**
- Interaction event capture from RAG and Search subsystems
- Interaction persistence model (`gg_interaction` CPT + structured meta/json payloads)
- Author-aware REST access model for interaction records
- Runtime recording flow (create vs append by conversation UUID)
- Interaction-related extension surface (filters/actions)
- Sync participation boundaries for interaction records

**Explicitly Excluded:**
- Detailed logging subsystem internals
- End-user UX for interaction exploration
- Benchmark/reporting analytics logic

### 1.2 Stakeholders and Concerns

| Stakeholder | Concern | Priority |
|---|---|---|
| Plugin Developers | Stable recording and extension points without coupling to internal storage details | High |
| Site Administrators | Full visibility and control over interaction records | High |
| Authenticated Site Users | Safe access to own interaction history without overexposure | High |
| Operations | Inspectable runtime traces for RAG/search troubleshooting | Medium |
| Security Reviewers | Correct least-privilege enforcement in REST access | High |

---

## 2. Context View (AV-01)

### 2.1 Context

```
RAG Service (gg_data_rag_complete) ─┐
                                    │
Search Integration (gg_data_search_completed) ─┐
                                                ▼
                                  Interaction Subsystem
                       (GG_Data_Interaction + REST Controller)
                                      │          │
                                      │          ├─ REST API: /wp-json/gg-data/v1/interactions
                                      │          │
                                      ▼          ▼
                         WordPress CPT Storage   Operational Logs
                           (gg_interaction)      (context snapshots)
                                      │
                                      ▼
                          Sync Control Filter Boundary
                      (excluded from real-time sync path)
```

### 2.2 Context Rationale

The subsystem is event-driven at ingress and REST-driven at egress. This separation allows low-friction recording from runtime flows while preserving explicit API contracts for retrieval and management.

**Mapping to Requirements:**
- AV-01 -> INT-FR-02, INT-FR-03, INT-FR-07, INT-FR-12
- AV-01 -> INT-OR-01, INT-OR-05

---

## 3. Component View (AV-02)

### 3.1 Major Components

1. **Interaction Orchestrator (`GG_Data_Interaction`)**
- Registers post type and meta fields.
- Subscribes to RAG/search lifecycle hooks.
- Persists interaction records and emits extension hooks.
- Applies sync exclusion behavior.

2. **REST Access Layer (`GG_Data_REST_Interactions_Controller`)**
- Provides `gg-data/v1/interactions` endpoint family.
- Enforces authentication and ownership-based access.
- Restricts non-admin direct create; interaction creation is chat-lifecycle driven.
- Applies admin override for full visibility and explicit multisite governance context for super admins.

3. **Persistence Model (`gg_interaction` + `_gg_interaction_*`)**
- Stores normalized typed meta fields.
- Stores rich per-record JSON payload for turn-level and context-level data.
- Maintains markdown content for human-readable conversation progression.

4. **Extension Surface**
- `gg_data_interaction_meta_fields`
- `gg_data_interaction_log_context`
- `gg_data_interaction_recorded`
- `gg_data_should_sync_post` integration boundary

### 3.2 Responsibilities Mapping

| Component | Primary Responsibilities | Requirement Links |
|---|---|---|
| Interaction Orchestrator | Event handling, record create/append, lock handling, sync exclusion | INT-FR-01..06, INT-FR-12, INT-OR-01..03 |
| REST Access Layer | Auth checks, ownership checks, constrained create policy, multisite admin governance behavior | INT-FR-07..10, INT-DR-07, INT-QR-02 |
| Persistence Model | Meta + JSON contract storage and conversation continuity | INT-FR-04..06, INT-DR-01..05, INT-QR-01 |
| Extension Surface | Integrator customization and post-record notifications | INT-FR-11, INT-OR-05 |

---

## 4. Runtime Interaction View (AV-03)

### 4.1 RAG Recording Flow

```
RAG completes -> gg_data_rag_complete
      |
      v
on_rag_complete()
  - ensure conversation_id exists
  - build normalized args payload
      |
      v
record_rag(conversation_id, args)
  - validate UUID
  - acquire conversation lock
  - lookup existing conversation by UUID + type=rag
      |
      +--> found: append_rag_turn()
      |
      +--> not found: create_rag_conversation()
      |
      v
persist meta + JSON + markdown content
      |
      v
emit log + do_action(gg_data_interaction_recorded)
```

### 4.2 Search Recording Flow

```
Search completes -> gg_data_search_completed
      |
      v
on_search_completed()
  - ensure connection exists
      |
      v
record_search(args)
  - create one interaction post
  - store typed meta + JSON payload
      |
      v
emit log + do_action(gg_data_interaction_recorded)
```

### 4.3 REST Access Flow

```
REST request -> permission check
      |
      +--> unauthenticated: deny
      |
      +--> admin: allow all
      |        - super admin may supply explicit site context (`site_id`) in multisite
      |
      +--> logged-in non-admin:
             - list scoped to current author
             - direct create denied (use chat lifecycle)
             - item read/update/delete only if owns record
```

**Mapping to Requirements:**
- AV-03 -> INT-FR-02..10, INT-OR-02..03
- AV-03 -> INT-DR-04, INT-DR-07, INT-QR-01..04

---

## 5. Architectural Decisions

### AD-01: Event-Driven Ingestion from RAG and Search Hooks

**Decision:** Record interactions by subscribing to existing lifecycle hooks rather than introducing a separate capture API.

**Alternatives Considered:**
- Dedicated synchronous interaction write calls in each subsystem
- Queue-based event bus for deferred recording

**Rationale:**
- Reuses existing subsystem lifecycle boundaries.
- Keeps call sites simple and avoids duplicated recording logic.
- Aligns with plugin-wide hook extension strategy.

**Consequences:**
- Recording depends on emission integrity of upstream hooks.
- Hook contracts become part of interaction subsystem dependencies.

**Requirement Links:** INT-FR-02, INT-FR-03, INT-OR-05

### AD-02: Hybrid Interaction Persistence (Typed Meta + Canonical JSON)

**Decision:** Persist both typed meta fields and richer JSON payloads for each interaction.

**Alternatives Considered:**
- JSON-only storage in one field
- Meta-only storage with no canonical payload document

**Rationale:**
- Typed meta supports straightforward filtering and display.
- JSON payload preserves detailed context and turn-level structures.
- Supports operational troubleshooting without schema churn for every detail field.

**Consequences:**
- Requires discipline to keep meta and JSON semantically aligned.
- Adds modest duplication by design for interoperability.

**Requirement Links:** INT-FR-04, INT-DR-01..05, INT-QR-03

### AD-03: Conversation-Centric RAG Storage with Append Semantics

**Decision:** Group RAG turns by UUID and append turns to a single conversation record.

**Alternatives Considered:**
- One post per RAG turn
- One post per request with no cross-turn grouping

**Rationale:**
- Preserves conversational continuity and reduces fragmentation.
- Simplifies human inspection and historical context retrieval.

**Consequences:**
- Requires UUID validation and lookup logic.
- Needs lock coordination for concurrent turn writes.

**Requirement Links:** INT-FR-05, INT-FR-06, INT-DR-03, INT-DR-04, INT-OR-02

### AD-04: Ownership-Aware REST Access with Admin Override

**Decision:** Use author-based access control for non-admin users, deny non-admin direct create, and retain full admin visibility with explicit multisite governance context for super admins.

**Alternatives Considered:**
- Admin-only interaction endpoints
- Public authenticated endpoint with no ownership filtering
- Direct non-admin create through interactions CRUD endpoint

**Rationale:**
- Balances privacy with usability for authenticated users.
- Preserves operational access for administrators.
- Keeps interaction creation on canonical chat lifecycle paths, reducing contract drift and audit ambiguity.
- Supports governed multisite operations without widening non-admin cross-site scope.

**Consequences:**
- Requires custom REST controller behavior beyond default CPT exposure.
- Endpoint consumers must handle authorization outcomes explicitly.
- Multisite callers must pass explicit site context only when authorized as super admin.

**Requirement Links:** INT-FR-07..10, INT-DR-07, INT-QR-02

### AD-05: Exclude Interaction Records from Real-Time Sync

**Decision:** Force `gg_interaction` records out of real-time sync flow by default through `gg_data_should_sync_post`.

**Alternatives Considered:**
- Sync interaction records on each save
- Configurable per-record sync behavior in interaction subsystem

**Rationale:**
- Prevents high-write interaction events from amplifying immediate sync load.
- Keeps interaction capture lightweight and decoupled from sync critical path.

**Consequences:**
- Downstream sync use cases require explicit orchestration.
- Interaction analytics using sync targets are not automatic by default.

**Requirement Links:** INT-FR-12, INT-OR-01

---

## 6. Constraints and Risks

### 6.1 Constraints

| ID | Constraint | Rationale |
|---|---|---|
| C-01 | RAG recording requires caller-provided valid UUID conversation IDs. | Prevents ambiguous turn grouping and data corruption. |
| C-02 | Non-admin REST users are constrained to authored interaction records and cannot directly create records through interactions CRUD. | Enforces least privilege boundaries and canonical creation paths. |
| C-04 | Explicit multisite site context is super-admin-only. | Prevents cross-site data leakage from non-admin paths. |
| C-05 | Multisite admin editor routing for interaction records MUST remain site-local by default and MUST NOT use implicit network-wide ID lookup. | Prevents cross-site admin hijack from numeric ID collisions between sites. |
| C-03 | Interaction capture is tied to upstream hook emissions. | Event-driven design relies on emitting subsystem parity. |

### 6.2 Risks

| ID | Risk | Impact | Mitigation |
|---|---|---|---|
| R-01 | Missing/changed upstream hook emissions reduce recording coverage. | Medium | Keep hook contracts versioned and validated in hook documentation. |
| R-02 | High-concurrency turn appends can contend for lock acquisition. | Medium | Maintain short lock timeout + deterministic append behavior. |
| R-03 | Schema drift between JSON payload and consumer expectations. | Medium | Keep canonical payload examples in developer docs and parity checks during refactors. |

---

## 7. Architecture Traceability

| Architecture Item | Requirement IDs Covered |
|---|---|
| AV-01 | INT-FR-02, INT-FR-03, INT-FR-07, INT-FR-12, INT-OR-01, INT-OR-05 |
| AV-02 | INT-FR-01..06, INT-FR-07..11, INT-DR-01..05, INT-DR-07 |
| AV-03 | INT-FR-02..10, INT-OR-02..03, INT-QR-01..04 |
| AD-01 | INT-FR-02, INT-FR-03, INT-OR-05 |
| AD-02 | INT-FR-04, INT-DR-01..05, INT-QR-03 |
| AD-03 | INT-FR-05, INT-FR-06, INT-DR-03, INT-DR-04, INT-OR-02 |
| AD-04 | INT-FR-07..10, INT-DR-07, INT-QR-02 |
| AD-05 | INT-FR-12, INT-OR-01 |

---

## 8. Readiness Checklist

- Scope and exclusions are explicit.
- Major components and runtime flows are documented.
- Key decisions include alternatives and consequences.
- Constraints and risks are recorded with mitigations.
- Requirement mappings cover major architectural elements.
- Handoff artifacts available in same package:
  - [srs.md](srs.md)
  - [developer-documentation.md](developer-documentation.md)
