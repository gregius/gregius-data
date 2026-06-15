# Software Requirements Specification (SRS)

Standard: ISO/IEC/IEEE 29148:2018

## Document Information

| Field | Value |
|---|---|
| Project | Gregius Data |
| Software Component | Provider Architecture |
| Version | 1.0 |
| Date | 2026-03-30 |
| Author | Gregius |
| StRS Reference | N/A — direct architecture and codebase derivation |
| SyRS Reference | N/A — SRS produced directly from architecture documentation |
| Status | Draft |

## 1. Introduction

### 1.1 System Purpose

This SRS defines what the Provider Architecture software component must do to enable the Gregius Data plugin to support multiple database backends and AI services through unified, extensible interfaces.

### 1.2 System Scope

The software component comprises two parallel provider subsystems:

1. **Database Provider Subsystem** — abstracts direct PostgreSQL connections and HTTP-based PostgREST connections behind a single interface.
2. **AI Provider Subsystem** — abstracts multiple LLM, embedding, and reranking services behind a single interface with a fluent client facade and a per-provider tool calling strategy layer.

Software identifier: gregius-data-providers

Repository path: public/wp-content/plugins/gregius-data

### 1.3 System Overview

#### 1.3.1 System Context

The provider architecture operates as a foundational layer within the Gregius Data plugin. Upstream plugin components — sync, search, RAG pipeline, and admin UI — interact exclusively through the defined provider interfaces and contain no service-specific logic. The provider layer is responsible for translating abstract operations into the correct protocol calls for each backend.

#### 1.3.2 System Functions Summary

- Define and enforce DB provider interface contracts for all database backend implementations.
- Instantiate and cache DB provider instances via a factory, supporting runtime third-party extension.
- Define and enforce AI provider interface contracts for all AI service implementations.
- Register and resolve AI provider instances via a registry, supporting third-party extension through WordPress filters.
- Provide a fluent AI client facade for plugin-internal and third-party developer use.
- Adapt tool calling to provider-specific API formats via a strategy pattern.
- Ensure chunk production remains independent of the active database provider.

#### 1.3.3 User Characteristics

| User Class | Technical Level | Primary Interaction Point |
|---|---|---|
| Plugin Developer | Technical | Provider interfaces and AI client facade |
| Third-party Integrator | Technical | Factory registration, filter-based AI provider registration |
| Site Administrator | Technical/Operational | Connection configuration via admin UI |

### 1.4 Definitions

| Term | Definition |
|---|---|
| DB provider | A software component that implements the database provider interface and encapsulates all communication with one database backend type |
| AI provider | A software component that implements the AI provider interface and encapsulates all communication with one AI service |
| Provider factory | A registry and instantiation mechanism for DB provider classes, keyed by named type string |
| AI registry | A lookup and instantiation mechanism for AI provider instances, extensible via WordPress filter |
| Client facade | The single developer-facing entry point for AI requests, exposing a fluent request builder |
| Tool calling strategy | A per-provider adapter that translates abstract tool definitions and responses to and from a specific AI provider's native function/tool calling API format |
| Chunk | A canonical segment of WordPress post content stored provider-agnostically before vector generation |
| PostgREST provider | The HTTP-based DB provider that communicates with PostgreSQL through a PostgREST REST API |
| Connection | A named configuration record containing credentials and type information for one DB or AI service endpoint |

### 1.5 Abbreviations and Acronyms

| Abbreviation | Expansion |
|---|---|
| SRS | Software Requirements Specification |
| DB | Database |
| PDO | PHP Data Objects |
| RPC | Remote Procedure Call |
| LLM | Large Language Model |
| TF-IDF | Term Frequency–Inverse Document Frequency |
| API | Application Programming Interface |

## 2. References

- docs/providers/architecture.md
- includes/providers/interface-gg-db-provider.php
- includes/providers/class-gg-provider-factory.php
- includes/providers/class-gg-postgresql-provider.php
- includes/providers/class-gg-postgrest-provider.php
- includes/interfaces/interface-gg-data-ai-provider.php
- includes/ai/class-gg-data-llm-registry.php
- includes/ai/class-gg-data-ai-client.php
- includes/ai/strategies/interface-gg-data-tool-calling-strategy.php
- includes/ai/strategies/class-gg-data-tool-strategy-factory.php
- includes/sql/postgrest-schema.sql

## 3. Software Requirements

Requirement notation:
- MUST: mandatory
- SHOULD: strongly recommended
- MAY: optional

### 3.1 Functional Requirements

#### 3.1.1 Database Provider Interface and Factory

| ID | Requirement | Priority |
|---|---|---|
| PA-FR-01 | The software MUST define a database provider interface that all DB backend implementations are required to fulfill before use. | Must |
| PA-FR-02 | The DB provider interface MUST include three connection management operations: establish a connection using a configuration record, close an established connection, and test a connection without persisting it. | Must |
| PA-FR-03 | The DB provider interface MUST include two post data operations: write or update a WordPress post record to the external database, and delete a post record from the external database. | Must |
| PA-FR-04 | The DB provider interface MUST include two vector operations: generate and store vector embeddings for a given post, and execute a semantic similarity search against stored embeddings. | Must |
| PA-FR-05 | The DB provider interface MUST include two schema management operations: retrieve the current schema version installed on the backend, and create or update the database schema to the required version. | Must |
| PA-FR-06 | The software MUST provide a DB provider factory that creates and returns a DB provider instance given a named provider type string. | Must |
| PA-FR-07 | The DB provider factory MUST support runtime registration of custom DB provider classes by name without requiring changes to core plugin files. | Must |
| PA-FR-08 | The DB provider factory MUST reject requests for unsupported or unregistered provider types and signal the failure in a way that callers can handle without crashing. | Must |
| PA-FR-09 | The software MUST ship a direct PDO-based PostgreSQL DB provider that implements the full DB provider interface. | Must |
| PA-FR-10 | The software MUST ship an HTTP-based PostgREST DB provider that implements the full DB provider interface using the WordPress HTTP API and without requiring PHP database extensions. | Must |

#### 3.1.2 AI Provider Interface and Registry

| ID | Requirement | Priority |
|---|---|---|
| PA-FR-11 | The software MUST define an AI provider interface that all AI service implementations are required to fulfill before use. | Must |
| PA-FR-12 | The AI provider interface MUST include: a unique string identifier, a human-readable display name, a text generation method, LLM model listing, embedding model listing, reranking model listing, a capability flags array, a reranking method, and an embedding generation method. | Must |
| PA-FR-13 | The software MUST provide an AI provider registry that resolves a registered AI provider instance by its string identifier. | Must |
| PA-FR-14 | The AI provider registry MUST allow third-party code to register additional AI providers via a WordPress filter without modifying core plugin files. | Must |
| PA-FR-15 | The software MUST provide a fluent AI client facade that allows callers to initiate a text generation request and optionally chain provider, model, system message, and named connection selections before executing. | Must |
| PA-FR-16 | The AI client facade MUST support per-request selection of a named connection, enabling multiple API credentials to be used for the same provider type. | Must |
| PA-FR-17 | The software MUST ship native AI provider implementations for: OpenAI, Anthropic, Google Gemini, DeepSeek, Voyage AI, Cohere, and an internal provider with TF-IDF 300D and HashingTF Murmur3 1024D embedding models. | Must |

#### 3.1.3 Tool Calling Strategy Layer

| ID | Requirement | Priority |
|---|---|---|
| PA-FR-18 | The software MUST define a tool calling strategy interface that all provider-specific tool adapters are required to implement. | Must |
| PA-FR-19 | The tool calling strategy interface MUST include: a tool selection method, a tool formatting method for converting abstract tool definitions into the provider-specific wire format, a response parsing method for extracting tool selections from provider responses, and a strategy identifier method. | Must |
| PA-FR-20 | The software MUST provide a strategy factory that selects the appropriate tool calling strategy for a given model configuration automatically, without requiring callers to specify the strategy directly. | Must |
| PA-FR-21 | The strategy factory MUST fall back to a prompt-based tool calling strategy for any provider or model that does not support native tool calling. | Must |
| PA-FR-22 | The software MUST ship native tool calling strategy implementations for: OpenAI and DeepSeek (shared implementation), Anthropic, and Google Gemini. | Must |
| PA-FR-23 | The software MUST ship a prompt-based fallback tool calling strategy usable with any provider that does not have a native strategy. | Must |

#### 3.1.4 Connection Management

| ID | Requirement | Priority |
|---|---|---|
| PA-FR-24 | The software MUST provide a connection manager that caches DB provider instances per named connection and reuses them across multiple operations within the same request lifecycle. | Must |
| PA-FR-25 | The connection manager MUST support retrieval and on-demand instantiation of a DB provider from a stored named connection configuration. | Must |

### 3.2 Data and Contract Requirements

| ID | Requirement | Priority |
|---|---|---|
| PA-DR-01 | All DB provider method responses MUST include a boolean `success` field and a human-readable `message` string field. | Must |
| PA-DR-02 | DB provider error responses MUST set `success` to false and include a `message` field describing the failure condition. | Must |
| PA-DR-03 | DB provider success responses MUST set `success` to true and include operation-relevant data fields alongside the standard fields. | Must |
| PA-DR-04 | AI provider text generation responses MUST return either an array containing at minimum `text`, `tokens_used`, and `model` fields, or a `WP_Error` instance on failure. | Must |
| PA-DR-05 | Chunk production MUST remain provider-agnostic. Chunk boundaries and chunk content MUST NOT vary based on which DB provider is active for a given connection. | Must |
| PA-DR-06 | The `supabase` DB provider factory type alias MUST resolve to the same HTTP-based PostgREST provider as the `postgrest` alias. | Must |
| PA-DR-07 | The backward compatibility AI provider interface alias MUST remain resolvable and behave identically to the canonical AI provider interface. | Must |
| PA-DR-08 | For any validation or summary operation that requires multiple sequential HTTP calls on the PostgREST provider, the software SHOULD provide a single-call RPC equivalent that returns all required data in one server round trip. | Should |
| PA-DR-09 | Runtime PostgREST/Supabase operations MUST resolve credentials from canonical keys only: `project_url`, `publishable_key`, and `secret_key`. | Must |
| PA-DR-10 | Legacy Supabase key aliases (`api_key`, `service_role_key`) MAY be accepted only at configuration ingestion boundaries and MUST be normalized to canonical keys before runtime provider use. | Must |

### 3.3 Software Operations

| ID | Requirement | Priority |
|---|---|---|
| PA-OR-01 | The HTTP-based DB provider MUST use the WordPress HTTP API for all outbound requests and MUST NOT depend on PHP database extension availability. | Must |
| PA-OR-02 | The direct PDO-based DB provider MUST use the `pdo_pgsql` PHP extension for all database connections. | Must |
| PA-OR-03 | Connection credentials MUST NOT be written to log output at any verbosity level. | Must |
| PA-OR-04 | The connection manager MUST release all cached provider connections on WordPress shutdown. | Must |
| PA-OR-05 | The HTTP-based PostgREST DB provider MUST defer live connection establishment until the first operation is performed, and MUST NOT open a connection at construction time. | Must |
| PA-OR-06 | The software MUST support PHP 8.2 and above. | Must |
| PA-OR-07 | The software MUST function on any WordPress hosting environment that satisfies the minimum PHP version requirement, regardless of whether direct database TCP access is available. | Must |

### 3.4 Quality and Non-Functional Requirements

| ID | Requirement | Verification Method |
|---|---|---|
| PA-QR-01 | The DB provider interface MUST be implementable as a mock or test double suitable for unit testing without access to a live database connection. | Code review + test suite |
| PA-QR-02 | All DB provider implementations MUST use parameterized queries or equivalent prepared statements for all SQL execution to prevent injection attacks. | Code review |
| PA-QR-03 | All outbound HTTP requests in the HTTP-based DB provider MUST validate the response status code and handle WordPress HTTP API error return values gracefully without causing fatal errors. | Code review + integration test |
| PA-QR-04 | Registering a new third-party DB or AI provider via the defined extension points MUST NOT require changes to existing provider implementations, the factory, or the registry. | Code review |
| PA-QR-05 | The provider architecture MUST NOT cause performance regressions in existing sync or search operations when a new provider is registered via an extension point. | Benchmark and profiling review |

## 4. Verification and Acceptance

| Requirement Group | Verification Method | Tool or Evidence |
|---|---|---|
| DB provider interface and factory (PA-FR-01–10) | Interface conformance and factory behavior tests | Unit tests, code review |
| AI provider interface and registry (PA-FR-11–17) | Interface conformance and registration behavior tests | Unit tests, filter integration test |
| Tool calling strategy layer (PA-FR-18–23) | Strategy selection, format output, and response parsing tests | Unit tests per strategy implementation |
| Connection management (PA-FR-24–25) | Lifecycle and caching behavior tests | Unit tests, code review |
| Data contracts (PA-DR-01–08) | Response schema inspection | Unit tests + manual inspection |
| Operations requirements (PA-OR-01–07) | Environment, credential, and lifecycle review | Host compatibility review, code review |
| Quality requirements (PA-QR-01–05) | Static analysis, code review, integration test | PHPStan, code review, test suite |

## 5. Traceability

| SRS Requirement IDs | Source References | Notes |
|---|---|---|
| PA-FR-01 to PA-FR-10 | docs/providers/architecture.md (Database Providers, Interface Design Decisions, Factory Pattern Implementation) | DB interface, factory, and concrete provider requirements |
| PA-FR-11 to PA-FR-17 | docs/providers/architecture.md (AI Provider Architecture, Key Components, Implementing an AI Provider) | AI interface, registry, and client facade requirements |
| PA-FR-18 to PA-FR-23 | docs/providers/architecture.md (Tool Calling Strategy Architecture, Strategy Interface, Strategy Factory) | Tool calling strategy layer requirements |
| PA-FR-24 to PA-FR-25 | docs/providers/architecture.md (Connection Manager Integration, Provider Lifecycle) | Connection manager requirements |
| PA-DR-01 to PA-DR-04 | docs/providers/architecture.md (Standardized Return Formats, AI Provider Architecture) | Response contract requirements |
| PA-DR-05 | docs/providers/architecture.md (Shared Chunk Contract Across Providers) | Chunking independence requirement |
| PA-DR-06 to PA-DR-07 | docs/providers/architecture.md (Factory Type Aliases, backward compatibility note under Key Components) | Alias and backward compatibility requirements |
| PA-DR-08 | docs/providers/architecture.md (RPC Functions for Performance Optimization, Validation RPC Pattern) | RPC consolidation requirement |
| PA-DR-09 to PA-DR-10 | docs/providers/architecture.md (Interface Constraints, Connection Key Normalization Contract) | Canonical runtime key usage and ingestion-boundary normalization |
| PA-OR-01 to PA-OR-07 | docs/providers/architecture.md (HTTP-Based Providers, Direct Connection Providers, Performance Considerations, Security Considerations) | Operational and environment constraint requirements |
| PA-QR-01 to PA-QR-05 | docs/providers/architecture.md (Testing Strategy, Security Considerations, Performance Considerations) | Testability, security, and extensibility quality requirements |

## 6. Appendices

### 6.1 Assumptions and Dependencies

- This SRS is produced in SRS-first mode without separate BRS, StRS, or SyRS artifacts.
- Requirements are reverse-engineered from `docs/providers/architecture.md` and the verified codebase state as of 2026-03-30. The architecture doc is treated as the authoritative design record.
- The MySQL 9.0+ provider described as a future enhancement in the architecture doc is out of scope for this SRS. Requirements will be added in a future revision when implementation planning for that phase begins.
- The WordPress PHP AI Client (`wordpress/php-ai-client`) alignment section in the architecture doc is treated as a forward-looking migration note, not a current requirement.
- Third-party integrators are assumed to have PHP developer-level access to the plugin codebase to register custom providers via the defined extension points.

### 6.2 Open Issues

| ID | Section | Issue | Owner | Target Date |
|---|---|---|---|---|
| PA-TBD-01 | 3.1.1 | MySQL 9.0+ provider interface requirements are deferred until implementation planning for Phase R.4 begins. | Engineering | TBD |
| PA-TBD-02 | 3.4 | Formal performance baseline thresholds for sync and search operations are not yet codified as measurable acceptance criteria. | Engineering | TBD |
| PA-TBD-03 | 3.4 | Automated contract tests for AI provider text generation response schema are not yet part of the test suite. | Engineering | TBD |

### 6.3 Decisions Log

| Date | Decision | Rationale | Decided By |
|---|---|---|---|
| 2026-03-30 | Use SRS-only package for provider architecture requirements | Single feature with clear system context; SRS-first default mode is sufficient | Engineering direction |
| 2026-03-30 | Reverse-engineer SRS from providers/architecture.md | Architecture doc is the verified source of truth post-refactoring | Engineering direction |
| 2026-03-30 | Exclude MySQL provider requirements from this version | Not yet implemented; requirements will be authored when implementation planning begins | Engineering direction |
