# Architecture Description: REST API Subsystem

Standard: ISO/IEC/IEEE 42010:2022  
Component: REST API Subsystem (`gg-data/v1`)  
Version: 1.0.0  
Date: 2026-03-30  
Upstream Specification: [srs.md](srs.md)

---

## 1. Architecture Overview

### 1.1 Purpose and Scope

This architecture describes how Gregius Data exposes plugin capabilities through a versioned WordPress REST API surface implemented by multiple domain-specific controllers.

Scope includes:
- Route registration and namespace/version strategy
- Controller decomposition by operational domain
- Permission and access models
- Request lifecycle from route match to response emission
- Cross-cutting constraints and risks for parity and maintainability

Explicitly excluded:
- End-user usage walkthroughs
- Full endpoint cookbook detail
- Internal implementation of non-REST subsystems (sync internals, provider internals, RAG orchestration internals)

### 1.2 Stakeholders and Concerns

| Stakeholder | Concern | Priority |
|---|---|---|
| Plugin developers | Clear route ownership and extension points | High |
| Dashboard developers | Stable request/response contracts | High |
| Operations | Diagnosable endpoint behavior and health surfaces | High |
| Security reviewers | Permission consistency and least-privilege behavior | High |
| Product owners | Backward compatibility within API version | Medium |

---

## 2. Context View (AV-01)

```
┌───────────────────────────────────────────────────────────────────────┐
│                           WordPress Runtime                           │
│                                                                       │
│  Dashboard UI / Frontend / Integrations                               │
│             │                                                         │
│             ▼                                                         │
│      /wp-json/gg-data/v1/*                                            │
│             │                                                         │
│             ▼                                                         │
│   GG_Data_REST_API Route Registration Layer                           │
│             │                                                         │
│   ┌─────────┴──────────────────────────────────────────────────────┐  │
│   │ Domain Controllers                                             │  │
│   │ settings, connections, schema, sync, search, vectors, logs,   │  │
│   │ prompts, models, rag, retry-queue, sync-validation, etc.      │  │
│   └─────────┬──────────────────────────────────────────────────────┘  │
│             │                                                         │
│             ▼                                                         │
│      Plugin Services and Managers                                    │
│      (settings manager, db layer, sync services, rag services)       │
└───────────────────────────────────────────────────────────────────────┘
```

Architecture intent:
- Keep integration boundary stable through versioned namespace.
- Keep endpoint complexity partitioned by domain controller.
- Keep permission checks explicit at route level.

Mapping:
- AV-01 -> REST-FR-01, REST-FR-02, REST-FR-03, REST-QR-01

---

## 3. Component View (AV-02)

### 3.1 Component Decomposition

1. **Bootstrap and Registry Component**
   - `GG_Data_REST_API` binds `rest_api_init` and registers domain controllers.

2. **Administrative Controllers**
   - Settings, connections, connection health/models, schema, sync, sync validation, search, vectors, vocabulary, retry queue, models, prompts, logs.
   - Predominantly admin-gated using capability checks.
   - Logs controller delegates retention purge behavior to logger/runtime contracts, including site-scoped table boundaries.
   - Vector controller includes timeout-safe batch deletion semantics for large vector tables.

3. **Runtime Interaction Controllers**
   - RAG controller with filter-driven endpoint permission policy.
   - Interactions controller based on ownership-aware CPT access model with admin-governed direct create and explicit multisite site context for super admins.

4. **Streaming Component**
   - SSE handler for streaming chat behavior with optional hook-driven rate-limit checks.

### 3.2 Permission Model Components

- **Admin capability model:** most operational controllers use admin checks (`manage_options` or equivalent wrappers).
- **Filter-policy model:** RAG uses `gg_data_rag_endpoint_permission` and optional security hooks/settings.
- **Ownership model:** interactions endpoints allow users to access their own records (read/update/delete), while direct create is admin-governed and non-admin creation is chat-lifecycle driven.

Mapping:
- AV-02 -> REST-FR-04 to REST-FR-16
- AV-02 -> REST-OR-01 to REST-OR-04

---

## 4. Runtime Interaction View (AV-03)

### 4.1 Standard Request Lifecycle

```
HTTP Request
   │
   ▼
Route Resolution (namespace + route pattern)
   │
   ▼
Permission Callback
   │
   ├─ deny -> WP_Error response
   │
   ▼
Argument Sanitization / Validation
   │
   ├─ invalid -> WP_Error response
   │
   ▼
Controller Callback
   │
   ▼
Service/Manager Invocation
   │
   ▼
Structured Success/Error Response
```

### 4.2 Variant Flows

- **RAG flow:** route permission can be tightened via filter/security hooks and includes specialized response metadata contracts.
- **RAG metadata flow:** optional `metadata_filter` and `metadata_manifest` request payload fields are sanitized at entry and propagated to runtime retrieval options.
- **Interactions flow:** permission logic depends on authentication, post ownership, direct-create restriction for non-admin users, and optional super-admin multisite site context.
- **Streaming flow:** SSE requests can be gated by `gg_data_rag_rate_limit` filter.
- **Logs purge flow:** `DELETE /logs/purge` delegates to logger retention purge for current site context and returns deleted row counts.
- **Vector batch delete flow:** `POST /vectors/batch-delete` performs iterative delete-safe progress operations and preserves provider parity across PDO and PostgREST/Supabase.

Mapping:
- AV-03 -> REST-FR-04, REST-FR-06, REST-FR-07, REST-DR-03, REST-OR-02, REST-OR-03

---

## 5. Architectural Decisions (ADRs)

### AD-01: Versioned Namespace as Primary Compatibility Boundary

Decision:
- Use `gg-data/v1` as the single namespace for current REST contracts.

Rationale:
- Keeps route ownership unified.
- Supports future major-version migration without route ambiguity.

Consequences:
- Backward-compatible changes should remain within `v1`.
- Breaking changes should trigger version strategy review.

Linked requirements:
- AD-01 -> REST-FR-01, REST-FR-17, REST-QR-01, REST-QR-04

### AD-02: Domain-Specific Controller Decomposition

Decision:
- Split endpoints across controller classes by domain rather than one monolithic controller.

Rationale:
- Reduces coupling and keeps permission/argument logic local to each domain.
- Improves maintainability and parity reviewability.

Consequences:
- Cross-controller consistency must be actively managed.
- Documentation must maintain grouped route catalogs.

Linked requirements:
- AD-02 -> REST-FR-02, REST-FR-03, REST-FR-09 to REST-FR-16, REST-QR-08

### AD-03: Explicit Permission Callback per Route

Decision:
- Require explicit permission callback declarations per route.

Rationale:
- Makes access behavior auditable.
- Avoids implicit defaults that can drift with framework assumptions.

Consequences:
- Permission divergence is possible by design and must be documented.

Linked requirements:
- AD-03 -> REST-FR-04, REST-FR-05, REST-FR-06, REST-FR-07, REST-OR-01, REST-OR-02

### AD-04: Specialized Access Models for RAG and Interactions

Decision:
- Preserve domain-specific access models:
  - Filter-driven RAG permission policy.
   - Ownership-aware interactions access with constrained direct-create and multisite super-admin governance context.

Rationale:
- RAG and interactions have user-facing runtime needs different from admin-only operational endpoints.
- Interaction creation through chat lifecycle reduces contract drift and audit ambiguity for non-admin actor paths.

Consequences:
- Security hardening policy must explicitly cover these exceptions.
- Developer docs must call out different authentication and authorization expectations.

Linked requirements:
- AD-04 -> REST-FR-06, REST-FR-07, REST-OR-02, REST-OR-03, REST-OR-04

### AD-05: Controller Layer Does Not Imply Global CORS/Rate-Limit Middleware

Decision:
- Treat CORS and generic endpoint throttling as deployment or extension concerns unless explicitly implemented in runtime controller/SSE flow.

Rationale:
- Prevents stale architectural claims that are not enforced in code.

Consequences:
- Canonical docs must not describe these as implemented universal capabilities.
- Release hardening may need additional middleware or plugin-level hooks.

Linked requirements:
- AD-05 -> REST-OR-06, REST-OR-07, REST-QR-07

### AD-06: Operational Batch Delete Uses Backend-Owned Destructive Pagination

Decision:
- The REST API vector batch delete route owns destructive pagination semantics on the server and does not trust client offsets as selection cursors.

Rationale:
- Shrinking datasets make naive offset-based deletes unsafe and can cause stalled or looping clients.

Consequences:
- Route contracts expose progress metadata while backend selects from the current remaining set each batch.
- Provider-specific implementations must preserve equivalent behavior for PDO and PostgREST/Supabase.

Linked requirements:
- AD-06 -> REST-FR-19, REST-DR-14, REST-OR-08, REST-QR-09

---

## 6. Constraints, Risks, and Mitigations

### 6.1 Constraints

- Namespace compatibility must be preserved for existing clients.
- Permission behavior is distributed across controllers and must remain explicit.
- Non-admin runtime endpoints require stricter parity and security review.
- CORS and broad global throttling cannot be assumed from documentation alone.
- Route-scoped throttling is implemented for RAG `POST /rag/chat` and `POST /rag/action`.

### 6.2 Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Documentation drift across many controllers | High | Maintain a route inventory and traceability-driven docs updates |
| Security posture confusion for RAG and interactions | High | Keep explicit ADRs and developer guidance for access models |
| Stale claims of rate limiting/CORS | Medium | Treat as constraints unless runtime implementation is verified; document route-scoped exceptions explicitly |
| Inconsistent response/error envelopes | Medium | Include response contract requirements in SRS and developer docs |

---

## 7. Architecture Coverage Mapping

| Architecture Item | Requirement Coverage |
|---|---|
| AV-01 Context View | REST-FR-01 to REST-FR-03, REST-QR-01 |
| AV-02 Component View | REST-FR-02 to REST-FR-16, REST-OR-01 to REST-OR-04 |
| AV-03 Runtime View | REST-FR-04, REST-FR-06, REST-FR-07, REST-DR-03 |
| AD-01 Namespace Strategy | REST-FR-01, REST-FR-17, REST-QR-01, REST-QR-04 |
| AD-02 Controller Decomposition | REST-FR-02, REST-FR-03, REST-FR-09 to REST-FR-16 |
| AD-03 Permission Per Route | REST-FR-04, REST-FR-05, REST-FR-06, REST-FR-07, REST-OR-01 |
| AD-04 Access Model Exceptions | REST-FR-06, REST-FR-07, REST-OR-02 to REST-OR-04 |
| AD-05 CORS/Rate-Limit Constraint | REST-OR-06, REST-OR-07, REST-QR-07 |

---

## 8. Readiness and Handoff

Readiness status:
- Context, component, and runtime views are defined.
- Major architectural decisions are documented and mapped to SRS IDs.
- Risks and constraints are explicit for parity-sensitive areas.

Remaining migration notes:
- Canonical architecture scope for this subsystem is maintained in this file.
- Historical root architecture narrative has been retired in favor of the split package.

Downstream artifact:
- [developer-documentation.md](developer-documentation.md)
