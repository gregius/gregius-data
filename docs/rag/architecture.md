# Architecture Description: RAG Subsystem

**Standard:** ISO/IEC/IEEE 42010:2022  
**Component:** Retrieval-Augmented Generation (RAG)  
**Version:** 1.0.0  
**Date:** 2026-03-30  
**Upstream Specification:** [Software Requirements Specification](srs.md)

---

## 1. Architecture Overview

### 1.1 Purpose and Scope

This architecture describes how the Gregius Data plugin delivers grounded RAG behavior across retrieval, governance, answer generation, and externally exposed interaction contracts.

**Scope:**
- RAG orchestration and request lifecycle
- Retrieval planning and retrieval-mode resolution
- Structured metadata propagation for retrieval scoping (`metadata_filter`, `metadata_manifest`)
- Canonical manifest normalization and versioned manifest-contract handling
- Deterministic tool override routing (`forced_tool`) ahead of agentic selection
- Passage expansion, acceptance, and coverage governance
- Answer generation and metadata emission
- REST-facing RAG interaction contracts
- Security and extension boundaries for RAG-specific behavior

Canonical contract reference:
- [RAG Manifest Contract](manifest-contract.md)

**Explicitly Excluded:**
- Provider subsystem internals beyond their role as supporting dependencies
- Benchmark runner implementation
- End-user UX guidance and operator playbooks
- Class-by-class implementation reference and recipes

### 1.2 Stakeholders and Concerns

| Stakeholder | Concern | Priority |
|---|---|---|
| Plugin Developers | Clear boundaries between retrieval, governance, and generation so subsystem changes remain localized | High |
| Site Administrators | Configurable models, access policy, and predictable fallback behavior without source edits | High |
| Frontend and API Integrators | Stable response and metadata contracts with extension hooks | High |
| Operations | Observability into retrieval, governance, and acceptance behavior for release validation | High |
| Security Reviewers | Ability to tighten public access and preserve explicit denial behavior | High |

---

## 2. Architecture Context View (AV-01)

### 2.1 Context View

```
┌──────────────────────────────────────────────────────────────────┐
│                      GREGIUS DATA PLUGIN                         │
│                                                                  │
│  Clients                                                          │
│  ┌─────────────────────┐   ┌────────────────────┐                │
│  │ Gutenberg RAG Block │   │ REST / Custom UI   │                │
│  └──────────┬──────────┘   └──────────┬─────────┘                │
│             │                         │                          │
│             └──────────────┬──────────┘                          │
│                            ▼                                     │
│                 ┌──────────────────────────┐                     │
│                 │      RAG Subsystem       │                     │
│                 │ GG_Data_RAG_Service      │                     │
│                 │ REST RAG Controller      │                     │
│                 │ Coverage Gate            │                     │
│                 └──────────┬───────────────┘                     │
│                            │                                     │
│      ┌─────────────────────┼──────────────────────┐              │
│      ▼                     ▼                      ▼              │
│  Search / Retrieval    Prompt + LLM         Security + Settings  │
│  Integration Layer     Provider Layer       Layer                │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
             │                     │                     │
             ▼                     ▼                     ▼
     PostgreSQL / PostgREST     LLM / Embedding /      WordPress settings,
     search backends            rerank providers        capabilities, hooks
```

### 2.2 Context Rationale

The subsystem sits between client-facing interfaces and backend services. It centralizes request orchestration so retrieval policy, governance, and answer generation are applied consistently regardless of whether the request originates from a frontend block, a REST client, or future plugin integrations.

**Mapping to Requirements:**
- AV-01 -> RAG-FR-01 to RAG-FR-21
- AV-01 -> RAG-OR-01 to RAG-OR-07

---

## 3. Architecture Component View (AV-02)

### 3.1 Major Components

```
Request Entry
    │
    ▼
REST Controller / Client Caller
    │
    ▼
RAG Orchestrator
    ├─ Tool Selection / Direct Response Routing
    ├─ Retrieval Planning and Mode Resolution
    ├─ Passage Expansion and Candidate Acceptance
    ├─ Coverage Gate and Governance Decision
    ├─ Prompt Resolution and LLM Invocation
    └─ Response / Metadata Assembly
    │
    ├───────────┬──────────────┬──────────────┐
    ▼           ▼              ▼              ▼
Search Layer  Prompt Layer  AI Client Layer  Security / Settings Layer
```

### 3.2 Component Responsibilities

1. **Entry and Contract Layer**
   - Accepts chat and action requests.
   - Validates required input.
    - Sanitizes optional metadata filter and metadata manifest payloads.
   - Normalizes request options and response envelopes.
   - Enforces access policy before business logic runs.

2. **RAG Orchestration Layer**
   - Owns end-to-end RAG request execution.
    - Normalizes caller-supplied manifest payloads through the canonical validator.
    - Applies deterministic tool override routing when `forced_tool` matches an allowed tool.
   - Selects optional tool routing behavior.
   - Coordinates retrieval, governance, and generation phases.
   - Emits lifecycle, telemetry, and completion events.

3. **Retrieval Layer**
   - Resolves retrieval mode from query class and capability state.
   - Retrieves post candidates using lexical, semantic, or hybrid behavior.
   - Expands post candidates into chunk candidates when passage data exists.
   - Applies prefiltering, scoring, reranking, and final evidence selection.

4. **Governance Layer**
   - Applies threshold profiles and acceptance policies.
   - Uses coverage evaluation to determine `full`, `partial`, or `abstain` outcomes.
   - Prevents unsupported synthesis when accepted evidence is empty.
   - Emits policy and retrieval metadata for auditability.

5. **Generation Layer**
   - Resolves the effective prompt.
   - Uses provider abstraction for answer generation.
   - Respects per-model limits and token accounting.
   - Returns answer text and source/reference payloads as separate structures.
    - Emits manifest observability metadata (`manifest`, `manifest_hash`, `manifest_size_bytes`, `manifest_version`) for diagnostics and interaction persistence.

6. **Security and Extension Layer**
   - Applies endpoint access policy defaults and overrides.
   - Exposes hooks for retrieval, governance, response shaping, and custom tools.
   - Allows integrators to extend behavior without editing the core subsystem.

**Mapping to Requirements:**
- AV-02 -> RAG-FR-05 to RAG-FR-22
- AV-02 -> RAG-DR-01 to RAG-DR-10
- AV-02 -> RAG-QR-01 to RAG-QR-09

---

## 4. Runtime Interaction View (AV-03)

### 4.1 Chat Request Flow

```
Client Request
    │
    ▼
Permission Check
    │
    ▼
Request Validation
    │
    ▼
Security Gatekeeper Check
    │
    ├─ unsafe -> policy block response (no tool execution, no retrieval)
    │
    ▼
Deterministic Forced Tool Check
    │
    ├─ summarize_current_entity / recommend_related_content -> execute selected tool directly
    │
    ▼
Optional Agentic Tool Selection
    │
    ├─ direct response / summarize / clarify / compare
    │
    ▼
Retrieval Planning
    │
    ▼
Metadata filter and manifest normalization
    │
    ▼
Post Candidate Retrieval
    │
    ▼
Chunk Expansion + Prefilter
    │
    ▼
Optional Rerank or Deterministic Scoring
    │
    ▼
Acceptance + Coverage Decision
    │
    ├─ abstain -> return response without answer-model call
    │
    ▼
Prompt Resolution + Answer Generation
    │
    ▼
Response Envelope + Metadata
```

### 4.2 User Action Flow

```
Action Request
    │
    ▼
Permission Check
    │
    ▼
Resolve Registered Tool Definitions
    │
    ▼
Reject Unknown or Non-User-Triggerable Action
    │
    ▼
Invoke Custom Tool Handler
    │
    ▼
Return Standard Success/Error Envelope
```

### 4.3 Runtime Rationale

The runtime model prioritizes deterministic safety over best-effort synthesis. Governance executes before final answer generation so no-answer and low-evidence paths are resolved deliberately rather than through accidental model behavior.

**Mapping to Requirements:**
- AV-03 -> RAG-FR-08 to RAG-FR-20
- AV-03 -> RAG-QR-02 to RAG-QR-09

---

## 5. Architectural Decisions

### AD-01: Use a Unified RAG Orchestrator for Retrieval, Governance, and Generation

**Decision:** Centralize end-to-end RAG execution in a single subsystem orchestrator rather than splitting retrieval, governance, and generation into separate externally coordinated flows.

**Alternatives Considered:**
- Separate retrieval and generation services coordinated by callers
- Client-managed sequencing with thin server endpoints

**Rationale:**
- Preserves one authoritative governance path.
- Keeps metadata and response semantics consistent across clients.
- Reduces drift between endpoint behavior and internal subsystem behavior.

**Consequences:**
- The orchestrator becomes the primary change surface for RAG behavior.
- Internal complexity increases, but client integrations stay simpler.

**Linked Requirements:** AD-01 -> RAG-FR-05, RAG-FR-14, RAG-FR-15, RAG-FR-16, RAG-DR-03

### AD-02: Treat Agentic Routing and Reranking as Optional Capabilities

**Decision:** Optional agentic routing and reranking are integrated into the architecture but are not prerequisites for successful request handling.

**Alternatives Considered:**
- Require an agentic model for all requests
- Require reranking for all final evidence selection

**Rationale:**
- Supports deployments with minimal model configuration.
- Preserves deterministic baseline behavior when optional capabilities are unavailable.
- Aligns subsystem behavior with release-quality consistency requirements.

**Consequences:**
- Additional branching exists in runtime flow.
- Metadata must clearly expose active and inactive capability states.

**Linked Requirements:** AD-02 -> RAG-FR-08, RAG-FR-09, RAG-FR-12, RAG-FR-13, RAG-OR-03, RAG-OR-07, RAG-QR-05

### AD-03: Make Governance Authoritative Before Synthesis

**Decision:** Apply acceptance and coverage decisions before answer generation, with abstain short-circuit behavior when accepted evidence is empty.

**Alternatives Considered:**
- Always generate an answer and let prompt instructions attempt abstention
- Apply only soft governance after model generation

**Rationale:**
- Reduces unsupported answers.
- Creates explicit, inspectable decision points for release validation.
- Preserves truthful abstention behavior.

**Consequences:**
- More policy metadata must be carried in responses.
- Tuning thresholds and compare behavior becomes a first-class architecture concern.

**Linked Requirements:** AD-03 -> RAG-FR-14, RAG-FR-15, RAG-FR-16, RAG-DR-04, RAG-DR-05, RAG-QR-02, RAG-QR-03, RAG-QR-04

### AD-04: Use One Tool Registry for Both Agentic and User-Triggered Actions

**Decision:** Define tool capabilities once and reuse them for model-selected tools and direct user-triggerable actions.

**Alternatives Considered:**
- Separate internal tool registry and external action registry
- Hardcode direct action endpoints per tool

**Rationale:**
- Keeps action discovery and execution aligned with runtime tool semantics.
- Simplifies extension for integrators.
- Preserves a single handler model for custom tools.

**Consequences:**
- Direct action execution depends on tool-definition discipline.
- User-triggerable tools need explicit opt-in metadata.

**Linked Requirements:** AD-04 -> RAG-FR-18, RAG-FR-19, RAG-FR-20, RAG-FR-21, RAG-DR-09

### AD-05: Treat Metadata as an Architectural Output, Not Incidental Logging

**Decision:** Retrieval, governance, capability-state, and timing metadata are part of the subsystem architecture and response contract.

**Alternatives Considered:**
- Basic answer-only responses
- Internal logs without response-level observability

**Rationale:**
- Supports debugging, benchmark evaluation, and governance review.
- Enables parity checks across interfaces and releases.
- Makes fallback behavior explicit to consumers and reviewers.

**Consequences:**
- Response contracts must remain stable.
- Documentation must distinguish architectural metadata from UI-only presentation.
- Interaction payload contracts must preserve `search.metadata_filter` visibility for diagnostics.

**Linked Requirements:** AD-05 -> RAG-DR-01, RAG-DR-03, RAG-DR-04, RAG-DR-05, RAG-DR-06, RAG-DR-12, RAG-QR-06, RAG-QR-07

### 5.2 Route-Policy Matrix (AV-05)

The RAG subsystem exposes multiple surface entry points (REST chat/action/actions, journey endpoints, and SSE streaming) with distinct permission seed defaults and caller context:

| Route | Controller | HTTP Method | Auth Context | Permission Seed | Rate Limiting | Notes |
|---|---|---|---|---|---|---|
| `POST /rag/chat` | RAG Controller | POST | Caller (logged-in or guest session) | Fail-Closed (False) | Fixed-window per-route | Chat may be public-eligible; guest session validates ownership via session hash |
| `POST /rag/action` | RAG Controller | POST | Caller (logged-in or guest session) | Fail-Closed (False) | Fixed-window per-route | Action execution requires caller capability or public override; guest session validates ownership |
| `GET /rag/actions` | RAG Controller | GET | Caller (logged-in or guest session) | Fail-Closed (False) | Per-scope default | Action discovery lists available tools; guest session does not require ownership for discovery |
| `POST /rag/journey/issue` | Journey Controller | POST | Caller (guest session or new visitor) | Fail-Open (True) | Throttled by default | New or existing journey entry point; first-time users granted access unless blocked by override hook |
| `POST /rag/journey/consume` | Journey Controller | POST | Caller (guest session only) | Fail-Open (True) | Per-scope default | Append to journey; must validate caller session hash matches stored journey ownership hash; supports first-claim legacy binding |
| `GET /rag/journey/history` | Journey Controller | GET | Caller (guest session only) | Fail-Open (True) | Per-scope default | Journey interaction history; must validate caller session hash matches stored journey ownership hash; supports first-claim legacy binding |
| `GET /rag/stream` (SSE) | SSE Handler | GET | Caller (logged-in or guest session) | Fail-Open (True) | Generic rate-limit filter | SSE streaming; guest session hashed for session-bound continuity when applicable |

**Permission Seed Semantics:**
- **Fail-Closed (False)**: Default deny. Endpoint returns 403 Forbidden unless the `gg_data_rag_endpoint_permission` filter explicitly grants access (e.g., for public chat or capability-gated actions).
- **Fail-Open (True)**: Default allow. Endpoint grants access unless the `gg_data_rag_endpoint_permission` filter explicitly blocks access (e.g., to revoke guest journey access during maintenance).

**Guest Session Ownership:**
- Guest callers are identified by a hashed session credential computed from the WordPress authentication salts and stored in a browser cookie.
- Journey endpoints use this hash to enforce session-bound interaction visibility (RAG-DR-14, RAG-OR-09).
- First-claim binding for legacy records allows a guest without a stored session hash to claim ownership on first interaction (RAG-DR-15).

**Mapping to Requirements:**
- AV-05 -> RAG-FR-30 to RAG-FR-33
- AV-05 -> RAG-DR-14, RAG-DR-15
- AV-05 -> RAG-OR-09

---

## 6. Constraints and Assumptions

### 6.1 Constraints

- The architecture must work with multiple configured model providers rather than a single vendor-specific stack.
- Access control must remain configurable while preserving fail-closed evaluation and logged-in default policy.
- The subsystem must preserve stable metadata and policy semantics because roadmap acceptance checks depend on them.
- Retrieval and governance behavior must tolerate absent optional capabilities without collapsing the request flow.

### 6.2 Assumptions

- The RAG subsystem operates inside the broader plugin and depends on configured connections, model registry data, and synchronized content.
- Upstream requirements are represented only by the RAG SRS and planning artifacts; no separate SyRS currently exists.
- Legacy narrative content in the root RAG architecture document remains available during migration but is no longer the target final architecture artifact.

---

## 7. Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Optional capability branching causes behavior drift between configurations | Medium | Preserve explicit capability-state metadata and deterministic baseline behavior |
| Governance tuning changes may accidentally weaken abstain guarantees | High | Keep acceptance authoritative and expose stable policy IDs and thresholds |
| Access policy can be configured too open in production | High | Preserve logged-in default, fail-closed permission flow, and documented hardening hooks |
| Legacy root architecture narrative diverges from split-doc package | Medium | Use this document as the new architecture source of truth and retire legacy content after migration review |
| Response metadata grows without clear ownership | Medium | Treat metadata as a documented architecture output tied to release validation |
| Metadata filter object/array drift across transports | High | Keep empty-filter object semantics and parity checks across REST, SSE, and provider payload builders |

---

## 8. Architecture Coverage Mapping

| Architecture Item | Requirement Coverage |
|---|---|
| AV-01 Context View | RAG-FR-01 to RAG-FR-21; RAG-OR-01 to RAG-OR-07 |
| AV-02 Component View | RAG-FR-05 to RAG-FR-22; RAG-DR-01 to RAG-DR-10 |
| AV-03 Runtime View | RAG-FR-08 to RAG-FR-20; RAG-QR-02 to RAG-QR-09 |
| AD-01 Unified Orchestrator | RAG-FR-05, RAG-FR-14, RAG-FR-15, RAG-FR-16, RAG-DR-03 |
| AD-02 Optional Capabilities | RAG-FR-08, RAG-FR-09, RAG-FR-12, RAG-FR-13, RAG-OR-03, RAG-OR-07, RAG-QR-05 |
| AD-03 Governance Before Synthesis | RAG-FR-14, RAG-FR-15, RAG-FR-16, RAG-DR-04, RAG-DR-05, RAG-QR-02, RAG-QR-03, RAG-QR-04 |
| AD-04 Unified Tool Registry | RAG-FR-18, RAG-FR-19, RAG-FR-20, RAG-FR-21, RAG-DR-09 |
| AD-05 Metadata as Architecture Output | RAG-DR-01, RAG-DR-03, RAG-DR-04, RAG-DR-05, RAG-DR-06, RAG-QR-06, RAG-QR-07 |

---

## 9. Readiness and Handoff

### 9.1 Architecture Readiness Check

- Scope and subsystem boundary are defined.
- Major architectural views are documented.
- Major decisions are linked to SRS requirement IDs.
- Constraints, assumptions, and risks are recorded.
- Remaining migration work is identified.

### 9.2 Remaining Gaps

- Developer-facing examples, hook recipes, and troubleshooting remain outside architecture scope and belong in the RAG developer reference.

### 9.3 Handoff

Next downstream artifact: [Developer Documentation](developer-documentation.md)

---

This architecture document is the canonical architecture reference for the RAG subsystem.