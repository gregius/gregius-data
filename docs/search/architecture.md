# Architecture Description: Search Subsystem

Standard: ISO/IEC/IEEE 42010:2022  
Component: Search Subsystem  
Version: 1.0.0  
Date: 2026-04-04  
Upstream Specification: [srs.md](srs.md)

---

## 1. Architecture Overview

### 1.1 Purpose and Scope

This architecture describes how Gregius Data integrates enhanced retrieval into WordPress search by combining query interception, SQL orchestration, provider-path routing, and operational controls.

Scope includes:
- WordPress search interception and result ordering integration
- SQL function orchestration for lexical, trigram, and vector-capable retrieval
- JSONB metadata filtering contracts for deterministic scoped retrieval
- Provider-path routing for PDO and PostgREST execution
- Health fallback and observability behavior
- Search management REST endpoints (status/schema/language/typo-tolerance)

Explicitly excluded:
- RAG synthesis and answer-generation architecture
- Vector generation internals beyond search consumption assumptions
- End-user search UX guidance and dashboard walkthroughs

### 1.2 Stakeholders and Concerns

| Stakeholder | Concern | Priority |
|---|---|---|
| Plugin developers | Stable search extension points, SQL orchestration boundaries, and provider parity | High |
| Site administrators | Predictable search controls, health visibility, and safe fallback behavior | High |
| Operations | Diagnosable degradation behavior and actionable status endpoints | High |
| Integrators | Consistent search behavior across WordPress query contexts and provider configurations | High |

---

## 2. Context View (AV-01)

```
┌────────────────────────────────────────────────────────────────────┐
│                         WordPress Runtime                          │
│                                                                    │
│  WP_Query Search Request                                            │
│         │                                                          │
│         ▼                                                          │
│  Search Integration Filter Layer                                    │
│  (posts_search / posts_orderby)                                     │
│         │                                                          │
│         ▼                                                          │
│  Search Execution Layer                                             │
│  - PDO PostgreSQL path                                              │
│  - PostgREST RPC path                                               │
│         │                                                          │
│         ▼                                                          │
│  SQL Search Functions                                                │
│  - search_native_orchestrate                                        │
│  - search_core_vector_candidates / RRF helper                       │
│                                                                    │
└────────────────────────────────────────────────────────────────────┘
         │                                      │
         ▼                                      ▼
 PostgreSQL / PostgREST                    Search REST Management
 (fts + trigram + vector)                  (/search/* endpoints)
```

Architecture intent:
- Keep WordPress search compatibility by integrating at filter layer instead of replacing query primitives entirely.
- Keep retrieval logic centralized in SQL orchestration for parity across execution paths.
- Keep operational control and diagnostics explicit through dedicated REST search management routes.

Mapping:
- AV-01 -> SEARCH-FR-01 to SEARCH-FR-06
- AV-01 -> SEARCH-OR-01 to SEARCH-OR-04

---

## 3. Component View (AV-02)

### 3.1 Component Decomposition

1. Search Integration Component (`GG_Data_Search_Integration`)
- Hooks into `posts_search` and `posts_orderby`.
- Resolves active connection and search settings.
- Executes PostgreSQL search and composes MySQL catch-up behavior for unsynced types.

2. Search SQL Orchestration Component (`create-search-function.sql`)
- Provides native orchestrator `search_native_orchestrate`.
- Provides RAG-oriented orchestrator `search_rag_orchestrate` with JSONB metadata containment support.
- Provides shared scoring helpers and vector candidate generation.

3. Search Schema Management Component (`GG_Data_Search_Schema`)
- Creates and verifies SQL search functions.
- Tracks provisioning readiness and schema support status.

4. Search Fallback/Health Component (`GG_Data_Search_Fallback`)
- Handles runtime fallback execution boundaries.
- Tracks success/failure/latency metrics and degradation state.

5. Search REST Management Component (`GG_Data_REST_Search_Controller`)
- Exposes health, status, schema, language, and typo-tolerance routes.
- Provides operator entrypoints for maintenance and diagnostics.

### 3.2 Provider Path Routing

- PDO path executes SQL function calls directly via database connection.
- PostgREST path executes equivalent RPC calls to SQL functions.
- Both paths share core parameter contracts (`search_text`, `post_types`, language, trigram/vector flags, vector table metadata).

Mapping:
- AV-02 -> SEARCH-FR-04 to SEARCH-FR-11
- AV-02 -> SEARCH-DR-03 to SEARCH-DR-08

---

## 4. Runtime View (AV-03)

### 4.1 WordPress Search Request Flow

```
WP_Query main search
   │
   ▼
Enhanced search enabled check
   │
   ├─ disabled -> WordPress default search behavior
   │
   ▼
Connection and post-type resolution
   │
   ▼
PostgreSQL/PostgREST search execution
   │
   ├─ if `retrieval_mode = hybrid_default` -> merge MySQL coverage
   │
   ├─ if `retrieval_mode = postgresql_only` -> skip merge path
   │
   ├─ if PostgreSQL execution fails -> internal MySQL fallback
   │
   ▼
Deduplicate + merge results
   │
   ▼
Apply ordered post ID constraints to WP query
   │
   ▼
Emit gg_data_search_completed action
```

### 4.2 SQL Orchestration Flow

```
search_native_orchestrate
   │
   ├─ FTS lexical candidates
   ├─ Trigram candidates (capability + query conditions)
   ├─ Vector candidates (capability + data conditions)
   ├─ Optional metadata filter containment per branch
   │
   ▼
Candidate fusion and ranking
   │
   ▼
Result set with match_type metadata
```

### 4.3 Operational Control Flow

```
/search/* REST request
   │
   ▼
Admin permission check
   │
   ▼
Controller callback
   │
   ├─ health/status retrieval
   ├─ schema creation
   ├─ language status/update
   └─ typo tolerance status/config update
   │
   ▼
Structured success/error response
```

Mapping:
- AV-03 -> SEARCH-FR-02, SEARCH-FR-03, SEARCH-FR-07, SEARCH-FR-09 to SEARCH-FR-12
- AV-03 -> SEARCH-OR-02, SEARCH-OR-03, SEARCH-OR-05 to SEARCH-OR-07

---

## 5. Architectural Decisions (ADRs)

### AD-01: SQL Orchestrator as Canonical Search Execution Core

Decision:
- `search_native_orchestrate` is the sole native search entry point.

Rationale:
- Consolidates retrieval logic for lexical/trigram/vector behavior into a single parallel RRF function.
- The legacy `gg_search_posts_fts` wrapper has been removed.

Consequences:
- All callers must use `search_native_orchestrate`.

Linked requirements:
- AD-01 -> SEARCH-FR-04, SEARCH-FR-06, SEARCH-QR-07

### AD-02: Provider-Path Parity Through Shared Parameter Contract

Decision:
- Use equivalent parameterized execution semantics for PDO and PostgREST paths.

Rationale:
- Keeps behavior aligned across connection providers.
- Reduces divergence risk between runtime environments.

Consequences:
- Provider capabilities and transport differences remain an operational risk area.
- Parity testing needs to be explicit.

Linked requirements:
- AD-02 -> SEARCH-FR-05, SEARCH-DR-08, SEARCH-QR-05

### AD-03: WordPress Compatibility via Filter-Layer Interception

Decision:
- Integrate enhanced retrieval through `posts_search` and `posts_orderby` filters instead of replacing WordPress search architecture wholesale.

Rationale:
- Preserves compatibility with WordPress query lifecycle.
- Limits invasive changes to site behavior.

Consequences:
- Main-query and filter-order behavior must be handled carefully.
- Rank-order consistency requires explicit ordering logic.

Linked requirements:
- AD-03 -> SEARCH-FR-02, SEARCH-FR-07, SEARCH-QR-02

### AD-04: Fallback as Availability Guardrail

Decision:
- Treat fallback and health-tracking behavior as first-class architecture, not optional diagnostics.

Rationale:
- Search failures should not break site availability.
- Operational teams need explicit visibility into degraded states.

Consequences:
- Fallback metrics and alerting become operational dependencies.
- Troubleshooting workflows must include fallback state interpretation.

Linked requirements:
- AD-04 -> SEARCH-OR-02, SEARCH-OR-03, SEARCH-QR-04, SEARCH-QR-08

### AD-05: Capability-Gated Parallel Hybrid Retrieval via RRF

Decision:
- Enable trigram/vector behavior only when extension and data prerequisites are met.
- All three retrieval strategies (FTS, trigram, vector) run in parallel as independent CTEs, fused via reciprocal rank fusion (RRF).
- Vector search is capability-gated (extension + table + data availability), not cascade-gated by lexical count.

Rationale:
- Prevents hard failures in heterogeneous deployments.
- Parallel execution avoids the blind spots of cascade/fallback — each strategy independently covers gaps (e.g., FTS stemmer misses, trigram for typos, vector for semantics).
- RRF fusion produces stable blended rankings without brittle threshold tuning.

Consequences:
- Feature availability can vary per connection/deployment.
- Status endpoints and docs must make capability state explicit.
- `semantic_activation_threshold` no longer exists — vector eligibility is purely capability-driven.

Linked requirements:
- AD-05 -> SEARCH-FR-11, SEARCH-OR-06, SEARCH-OR-07, SEARCH-QR-06

### AD-06: Metadata Filter Contract Uses JSONB Object Default

Decision:
- Treat empty metadata filters as JSON objects (`{}`) so SQL containment bypass checks evaluate correctly.

Rationale:
- JSONB containment checks in orchestrator paths rely on object semantics for no-filter bypass behavior.
- Array serialization (`[]`) can produce false negatives and suppress otherwise valid results.

Consequences:
- Provider payload builders must preserve object semantics for empty metadata filters.
- Schema and content-cleaning contracts must keep `metadata_manifest` available for deterministic containment checks.

Linked requirements:
- AD-06 -> SEARCH-FR-13, SEARCH-DR-09, SEARCH-DR-10, SEARCH-QR-09

---

## 6. Constraints and Risks

### 6.1 Constraints

- Search settings are site-wide in key areas, while execution is connection-sensitive.
- Retrieval-mode options are intentionally constrained to two user-facing values: `hybrid_default` and `postgresql_only`.
- SQL orchestration depends on PostgreSQL extension and table readiness.
- Metadata containment behavior depends on `metadata_manifest` JSONB availability on cleaned post rows.
- PostgREST execution depends on RPC and API-key availability.

### 6.2 Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Provider-path behavior drift | High | Maintain shared contract docs and parity checks |
| Capability mismatch (extensions/tables unavailable) | High | Capability-gated execution + status endpoints |
| Rank-order inconsistency in WordPress rendering | Medium | Explicit `posts_orderby` relevance ordering |
| Silent degradation without operator visibility | Medium | Health telemetry + admin-managed check/reset routes |
| Empty metadata filter serialized as JSON array | High | Preserve object-default contract (`{}`) in provider payload builders and regression checks |

---

## 7. Architecture Coverage Mapping

| Architecture Item | Requirement Coverage |
|---|---|
| AV-01 Context View | SEARCH-FR-01 to SEARCH-FR-06; SEARCH-OR-01 to SEARCH-OR-04 |
| AV-02 Component View | SEARCH-FR-04 to SEARCH-FR-11; SEARCH-DR-03 to SEARCH-DR-08 |
| AV-03 Runtime View | SEARCH-FR-02, SEARCH-FR-03, SEARCH-FR-07, SEARCH-FR-09 to SEARCH-FR-12 |
| AD-01 Orchestrator Core | SEARCH-FR-04, SEARCH-FR-06, SEARCH-QR-07 |
| AD-02 Provider Parity | SEARCH-FR-05, SEARCH-DR-08, SEARCH-QR-05 |
| AD-03 WP Filter Integration | SEARCH-FR-02, SEARCH-FR-07, SEARCH-QR-02 |
| AD-04 Availability Guardrail | SEARCH-OR-02, SEARCH-OR-03, SEARCH-QR-04 |
| AD-05 Capability Gating | SEARCH-FR-11, SEARCH-OR-06, SEARCH-OR-07, SEARCH-QR-06 |
| AD-06 JSONB Metadata Filter Contract | SEARCH-FR-13, SEARCH-DR-09, SEARCH-DR-10, SEARCH-QR-09 |

---

## 8. Readiness and Handoff

Readiness status:
- Architecture context, component, and runtime views are defined.
- Major architecture decisions are explicit and requirement-linked.
- Risk and constraint framing is available for implementation and operations.

Canonical scope note:
- Canonical architecture scope for Search is maintained in this package.

Downstream artifact:
- [developer-documentation.md](developer-documentation.md)
