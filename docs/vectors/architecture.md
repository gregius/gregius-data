# Architecture Description: Vectors and Embeddings Subsystem

Standard: ISO/IEC/IEEE 42010:2022  
Component: Vectors and Embeddings Subsystem  
Version: 1.0.0  
Date: 2026-04-04  
Upstream Specification: [srs.md](srs.md)

---

## 1. Architecture Overview

### 1.1 Purpose and Scope

This architecture describes how Gregius Data generates and manages semantic vectors through strategy orchestration, vocabulary lifecycle controls, and operational APIs while keeping contracts stable for Search and RAG retrieval consumers.

Scope includes:
- Vector orchestration and strategy routing
- TF-IDF and API embeddings execution paths
- HashingTF/stateless internal embeddings execution path
- Vocabulary preparation/status/cache lifecycle
- Connection-model association and model routing behavior
- Vector and vocabulary REST operational controls
- Timeout-safe batch deletion for large vector tables
- Row-per-embedding storage model and contract constraints

Explicitly excluded:
- Full schema lifecycle/migrations outside vectors-specific table assumptions
- Search ranking and query orchestration internals
- RAG synthesis and answer-generation behavior

### 1.2 Stakeholders and Concerns

| Stakeholder | Concern | Priority |
|---|---|---|
| Plugin developers | Stable strategy interfaces and model contracts for extensibility | High |
| Site administrators | Predictable, safe, and auditable vector/vocabulary operations | High |
| Operations | Diagnosable status endpoints, error handling, and regeneration workflows | High |
| Integrators | Consistent model-table contracts and provider-path parity | High |

---

## 2. Context View (AV-01)

```
┌───────────────────────────────────────────────────────────────────┐
│                         WordPress Runtime                         │
│                                                                   │
│  Dashboard / REST Clients                                          │
│          │                                                        │
│          ▼                                                        │
│  Vector and Vocabulary REST Layer                                  │
│  - /vector-queue                                                   │
│  - /vectors/*                                                      │
│  - /vocabulary/*                                                   │
│  - /connections/{connection}/vectors/models                        │
│          │                                                        │
│          ▼                                                        │
│  Vector Orchestration Layer                                        │
│  - GG_Data_Vector_Generator                                        │
│  - Strategy selection (TF-IDF | Hashing TF | API embeddings)       │
│          │                                                        │
│          ▼                                                        │
│  Data and Model Dependencies                                       │
│  - Model registry + connection-model associations                  │
│  - Cleaned/chunked content contracts                               │
│  - Vocabulary manager                                               │
│                                                                   │
└───────────────────────────────────────────────────────────────────┘
           │                                   │
           ▼                                   ▼
   PostgreSQL Vector Tables              Search and RAG Consumers
   (row-per-embedding)                   (consume embedding outputs)
```

Architecture intent:
- Keep vector generation and model routing centralized in one orchestrator.
- Keep strategy implementations swappable behind a stable interface contract.
- Keep operational control explicit through dedicated vector and vocabulary endpoints.

Mapping:
- AV-01 -> VEC-FR-01 to VEC-FR-10
- AV-01 -> VEC-OR-01 to VEC-OR-07

---

## 3. Component View (AV-02)

### 3.1 Component Decomposition

1. Vector Generator Component (`GG_Data_Vector_Generator`)
- Resolves model metadata from registry.
- Selects first supporting strategy for the model contract.
- Executes generation and logs operational outcomes.

2. Vector Strategy Components (`GG_Data_Vector_Strategy_Interface`)
- `GG_Data_TFIDF_Strategy` for internal TF-IDF model workflows.
- `GG_Data_HashingTF_Strategy` for internal stateless hashing model workflows (no vocabulary prerequisite).
- `GG_Data_API_Embeddings_Strategy` for provider-backed embeddings.
- Shared result contract (`success`, `message`, `processed`, `failed`, `total_tokens`).

3. Vocabulary Management Component (`GG_Data_Vocabulary_Manager`)
- Builds vocabulary from cleaned content corpus.
- Supports provider-path-aware execution (PDO and PostgREST/Supabase style).
- Exposes status and cache clear operations.

4. Vector REST Management Component (`GG_Data_REST_Vector_Queue_Controller`)
- Queue visibility, single item generation, batch generation, batch deletion, status, and clear operations.
- Posts-list status endpoint for operational inspection.

5. Vocabulary REST Management Component (`GG_Data_REST_Vocabulary_Controller`)
- Vocabulary prepare, status, and cache clear routes.

6. Connection-Model REST Component (`GG_Data_REST_Connection_Models_Controller`)
- Lists, adds, and removes active models per connection.
- Masks sensitive model config data in responses.

7. Storage Contract Component (PostgreSQL vector tables)
- Row-per-embedding model with `post_id + field_type + chunk_index` uniqueness.
- Supports title, excerpt, and chunk embeddings under one table per model.
- Each model family owns independent DDL columns: TF-IDF uses `vocabulary_version`; provider-backed tables may use `model_used`; HashingTF uses `tokenizer_version`. No column uniformity is required across model families.
- The search SQL function detects presence of `vocabulary_version` at runtime. If absent, the dense/stateless search path is used automatically.

### 3.2 Contract Boundaries

- Upstream dependencies: sync-clean-chunk contracts and model registry state.
- Downstream dependencies: search and RAG consumers of generated vectors.
- Operational boundaries: admin-authorized endpoints for all state-changing operations.

Mapping:
- AV-02 -> VEC-FR-01 to VEC-FR-13
- AV-02 -> VEC-DR-01 to VEC-DR-10

---

## 4. Runtime View (AV-03)

### 4.1 Batch Generation Runtime Flow

```
POST /gg-data/v1/vectors/batch-generate
    │
    ▼
Permission check (manage_options)
    │
    ▼
Resolve model by connection + model_key
    │
    ├─ fallback to global model registry scope (if configured)
    │
    ▼
Vector generator selects strategy by supports_model()
    │
    ├─ TF-IDF strategy path
    │    ├─ validate vocabulary readiness
    │    ├─ consume cleaned/chunked content
    │    └─ upsert row-per-embedding vectors
    │
    ├─ HashingTF strategy path
    │    ├─ compute bucket hash (MurmurHash3, 1024D) — no vocabulary check
    │    ├─ consume cleaned/chunked content
    │    └─ upsert row-per-embedding vectors
    │
    └─ API embeddings strategy path
         ├─ consume model/provider settings
         ├─ generate embeddings per field/chunk
	         └─ persist vectors + token metadata
    │
    ▼
Structured response and operational logging
```

### 4.2 Vocabulary Runtime Flow

```
POST /gg-data/v1/vocabulary/prepare
    │
    ▼
Permission check (manage_options)
    │
    ▼
Detect connection type (PDO or PostgREST-compatible)
    │
    ▼
Read wp_posts_clean corpus
    │
    ▼
Build vocabulary and persist metadata/cache
    │
    ▼
Return version and readiness metadata
```

### 4.3 Connection-Model Runtime Flow

```
GET/POST/DELETE /connections/{connection}/vectors/models
    │
    ▼
Permission check (manage_options)
    │
    ▼
Read/update connection-model associations
    │
    ▼
Return sanitized model payloads and status messaging
```

### 4.4 Batch Delete Runtime Flow

```
POST /gg-data/v1/vectors/batch-delete
    │
    ▼
Permission check (manage_options)
    │
    ▼
Resolve model_key -> vector_table_name
    │
    ▼
Resolve connection provider type
    │
    ├─ PDO path
    │    ├─ SELECT first N ids from current remaining set
    │    ├─ DELETE selected ids
    │    └─ COUNT remaining rows
    │
    └─ PostgREST/Supabase path
         ├─ provider get_ids(table, batch_size, 0)
         ├─ provider delete_ids(table, ids)
         └─ provider count_records(table)
    │
    ▼
Return progress payload
(`deleted`, `total_deleted`, `has_more`, `duration_ms`, `errors`)
```

Runtime intent:
- Avoid offset drift by always selecting from the current head of remaining rows.
- Preserve provider parity across PDO and PostgREST/Supabase transports.
- Stop explicitly if zero rows are deleted while rows remain, preventing endless polling loops.

Mapping:
- AV-03 -> VEC-FR-03 to VEC-FR-12
- AV-03 -> VEC-OR-01 to VEC-OR-06
- AV-03 -> VEC-QR-04 to VEC-QR-06

---

## 5. Architectural Decisions (ADRs)

### AD-01: Strategy Pattern for Embeddings Generation

Decision:
- Route generation through a strategy interface with model-driven selection.

Rationale:
- Allows internal and provider-backed models without changing API callers.
- Keeps future model types extensible with minimal orchestration impact.

Consequences:
- Strategy compatibility checks become critical contract points.
- Error handling and result contracts must remain normalized.

Linked requirements:
- AD-01 -> VEC-FR-01, VEC-FR-02, VEC-DR-01, VEC-DR-02, VEC-QR-08

### AD-02: Row-Per-Embedding Storage Model

Decision:
- Store title, excerpt, and chunk embeddings as separate rows with a unified uniqueness contract.

Rationale:
- Supports long-content chunking and flexible field-level weighting by consumers.
- Simplifies schema evolution for field-specific behavior.

Consequences:
- Query and reporting layers must aggregate row-level embeddings per post.
- Storage and indexing strategy must be tuned for mixed field types.

Linked requirements:
- AD-02 -> VEC-FR-10, VEC-DR-03, VEC-DR-04, VEC-QR-02

### AD-03: Vocabulary as a Managed Runtime Dependency for TF-IDF

Decision:
- Treat vocabulary readiness as a prerequisite state for TF-IDF generation.

Rationale:
- Preserves deterministic TF-IDF output and avoids repeated expensive rebuilds.
- Enables operators to control regeneration windows.

Consequences:
- TF-IDF generation can be blocked by stale/missing vocabulary states.
- Operational guidance must include vocabulary health workflows.

Linked requirements:
- AD-03 -> VEC-FR-04, VEC-FR-05, VEC-FR-06, VEC-OR-06, VEC-QR-03

### AD-04: Connection-Aware Model Activation

Decision:
- Maintain active model associations per connection instead of global activation only.

Rationale:
- Supports environment-specific model choices and phased rollouts.
- Reduces operational risk when evaluating new models.

Consequences:
- Requires clear fallback and visibility semantics across connection scopes.
- Increases metadata management complexity.

Linked requirements:
- AD-04 -> VEC-FR-09, VEC-FR-12, VEC-OR-05, VEC-DR-08

### AD-05: Admin-Gated Operational Endpoints

Decision:
- Require administrative authorization for vector and vocabulary mutation paths.

Rationale:
- Prevents unauthorized vector operations and model changes.
- Aligns with operational risk profile of vector generation.

Consequences:
- Non-admin automation must use trusted execution contexts.
- UX and automation tooling must surface auth failures clearly.

Linked requirements:
- AD-05 -> VEC-OR-01, VEC-DR-10, VEC-QR-04

### AD-06: Stateless Hashing Model Requires No Vocabulary Prerequisite

Decision:
- HashingTF Murmur3 model uses feature hashing (PHP `hash('murmur3a')`) instead of corpus vocabulary, making it immediately usable without any preparation step.

Rationale:
- Reduces operator burden for environments where vocabulary preparation is impractical.
- Provides a zero-dependency baseline embedding option deployable to any WordPress hosting environment.

Consequences:
- HashingTF vectors lack IDF weighting, which may reduce precision compared to corpus-informed TF-IDF.
- Tokenizer version bumps (instead of vocabulary version bumps) trigger regeneration workflows.
- The search function's runtime vocabulary_version column detection automatically routes HashingTF to the dense/stateless search path without code changes.

Linked requirements:
- AD-06 -> VEC-FR-02, VEC-FR-15, VEC-DR-03, VEC-DR-04

### AD-07: Destructive Pagination Uses Head-Of-Remaining Selection

Decision:
- Batch delete operations always select from offset `0` of the current remaining dataset rather than using a client-supplied shifting offset.

Rationale:
- Deleting rows changes the dataset cardinality; offset-based destructive pagination can skip remaining rows and cause stalled UI loops.

Consequences:
- `next_offset` remains a progress signal for clients, not a server-side selection cursor.
- Backend must own loop-safety checks when deleted count is zero and rows still remain.

Linked requirements:
- AD-07 -> VEC-FR-16, VEC-DR-11, VEC-OR-09, VEC-QR-09

---

## 6. Constraints and Risks

### 6.1 Constraints

| ID | Constraint | Impact |
|---|---|---|
| C-01 | Vectors rely on upstream cleaned and chunked content contracts. | Out-of-date sync/chunking can degrade vector quality or completeness. |
| C-02 | Provider-backed models depend on external API capabilities, quotas, and connectivity. | Generation reliability varies by provider runtime conditions. |
| C-03 | Row-per-embedding contracts require stable table naming and model metadata. | Model misconfiguration can break downstream search/RAG expectations. |
| C-04 | Vocabulary management requires connection-aware data access paths. | Provider-path divergences can create parity drift without explicit checks. |

### 6.2 Risks

| Risk ID | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| R-01 | Strategy/provider parity drift over time | Medium | High | Keep shared strategy result contract and parity tests across model/provider paths |
| R-02 | Vocabulary drift causes stale TF-IDF output quality | Medium | Medium | Enforce status reporting and explicit regeneration workflows |
| R-03 | Misconfigured model associations target wrong vector tables | Low | High | Validate model metadata and expose clear status/diagnostic payloads |
| R-04 | Operational misuse triggers expensive or unnecessary generation | Medium | Medium | Keep admin gating and explicit endpoint semantics for destructive/regenerative actions |
| R-05 | Downstream consumers assume unavailable model tables | Medium | Medium | Keep integration checks and fallback expectations explicit in Search/RAG integration docs |

---

## 7. Coverage Mapping

| Architecture Item | SRS IDs Covered |
|---|---|
| AV-01 (Context View) | VEC-FR-01 to VEC-FR-10, VEC-OR-01 to VEC-OR-07 |
| AV-02 (Component View) | VEC-FR-01 to VEC-FR-13, VEC-DR-01 to VEC-DR-10 |
| AV-03 (Runtime View) | VEC-FR-03 to VEC-FR-12, VEC-OR-01 to VEC-OR-06, VEC-QR-04 to VEC-QR-06 |
| AD-01 | VEC-FR-01, VEC-FR-02, VEC-DR-01, VEC-DR-02, VEC-QR-08 |
| AD-02 | VEC-FR-10, VEC-DR-03, VEC-DR-04, VEC-QR-02 |
| AD-03 | VEC-FR-04, VEC-FR-05, VEC-FR-06, VEC-OR-06, VEC-QR-03 |
| AD-04 | VEC-FR-09, VEC-FR-12, VEC-OR-05, VEC-DR-08 |
| AD-05 | VEC-OR-01, VEC-DR-10, VEC-QR-04 |
| AD-06 | VEC-FR-02, VEC-FR-15, VEC-DR-03, VEC-DR-04 |

---

## 8. Readiness and Handoff

- Canonical vectors and embeddings architecture scope is maintained in this package.
- Legacy root-level vectors and embeddings architecture narratives are retired after reference rewiring is complete.
- Next implementation and maintenance work should use:
  - [srs.md](srs.md)
  - [architecture.md](architecture.md)
  - [developer-documentation.md](developer-documentation.md)
