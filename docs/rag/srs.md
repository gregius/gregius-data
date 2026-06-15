# Software Requirements Specification (SRS)

Standard: ISO/IEC/IEEE 29148:2018

## Document Information

| Field | Value |
|---|---|
| Project | Gregius Data |
| Software Component | Retrieval-Augmented Generation (RAG) |
| Version | 1.0 |
| Date | 2026-03-30 |
| Author | Gregius |
| StRS Reference | N/A - no standalone Stakeholder Requirements Specification is maintained for this subsystem |
| SyRS Reference | N/A - SRS produced in SRS-first mode for an existing subsystem |
| Status | Draft |

## 1. Introduction

### 1.1 System Purpose

This SRS defines what the Gregius Data RAG subsystem must do to provide grounded question answering over WordPress site content using configurable retrieval, generation, and governance behavior.

### 1.2 System Scope

The software component includes retrieval planning, candidate retrieval, passage selection, optional agentic tool routing, optional reranking, governance decisions, answer generation, source attribution, and externally exposed RAG interaction endpoints.

Software identifier: gregius-data-rag

Repository path: public/wp-content/plugins/gregius-data

### 1.3 System Overview

#### 1.3.1 System Context

The RAG subsystem is a plugin feature that enables conversational access to synchronized site content and returns grounded answers or explicit abstentions when acceptable evidence is unavailable.

#### 1.3.2 System Functions Summary

- Accept RAG requests with configurable retrieval and model inputs.
- Retrieve relevant evidence from synchronized WordPress content using lexical, semantic, or hybrid search behavior.
- Support optional agentic tool routing and optional reranking without making either capability mandatory.
- Apply governance rules before synthesis to determine whether to answer, partially answer, or abstain.
- Generate answer payloads with source attribution and metadata for UI, API, and observability consumers.
- Expose REST contracts for answer generation, user-triggered actions, and action discovery.

#### 1.3.3 User Characteristics

| User Class | Technical Level | Primary Interaction Point |
|---|---|---|
| Site Visitor or Frontend User | Non-technical | Chat UI or frontend block |
| Site Administrator | Technical/Operational | Plugin settings and access controls |
| Plugin Developer or Integrator | Technical | REST API, hooks, and extension surface |

### 1.4 Definitions

| Term | Definition |
|---|---|
| RAG request | One user query processed through retrieval, governance, and answer generation |
| Agentic routing | Optional model-assisted tool selection before retrieval or response execution |
| Reranking | Optional second-stage scoring that reorders evidence candidates for higher precision |
| Governance decision | The subsystem decision to answer fully, answer partially, or abstain based on evidence quality |
| Passage retrieval | Expansion from post-level candidates into chunk-level evidence for final context assembly |
| Evidence source | A cited post or chunk used to support a returned answer |

### 1.5 Abbreviations and Acronyms

| Abbreviation | Expansion |
|---|---|
| SRS | Software Requirements Specification |
| RAG | Retrieval-Augmented Generation |
| REST | Representational State Transfer |
| LLM | Large Language Model |
| FTS | Full-Text Search |

## 2. References

- docs/rag/architecture.md
- docs/rag/manifest-contract.md
- docs/hooks/architecture.md
- includes/rag/class-gg-data-rag-service.php
- includes/rag/class-gg-data-coverage-gate.php
- includes/api/class-gg-data-rest-rag-controller.php
- includes/hooks/class-gg-data-rag-security-hooks.php

## 3. Software Requirements

Requirement notation:
- MUST: mandatory
- SHOULD: strongly recommended
- MAY: optional

### 3.1 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| RAG-FR-01 | The software MUST accept a RAG chat request containing a user query, connection identifier, embedding model identifier, and answer-model identifier before answer generation starts. | Must |
| RAG-FR-02 | The software MUST reject a RAG chat request with a client error response when any required chat input is missing. | Must |
| RAG-FR-03 | The software MUST support optional request inputs for retrieval volume, retrieval scope, model temperature, prior messages, prompt selection, conversation correlation, optional agentic routing, and optional reranking. | Must |
| RAG-FR-04 | The software MUST default retrieval scope to the synchronized content types configured for the selected connection when request-specific content types are not supplied. | Must |
| RAG-FR-05 | The software MUST retrieve candidate evidence using lexical, semantic, or hybrid retrieval behavior, with the resolved mode determined by request context and available capabilities. | Must |
| RAG-FR-06 | The software MUST support embedding-model selection as a runtime input and MUST NOT hardcode retrieval to a single embedding implementation. | Must |
| RAG-FR-07 | The software MUST support answer generation with multiple LLM providers and model identifiers that are configured in the plugin. | Must |
| RAG-FR-08 | The software MUST support optional agentic routing that can select among search, compare, direct-response, conversation-summary, and clarification behaviors before retrieval executes. | Must |
| RAG-FR-09 | When agentic routing is not configured or does not change execution behavior, the software MUST continue with standard search-based RAG processing. | Must |
| RAG-FR-10 | The software MUST support compare-style requests that evaluate evidence across multiple entities and return a compare-aware response path. | Must |
| RAG-FR-11 | The software MUST expand post-level retrieval candidates into passage-level evidence when chunk data is available and MUST fall back to document-level context when passage data is unavailable. | Must |
| RAG-FR-12 | The software MUST support optional reranking of evidence candidates when a rerank model is configured and candidate volume meets the minimum execution policy. | Must |
| RAG-FR-13 | When reranking is unavailable, skipped, or fails, the software MUST continue using deterministic non-rerank scoring rather than failing the request. | Must |
| RAG-FR-14 | The software MUST apply an evidence-acceptance step before synthesis so only candidates meeting the active acceptance policy are considered final evidence. | Must |
| RAG-FR-15 | The software MUST apply a coverage decision for supported intents and classify evidence sufficiency as full, partial, or abstain before final answer synthesis. | Must |
| RAG-FR-16 | The software MUST abstain when no accepted evidence remains after governance evaluation and MUST short-circuit answer-model invocation in that case. | Must |
| RAG-FR-17 | The software MUST return source attribution data separately from answer text so consuming interfaces can render citations or references independently of prose. | Must |
| RAG-FR-18 | The software MUST expose a REST endpoint for chat-based answer generation. | Must |
| RAG-FR-19 | The software MUST expose a REST endpoint for direct execution of user-triggerable RAG actions. | Must |
| RAG-FR-20 | The software MUST expose a REST endpoint that lists available user-triggerable RAG actions for a connection context. | Must |
| RAG-FR-21 | The software MUST support custom or extended tool behaviors through a filter-based extension surface without requiring core modification. | Must |
| RAG-FR-22 | The software SHOULD emit progress and lifecycle events that allow streaming and interaction-tracking consumers to observe request progress. | Should |
| RAG-FR-23 | The software MUST accept optional structured metadata inputs (`metadata_filter`, `metadata_manifest`) and preserve deterministic retrieval behavior when those fields are absent. | Must |
| RAG-FR-24 | The software MUST accept an optional request `manifest` payload and normalize it against a canonical schema contract before downstream tool handlers consume it. | Must |
| RAG-FR-25 | The software MUST support an optional deterministic tool override (`forced_tool`) that executes prior to agentic routing when the value matches an allowed tool. | Must |
| RAG-FR-26 | The software MUST expose `summarize_current_entity` as a first-party tool that summarizes the active entity identified by request manifest or source context. | Must |
| RAG-FR-27 | The software MUST expose `recommend_related_content` as a first-party tool that derives scoped retrieval filters from manifest taxonomies. | Must |
| RAG-FR-28 | The software MUST enforce deterministic no-op behavior for unknown or unsupported `forced_tool` values by continuing standard tool-selection flow. | Must |
| RAG-FR-29 | The software MUST keep deterministic tool and manifest contracts reusable by external clients without requiring Gregius Intelligence-specific runtime dependencies. | Must |
| RAG-FR-30 | The software MUST expose a REST endpoint for session-bound guest journey continuity that accepts a reference identifier, optional block metadata, and returns a journey interaction record for first-time or prior session users. | Must |
| RAG-FR-31 | The software MUST expose a REST endpoint to retrieve and append RAG interactions to an existing session-bound journey record via a session hash ownership mechanism that validates caller session state. | Must |
| RAG-FR-32 | The software MUST expose a REST endpoint to list RAG interactions within a session-bound journey that applies guest session hash validation and returns interaction history scoped to the caller's session. | Must |
| RAG-FR-33 | The software MUST support per-surface permission seed behavior where RAG chat and action endpoints use fail-closed default permission (deny unless explicitly allowed), and journey and SSE endpoints use fail-open default permission (allow unless explicitly denied). | Must |

### 3.2 Data and Contract Requirements

| ID | Requirement | Priority |
|---|---|---|
| RAG-DR-01 | The chat response contract MUST return a success indicator and a structured data payload when answer generation succeeds. | Must |
| RAG-DR-02 | The response payload MUST include answer text or abstain text and MUST include source attribution data when supporting evidence is returned. | Must |
| RAG-DR-03 | The response payload MUST include metadata describing retrieval behavior, selected models, timing, and governance outcome so downstream consumers can inspect the decision path. | Must |
| RAG-DR-04 | Governance metadata MUST expose stable semantic policy identifiers for retrieval and rerank behavior rather than rollout-specific identifiers. | Must |
| RAG-DR-05 | Retrieval metadata MUST expose capability-state fields sufficient to determine whether lexical retrieval, embedding retrieval, agentic routing, and reranking were active for the request. | Must |
| RAG-DR-06 | Compare-style responses MUST preserve enough metadata to distinguish accepted evidence counts, fallback behavior, and slot-coverage decisions from normal single-answer flows. | Must |
| RAG-DR-07 | The software MUST preserve a separate mechanism for citation or reference ordering so clients can render citations consistently with the response text. | Must |
| RAG-DR-08 | The software MUST sanitize caller-supplied prior messages to restrict message roles to supported values and to sanitize message content before use. | Must |
| RAG-DR-09 | The software MUST expose an externally consumable contract for action discovery that identifies the action name, purpose, and required input schema for user-triggerable actions. | Must |
| RAG-DR-10 | The software MUST return structured error responses for missing input, unknown actions, unauthorized access, and other request failures. | Must |
| RAG-DR-11 | RAG chat and action endpoints MUST return structured 429 responses when route-scoped rate limits are exceeded, including retry and window metadata for clients. | Must |
| RAG-DR-12 | Metadata-filter transport MUST preserve empty-filter object semantics for JSONB containment (`{}`), and response metadata used by interaction tracking MUST expose the effective filter payload for observability. | Must |
| RAG-DR-13 | Successful tool or response payload metadata MUST include manifest observability fields (`manifest`, `manifest_hash`, `manifest_size_bytes`, `manifest_version`) when manifest context is present. | Must |
| RAG-DR-14 | Guest journey interactions MUST be persisted with a session-hash ownership credential that identifies the guest user, and all subsequent reads or appends MUST validate that the caller's current session hash matches the stored hash or qualifies as a first-claim legacy record before granting access. | Must |
| RAG-DR-15 | The software MUST support first-claim binding semantics for legacy guest conversation records (records created before session-hash ownership was implemented) such that a guest without a stored hash can assume ownership on first interaction only, and subsequent reads enforce session-hash validation. | Must |

### 3.3 Software Operations and Control Requirements

| ID | Requirement | Priority |
|---|---|---|
| RAG-OR-01 | The software MUST support configurable endpoint access control with public, authenticated-user, and capability-gated modes, while preserving fail-closed permission evaluation and logged-in default policy. | Must |
| RAG-OR-02 | The software MUST allow runtime access control overrides through the plugin extension surface without requiring source modification. | Must |
| RAG-OR-03 | The software MUST remain functional when optional agentic routing and reranking capabilities are absent. | Must |
| RAG-OR-04 | The software MUST support request handling without requiring server-side conversation persistence; prior conversation context MAY be supplied by the caller. | Must |
| RAG-OR-05 | The software MUST support configurable model context and output-token limits for generation behavior. | Must |
| RAG-OR-06 | The software SHOULD support request origins from multiple plugin interfaces while preserving a unified interaction and metadata contract. | Should |
| RAG-OR-07 | The software MUST preserve deterministic behavior when optional retrieval-enhancement capabilities are disabled or unavailable. | Must |
| RAG-OR-08 | The software MUST enforce route-scoped fixed-window throttling for RAG `POST /rag/chat` and `POST /rag/action`, with extension hooks for per-scope limits and time window values. | Must |
| RAG-OR-09 | The software MUST compute and validate guest session hashes using WordPress authentication salts to prevent tampering and MUST enforce hash validation on all journey endpoint requests from unauthenticated callers. | Must |

### 3.4 Quality and Non-Functional Requirements

| ID | Requirement | Verification Method |
|---|---|---|
| RAG-QR-01 | The subsystem MUST remain embedding-agnostic so the same retrieval and generation flow can operate with different configured embedding models. | Configuration inspection + functional run |
| RAG-QR-02 | The subsystem MUST preserve truthful abstention behavior when acceptable evidence is unavailable and MUST NOT fabricate source-backed answers in that state. | Response review + metadata inspection |
| RAG-QR-03 | Single-path evidence acceptance MUST be authoritative; when no candidate passes acceptance, the subsystem MUST not silently fall back to unsupported synthesis. | Response review + metadata inspection |
| RAG-QR-04 | Compare-mode fallback-to-top-candidates MUST remain disabled by default so compare acceptance remains deterministic unless explicitly overridden by operators. | Metadata inspection + controlled override test |
| RAG-QR-05 | The subsystem MUST support capability-conditional governance so structural quality rules continue to apply even when optional rerank or agentic components are absent. | Configuration variation test |
| RAG-QR-06 | The subsystem SHOULD provide retrieval-funnel observability sufficient to diagnose candidate counts, acceptance filtering, rerank execution, and final evidence selection. | Metadata inspection |
| RAG-QR-07 | The subsystem SHOULD preserve stable policy and metadata semantics across supported interfaces so benchmark and release validation remain comparable. | Cross-interface contract inspection |
| RAG-QR-08 | The subsystem MUST return explicit authorization failures when access policy denies endpoint usage. | Permission test |
| RAG-QR-09 | The subsystem MUST avoid fatal failure when retrieval backends, chunk data, or rerank execution are unavailable and MUST use documented fallback behavior instead. | Failure-mode test |
| RAG-QR-10 | The subsystem SHOULD support benchmarkable behavior across single, compare, ambiguous, and no-evidence prompt classes. | Benchmark evidence review |

## 4. Verification and Acceptance

| Requirement Group | Verification Method | Tool or Evidence |
|---|---|---|
| Functional requirements | Endpoint and service execution inspection | REST request testing + runtime result review |
| Data and contract requirements | Response schema and metadata inspection | JSON response review + source attribution review |
| Operations requirements | Configuration variation and permission checks | Settings inspection + access-control tests |
| Quality requirements | Governance and benchmark behavior review | Metadata inspection + benchmark evidence snapshot |

Acceptance baseline for current implemented subsystem:
- MVP roadmap expectations require correct handling across single, compare, ambiguous, no-evidence, and one-sided evidence scenarios.
- Metadata presence, deterministic fallback behavior when optional models are missing, and truthful abstain behavior are release-relevant quality gates.

## 5. Traceability

| SRS Requirement IDs | Source References | Notes |
|---|---|---|
| RAG-FR-01 to RAG-FR-04 | docs/rag/architecture.md (Overview, Context View), includes/api/class-gg-data-rest-rag-controller.php | Chat input contract and default retrieval scope |
| RAG-FR-05 to RAG-FR-17 | docs/rag/architecture.md (Component View, Runtime View, ADRs), includes/rag/class-gg-data-rag-service.php, includes/rag/class-gg-data-coverage-gate.php | Retrieval, passage selection, governance, abstain, and source behavior |
| RAG-FR-18 to RAG-FR-23 | docs/rag/architecture.md (Component responsibilities and extension boundaries), includes/api/class-gg-data-rest-rag-controller.php, includes/api/class-gg-data-sse-handler.php, includes/rag/class-gg-data-rag-service.php | External endpoints, metadata transport, and extension behavior |
| RAG-FR-24 to RAG-FR-29 | docs/rag/architecture.md (manifest contract and deterministic routing), includes/rag/class-gg-data-manifest-validator.php, includes/rag/class-gg-data-rag-service.php | Manifest normalization contract and deterministic tool execution behavior |
| RAG-FR-30 to RAG-FR-33 | docs/rag/architecture.md (Journey continuity component and per-surface permission policy), includes/api/class-gg-data-rest-rag-journey-controller.php, includes/class-gg-data-interaction.php | Guest journey endpoints, session-bound continuity, and permission seed semantics |
| RAG-FR-22, RAG-DR-01 to RAG-DR-12 | docs/rag/architecture.md (Runtime flow and metadata architecture), includes/api/class-gg-data-rest-rag-controller.php, includes/api/class-gg-data-sse-handler.php, includes/rag/class-gg-data-rag-service.php, includes/class-gg-data-interaction.php | Response schema, metadata, sanitation, observability, and errors |
| RAG-DR-13 | docs/rag/architecture.md (response metadata contract), includes/rag/class-gg-data-rag-service.php, includes/class-gg-data-interaction.php | Manifest metadata observability and persistence |
| RAG-DR-14, RAG-DR-15 | docs/rag/architecture.md (Security and journey ownership), includes/api/class-gg-data-rest-rag-journey-controller.php, includes/class-gg-data-interaction.php | Guest session hash ownership, first-claim binding for legacy records |
| RAG-OR-01 to RAG-OR-09 | docs/rag/architecture.md (Security and extension layer), includes/hooks/class-gg-data-rag-security-hooks.php, includes/api/class-gg-data-rest-rag-controller.php, includes/api/class-gg-data-rest-rag-journey-controller.php, includes/rag/class-gg-data-rag-service.php, includes/class-gg-data-interaction.php | Access control, stateless handling, route-scoped throttling, optional capability behavior, guest session hashing |
| RAG-QR-01 to RAG-QR-10 | docs/rag/architecture.md | Non-functional behavior and release-quality expectations |

## 6. Appendices

### 6.1 Assumptions and Dependencies

- This SRS is generated in SRS-first default mode without separate BRS, StRS, OpsCon, or SyRS artifacts.
- The current implementation and planning artifacts are treated as the best available upstream source of truth for reverse-engineered software requirements.
- Detailed hook names, class contracts, and implementation recipes are intentionally deferred to future RAG architecture and developer-documentation artifacts.
- Business rationale for embedding agnosticism and multi-provider support is inferred from planning documents and current product direction rather than a separate approved business-requirements package.

### 6.2 Open Issues

| ID | Section | Issue | Owner | Target Date |
|---|---|---|---|---|
| RAG-TBD-01 | 3.4 | Formal benchmark pass criteria for compare and ambiguous prompt classes are referenced in roadmap artifacts but not yet codified as machine-checkable release criteria in this subsystem package. | Product and engineering | TBD |
| RAG-TBD-02 | 3.3, 3.4 | Interface-specific guarantees for non-REST consumers are implied by current implementation and roadmap language but are not yet documented in a dedicated RAG developer artifact. | Engineering | TBD |
| RAG-TBD-03 | 3.2 | Full external response schema documentation for all metadata fields remains split across architecture and runtime code and should be consolidated in future RAG developer documentation. | Engineering | TBD |

### 6.3 Decisions Log

| Date | Decision | Rationale | Decided By |
|---|---|---|---|
| 2026-03-30 | Use an SRS-only package first for RAG | Fastest path to separate requirements from the legacy architecture narrative and create a clean handoff for later architecture and developer docs | Product and engineering direction |
| 2026-03-30 | Keep the legacy root RAG architecture doc during the first migration step | It remains the most complete reverse-engineering source until the split package is complete | Documentation migration decision |