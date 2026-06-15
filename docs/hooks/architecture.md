# Architecture Description: Hooks and Extension Surface Subsystem

Standard: ISO/IEC/IEEE 42010:2022
Component: Hooks and Extension Surface Subsystem
Version: 1.0.0
Date: 2026-04-04
Upstream Specification: [srs.md](srs.md)

---

## 1. Architecture Overview

### 1.1 Purpose and Scope

This architecture describes how Gregius Data exposes extension hooks across major subsystems while preserving contract clarity and compatibility boundaries.

Scope includes:
- custom hook namespace and categorization,
- filter/action runtime model,
- hook tiering model (public/semi-public/internal),
- dynamic hook variants,
- reference and contract mapping.

Explicitly excluded:
- implementation of subsystem business logic,
- plugin lifecycle internals beyond hook emission points,
- full RAG algorithmic design.

### 1.2 Stakeholders and Concerns

| Stakeholder | Concern | Priority |
|---|---|---|
| Plugin integrators | Stable public hooks with documented signatures and examples | High |
| Maintainers | Controlled evolution of hooks without accidental API breakage | High |
| Operations | Predictable extension behavior and diagnosable hook pathways | Medium |
| Product/Docs | Canonical docs remain parity-aligned with implementation | High |

---

## 2. Context View (AV-01)

```
Subsystem Runtime
   │
   ├─ Sync + Chunking + Search + Interaction + RAG + Providers
   │            │
   │            ▼
   │      Emission Sites
   │   (apply_filters/do_action)
   │            │
   │            ▼
   │      WordPress Hook Bus
   │            │
   │            ▼
   └── Consumer Extensions
       (themes, mu-plugins, custom plugins)

Canonical Contracts
   ├─ docs/hooks/srs.md
   ├─ docs/hooks/architecture.md
   └─ docs/hooks/developer-documentation.md
```

Mapping:
- AV-01 -> HOOKS-FR-01 to HOOKS-FR-10
- AV-01 -> HOOKS-OR-01 to HOOKS-OR-05

---

## 3. Component View (AV-02)

### 3.1 Hook Category Components

1. **Configuration and Batch Control Hooks**
   - Examples: `gg_data_post_sync_batch_size`, `gg_data_term_sync_batch_size`, `gg_data_embedding_batch_size`, `gg_data_vector_delete_batch_size`.
   - Purpose: operational tuning and throughput controls.

2. **Sync Control Hooks**
   - Examples: `gg_data_should_sync_post`, `gg_data_skip_meta_keys`, `gg_data_metadata_manifest`, `gg_data_sync_post_types_updated`.
   - Purpose: post/meta inclusion control and payload enrichment before persistence.

3. **Chunking Hooks**
   - Examples: `gg_data_chunking_strategies`, `gg_data_chunking_strategy`, `gg_data_embedding_chunks`, `gg_data_chunking_strategy_resolved`.
   - Purpose: chunk strategy registration/selection and payload shaping.

4. **Search Hooks**
   - Examples: `gg_data_search_title_weight`, `gg_data_search_content_weight`, `gg_data_search_similarity_threshold`, `gg_data_search_rate_limit`.
   - Purpose: ranking, typo tolerance tuning, and rate limiting.

5. **Interaction Hooks**
   - Examples: `gg_data_interaction_meta_fields`, `gg_data_interaction_log_context`, `gg_data_interaction_recorded`, `gg_data_interaction_feedback_received`.
   - Purpose: analytics metadata, post-record callbacks, and feedback signal collection.

6. **RAG and Governance Hooks**
   - Examples: `gg_data_rag_tools`, `gg_data_rag_tool_{name}`, `gg_data_rag_tool_executed`, `gg_data_rag_complete`, `gg_data_rag_journey_allowed_block_id_prefixes`, plus governance policy hooks.
   - Purpose: tool registration/execution, journey block ID allowlist extension, and policy tuning.

6b. **RAG Lifecycle Extension Hooks** (added Tier 1)
   - Examples: `gg_data_rag_pre_retrieval_filter`, `gg_data_rag_llm_response`.
   - Purpose: generic retrieval filtering and LLM response post-processing without feature-specific coupling.

7. **Provider and Auxiliary Hooks**
   - Examples: `gg_data_llm_providers`, provider request/call filters/actions, streaming/rate hooks.
   - Purpose: provider extensibility and observability.

8. **Log Governance Hooks**
   - Examples: `gg_data_log_retention_days`, `gg_data_before_purge_logs`, `gg_data_after_purge_logs`.
   - Purpose: retention policy extension, pre/post purge observability, and site-scoped governance.

8b. **Memory Anchoring Extension Hooks** (Tier 2)
   - Examples: `gg_data_rag_pre_retrieval_filter` (extended), `gg_data_rag_turn_metadata`.
   - Purpose: conversation-aware retrieval filtering and turn metadata enrichment for semantic context compression without core behavior logic.

8c. **Confidence Scoring Extension Hooks** (Tier 2)
   - Examples: `gg_data_rag_confidence_score` (filter), confidence response schema fields (`confidence_score`, `confidence_breakdown`).
   - Purpose: extensible confidence computation and factuality-faithfulness scoring with auditability and tamper-resilience without core business logic.

- **Tier 1 (Public Stable):** core extension hooks documented with explicit contracts and examples.
- **Tier 2 (Semi-Public):** advanced hooks with caveats; subject to tighter evolution controls than Tier 3.
- **Tier 3 (Internal):** implementation hooks not guaranteed as stable extension API.

Mapping:
- AV-02 -> HOOKS-FR-02, HOOKS-FR-07 to HOOKS-FR-12, HOOKS-FR-16 to HOOKS-FR-20
- AV-02 -> HOOKS-DR-01 to HOOKS-DR-09

---

## 4. Runtime View (AV-03)

### 4.1 Filter Hook Execution Pattern

```
Core Default Value
   │
   ▼
apply_filters( 'gg_data_xxx', $default, ...$params )
   │
   ├─ 0..N extension callbacks
   │
   ▼
Filtered Value Returned to Core Flow
```

### 4.2 Action Hook Execution Pattern

```
Core Event Occurs
   │
   ▼
do_action( 'gg_data_xxx', ...$event_payload )
   │
   ├─ 0..N extension callbacks
   │
   ▼
Core flow continues (no return contract)
```

### 4.3 Dynamic Hook Pattern

```
Runtime segment resolved
   │
   ▼
$hook_name = 'gg_data_rag_tool_' . $tool_name
   │
   ▼
apply_filters( $hook_name, $payload, ... )
```

Dynamic hooks require explicit format documentation and concrete examples in developer docs.

Mapping:
- AV-03 -> HOOKS-FR-03 to HOOKS-FR-06
- AV-03 -> HOOKS-DR-02 to HOOKS-DR-04

---

## 5. Architectural Decisions (ADRs)

### AD-01: Unified `gg_data_` Namespace for Hook Contracts

Decision:
- Plugin-owned hook contracts use the `gg_data_` prefix except intentionally documented dynamic variants.

Rationale:
- Reduces collisions and keeps extension discovery deterministic.

Consequences:
- New hooks must comply with namespace policy.
- Migration/docs must identify any deferred/legacy naming explicitly.

Linked requirements:
- AD-01 -> HOOKS-FR-01, HOOKS-DR-05

### AD-02: Tiered Stability Model for Hook Surface

Decision:
- Hooks are categorized into Tier 1, Tier 2, and Tier 3 for compatibility governance.

Rationale:
- Separates stable extension API from implementation internals.

Consequences:
- Docs must avoid presenting internal hooks as public guarantees.

Linked requirements:
- AD-02 -> HOOKS-FR-07 to HOOKS-FR-10, HOOKS-OR-05

### AD-03: Public Filter/Action Contracts Must Be Signature-Explicit

Decision:
- Tier 1 hook docs include parameter and default contracts plus emission paths.

Rationale:
- Third-party integrations need predictable signatures and context.

Consequences:
- Hook docs incur parity-maintenance burden.

Linked requirements:
- AD-03 -> HOOKS-DR-01 to HOOKS-DR-03, HOOKS-QR-01

### AD-04: Dynamic Hook Variants Allowed Only for Justified Cases

Decision:
- Dynamic hook names are permitted where static naming is insufficient.

Rationale:
- Enables tool/provider-specific customizations without exploding static hook count.

Consequences:
- Dynamic format and examples are mandatory in docs.

Linked requirements:
- AD-04 -> HOOKS-FR-06, HOOKS-DR-04

### AD-05: New Hooks Require Explicit Tier Assignment

Decision:
- Every newly introduced custom hook must be assigned a Tier (1/2/3) during review and reflected in canonical hook docs when public or semi-public.

Rationale:
- Prevents undocumented code-only hooks from becoming accidental public API.

Consequences:
- Review overhead increases slightly, but release-time compatibility risk decreases.

### AD-06: Lifecycle-Based Generic Extension Surface for Third-Party Plugins

Decision:
- New Tier 1 hooks (`gg_data_rag_pre_retrieval_filter`, `gg_data_rag_llm_response`, `gg_data_interaction_feedback_received`) are defined as generic lifecycle seams, not feature-specific hooks.
- These hooks enable third-party plugins (conversational anchoring, feedback loops, escalation workflows) without embedding feature logic in core.

Rationale:
- Decouples core from third-party plugin evolution; reduces maintenance burden as third-party capabilities expand.
- Provides stable, reusable interfaces for orthogonal concerns (filtering, post-processing, feedback intake).
- Establishes contract boundaries: core emits events; third-party plugins interpret and respond.

Consequences:
- Third-party plugins must implement their own business logic (anchoring strategy, pill generation, escalation rules) rather than relying on core defaults.
- Core code remains lean and non-prescriptive.
- Extension complexity is externalized but documented.

Linked requirements:
- AD-06 -> HOOKS-FR-13, HOOKS-FR-14, HOOKS-FR-15

### AD-07: Conversation-Aware Retrieval and Turn Metadata for Memory Anchoring

Decision:
- Add conversation message history as optional 4th parameter to `gg_data_rag_pre_retrieval_filter` (backward-compatible).
- Introduce `gg_data_rag_turn_metadata` (Tier 2) filter hook for anchor-relevant turn governance metadata.
- Third-party plugins implement Memory Anchoring / Semantic Context Compression logic, not core.

Rationale:
- Core publishes agnostic conversation-aware filtering and metadata enrichment points.
- Third-party plugins can implement custom anchor selection, evidence ranking, and conversation pruning without coupling to core.
- Ensures deterministic metadata shape for audit and traceability.

Consequences:
- Retrieval filter extended but maintains backward compatibility (4th param has safe default).
- Turn metadata must be persisted in interaction records for audit trail.
- Third-party plugins must implement own anchoring strategy; no default behavior in core.

Linked requirements:
- AD-07 -> HOOKS-FR-16, HOOKS-FR-17, HOOKS-DR-09

### AD-08: Confidence Scoring with Immutable Audit Trail and Extensible Computation

Decision:
- Every RAG response includes `confidence_score` (float 0.0-1.0) and `confidence_breakdown` (structured factors).
- Introduce `gg_data_rag_confidence_score` (Tier 2) filter hook for override without making confidence data mutable.
- Core computes baseline confidence; third-party plugins can override via hook but cannot mutate stored audit fields.
- Third-party plugins implement Confidence Scoring / Factuality-Faithfulness policy, not core.

Rationale:
- Confidence is required for enterprise audit, explainability, and hallucination prevention.
- Baseline formula prevents guardrail bypass; third-party plugins can customize logic via extension hook.
- Immutable audit fields preserve integrity for compliance and post-hoc analysis.
- Reason-code enum prevents silent failures and aids troubleshooting.

Consequences:
- Response schema expanded permanently; all responses contain confidence metadata.
- Turn metadata includes confidence_score and confidence_factors as immutable audit fields.
- Third-party plugins implement own scoring calibration; baseline formula is non-prescriptive.
- Governance/policy tuning is externalized via filter hook.

Linked requirements:
- AD-08 -> HOOKS-FR-18, HOOKS-FR-19, HOOKS-FR-20, HOOKS-DR-08

### AD-09: Staged Promotion Policy for Enterprise Contracts

Decision:
- Memory Anchoring (HOOKS-FR-16/17) and Confidence Scoring (HOOKS-FR-18/19/20) hooks are released as **Tier 2** (semi-public) for one full release cycle.
- Promotion to Tier 1 is gated on: (1) zero signature drift, (2) no breaking changes in downstream third-party implementations, (3) documented calibration evidence (confidence only).

Rationale:
- Tier 2 period allows calibration, signature validation, and downstream feedback collection without committing to immutable contract.
- Reduces release-time risk of immature specifications.
- Third-party plugin ecosystem validated through controlled release cycle.

Consequences:
- One-cycle validation gate before Tier 1 status; documented promotion criteria.
- Staged rollout reduces risk to third-party integrations.
- Architecture complete: core publishes agnostic lifecycle seams; third-party plugins implement domain-specific behavior.

Linked requirements:
- AD-09 -> HOOKS-OR-06, HOOKS-OR-07

### 6.1 Constraints

| ID | Constraint | Impact |
|---|---|---|
| C-01 | Hook compatibility guarantees apply only by documented tier status. | Integrators must check hook tier before depending on stability. |
| C-02 | Monolithic docs may contain stale hooks not present in current implementation. | Canonical docs must flag unknown hooks explicitly. |
| C-03 | Dynamic hooks require runtime-derived names. | Integrators need clear naming templates and examples. |
| C-04 | Code-only hooks must complete tier review before being treated as public extension contracts. | Maintainers must classify hooks early to avoid accidental API exposure. |

### 6.2 Risks

| Risk ID | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| R-01 | Docs overpromise internal hooks as stable API | Medium | High | Tier labeling and explicit internal section |
| R-02 | Undocumented code-only hooks cause extension blind spots | High | Medium | Add critical code-only hooks to developer docs |
| R-03 | Broken references during monolith retirement | Medium | Medium | Rewire references before deletion + grep gate |

---

## 7. Coverage Mapping

| Architecture Item | SRS IDs Covered |
|---|----|---|
| AV-01 (Context View) | HOOKS-FR-01 to HOOKS-FR-12, HOOKS-OR-01 to HOOKS-OR-05 |
| AV-02 (Component View) | HOOKS-FR-02, HOOKS-FR-07 to HOOKS-FR-12, HOOKS-FR-16 to HOOKS-FR-20, HOOKS-DR-01 to HOOKS-DR-09 |
| AV-03 (Runtime View) | HOOKS-FR-03 to HOOKS-FR-06, HOOKS-DR-02 to HOOKS-DR-04 |
| AD-01 | HOOKS-FR-01, HOOKS-DR-05 |
| AD-02 | HOOKS-FR-07 to HOOKS-FR-10, HOOKS-OR-05 |
| AD-03 | HOOKS-DR-01 to HOOKS-DR-03, HOOKS-QR-01 |
| AD-04 | HOOKS-FR-06, HOOKS-DR-04 |
| AD-05 | HOOKS-FR-07, HOOKS-OR-03, HOOKS-OR-05 |
| AD-06 | HOOKS-FR-13, HOOKS-FR-14, HOOKS-FR-15 |
| AD-07 | HOOKS-FR-16, HOOKS-FR-17, HOOKS-DR-09 |
| AD-08 | HOOKS-FR-18, HOOKS-FR-19, HOOKS-FR-20, HOOKS-DR-08 |
| AD-09 | HOOKS-OR-06, HOOKS-OR-07 |
