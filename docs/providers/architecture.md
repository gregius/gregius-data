# Architecture Description: Provider Subsystem

**Standard:** ISO/IEC/IEEE 42010:2022  
**Component:** Provider Architecture (Database + AI Provider Abstraction)  
**Version:** 1.0.0  
**Date:** 2026-03-30  
**Upstream Specification:** [Software Requirements Specification](srs.md)

---

## 1. Architecture Overview

### 1.1 Purpose and Scope

This architecture describes how the Gregius Data plugin abstracts multiple backend services (databases and AI providers) through unified interfaces, enabling runtime selection of providers without core plugin changes.

**Scope:**
- Database provider subsystem: interface, factory, concrete implementations (PostgreSQL direct, PostgREST/Supabase)
- AI provider subsystem: interface, registry, client facade, per-provider implementations
- Tool calling strategy layer: per-provider adapters for function calling formats
- Extensibility mechanisms: factory registration (DB), filter-based registration (AI)
- Canonical PostgreSQL mirror naming: provider-facing table names resolve to the shared `wp_` mirror prefix rather than a runtime multisite suffix

**Explicitly Excluded:**
- Chunk production and storage (assumed provider-agnostic)
- Admin UI configuration
- REST API endpoint implementation
- Sync, search, and RAG pipeline orchestration

### 1.2 Architecture Stakeholders and Concerns

| Stakeholder | Concern | Priority |
|---|---|---|
| **Plugin Developers** | Easy interface to add new DB/AI providers; clear extension contracts | High |
| **Site Administrators** | Runtime selection of providers via admin UI; no code changes | High |
| **Third-party Integrators** | Filter-based AI registration; factory-based DB registration without core edits | High |
| **Operations** | Deterministic contract parity across provider paths (PDO vs HTTP); error isolation | Medium |
| **Security** | Credential isolation per provider; no cross-provider auth leakage | High |

---

## 2. Architecture Context View (AV-01)

### 2.1 System Context Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                   GREGIUS DATA PLUGIN                        │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │  Upstream Plugin Components                             │ │
│  │  (Sync, Search, RAG Pipeline, Admin UI, REST API)      │ │
│  │  - No service-specific logic                            │ │
│  │  - Call only interface methods                          │ │
│  └────────────────────┬────────────────────────────────────┘ │
│                       │                                       │
│                       ▼                                       │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │  PROVIDER ABSTRACTION LAYER                             │ │
│  │  ┌────────────────────────────────────────────────────┐ │ │
│  │  │ Database Provider Subsystem                         │ │ │
│  │  │ ├─ Interface: GG_Data_DB_Provider                       │ │ │
│  │  │ ├─ Factory: GG_Data_Provider_Factory                    │ │ │
│  │  │ ├─ PostgreSQL (PDO): GG_Data_PostgreSQL_Provider       │ │ │
│  │  │ └─ PostgREST (HTTP): GG_Data_PostgREST_Provider        │ │ │
│  │  └────────────────────────────────────────────────────┘ │ │
│  │                                                          │ │
│  │  ┌────────────────────────────────────────────────────┐ │ │
│  │  │ AI Provider Subsystem                               │ │ │
│  │  │ ├─ Interface: GG_Data_AI_Provider_Interface        │ │ │
│  │  │ ├─ Registry: GG_Data_LLM_Registry                  │ │ │
│  │  │ ├─ Client Facade: GG_Data_Ai_Client               │ │ │
│  │  │ ├─ OpenAI, Anthropic, Gemini, DeepSeek providers   │ │ │
│  │  │ ├─ Voyage, Cohere, Internal providers              │ │ │
│  │  │ └─ Tool Strategy Layer                              │ │ │
│  │  │    ├─ OpenAI/DeepSeek native                       │ │ │
│  │  │    ├─ Anthropic native                             │ │ │
│  │  │    ├─ Gemini native                                │ │ │
│  │  │    └─ Fallback: Prompt-based JSON                  │ │ │
│  │  └────────────────────────────────────────────────────┘ │ │
│  └─────────────────────────────────────────────────────────┘ │
│                                                               │
└─────────────────────────────────────────────────────────────┘
         │                                      │
         ▼                                      ▼
    ┌─────────────────┐          ┌──────────────────────┐
    │ PostgreSQL      │          │ AI Services          │
    │ (any server)    │          │ - OpenAI             │
    │ via PDO         │          │ - Anthropic          │
    └─────────────────┘          │ - Google Gemini      │
                                  │ - DeepSeek           │
    ┌─────────────────┐          │ - Voyage AI          │
    │ Supabase        │          │ - Cohere             │
    │ (PostgREST)     │          └──────────────────────┘
    └─────────────────┘
```

### 2.2 Context Rationale

The provider abstraction layer isolates upstream plugin components from backend-specific APIs and credentials. Upstream code calls interface methods; the provider layer translates to the correct backend protocol. This achieves:

- **Separation of Concerns:** Sync, search, and RAG code remain unaware of DB/AI specifics
- **Runtime Flexibility:** Administrators select providers without code changes
- **Third-party Extensibility:** New providers can be registered without modifying core
- **Error Isolation:** Provider failures are explicit and contained to provider operations
- **Canonical Mirror Names:** PostgreSQL mirror tables are resolved through one shared naming contract, so PDO and PostgREST behave the same across sites

---

## 3. Architecture Component View (AV-02)

### 3.1 Database Provider Subsystem

```
Upstream Plugin Code
         │
         ├─ sync_process()
         ├─ search()
         └─ rag_pipeline()
         │
         ▼
Connection Manager
  - Cache provider instances
  - Route calls to active provider
         │
         ├──────────────────┬──────────────────┐
         │                  │                  │
         ▼                  ▼                  ▼
    Interface          Factory              Concrete Providers
 GG_Data_DB_Provider    (Instantiation)
     (9 methods)         │              PostgreSQL (PDO)
                         │              GG_Data_PostgreSQL_Provider
                    register()              - connect()
                    create()                - sync_post()
                    is_supported()          - vector_search()
                                            - schema version
                             
                              PostgREST (HTTP)
                              GG_Data_PostgREST_Provider
                              - same 9-method interface
                              - HTTP via wp_remote_post()
                              - RPC calls for complex ops
```

**Key Components:**

1. **Interface** (`GG_Data_DB_Provider`) — defines contract:
   - Connection: `connect()`, `disconnect()`, `test_connection()`
   - Data: `sync_post()`, `delete_post()`
   - Vectors: `generate_vectors()`, `search()`
   - Schema: `get_schema_version()`, `create_schema()`

2. **Factory** (`GG_Data_Provider_Factory`) — handles instantiation:
   - `create_provider($type, $config)` → provider instance
   - `register_provider($type, $class)` → third-party registration
   - `get_supported_types()` → active provider list
   - Error handling for unsupported types

3. **PostgreSQL Provider** (`GG_Data_PostgreSQL_Provider`):
   - Direct PDO connection to PostgreSQL
   - Requires `pdo_pgsql` PHP extension
   - Best performance; VPS/cloud hosting required
   - Native pgvector support
   - Uses canonical mirror table names with the shared `wp_` prefix contract

4. **PostgREST Provider** (`GG_Data_PostgREST_Provider`):
   - HTTP REST API to Supabase
   - No PHP extensions required
   - Works on any WordPress hosting
   - RPC functions for bulk operations and complex queries
   - Uses the same canonical mirror table names as the PDO provider

**Mapping to Requirements:**
- AV-02 → PA-FR-01, PA-FR-02, PA-FR-03, PA-FR-04, PA-FR-05, PA-FR-06, PA-FR-07, PA-FR-08, PA-FR-09, PA-FR-10

---

### 3.2 AI Provider Subsystem

```
Upstream Plugin Code
         │
    GG_Data_Ai_Client::prompt('...')
         │
         ▼
AI Client Facade
  - Fluent builder interface
  - Sets prompt, system message, model, provider, connection
         │
         ▼
AI Request Handler
  - Validates config
  - Executes via registry
         │
         ▼
AI Registry
  - Lookup by provider ID
  - Filter: gg_data_llm_providers
         │
    ┌────┴────┬────────────┬─────────────┬────────────┬────────────┐
    │          │            │             │            │            │
    ▼          ▼            ▼             ▼            ▼            ▼
  OpenAI   Anthropic   Gemini      DeepSeek      Voyage        Cohere
   (LLM)   (LLM)       (LLM+E)     (LLM)         (E + Rerank)  (Rerank)
   │       │           │           │             │             │
   └───────┴───────────┴───────────┴─────────────┴─────────────┘
                 │
         Tool Calling Strategy
                 │
      ┌──────────┼──────────┬──────────┬──────────┐
      │          │          │          │          │
      ▼          ▼          ▼          ▼          ▼
   OpenAI   Anthropic   Gemini    DeepSeek   Prompt-Based
   Native   Native      Native    Native     (JSON fallback)
```

**Key Components:**

1. **Interface** (`GG_Data_AI_Provider_Interface`) — defines contract:
   - `get_id()` → unique provider identifier
   - `get_name()` → display name
   - `generate_text($prompt, $options)` → LLM generation
   - `get_llm_models()` → available LLM models
   - `get_embedding_models()` → available embedding models
   - `get_rerank_models()` → available reranking models
   - `get_capabilities()` → capability flags
   - `rerank($query, $results)` → reranking
   - `generate_embedding($text)` → embeddings

2. **Registry** (`GG_Data_LLM_Registry`):
   - Manages provider instances
   - `register_provider($id, $instance)` → add provider
   - `get_provider($id)` → retrieve provider by ID
   - Extensible via `gg_data_llm_providers` filter

3. **Client Facade** (`GG_Data_Ai_Client`):
   - Single entry point for plugin/third-party use
   - Fluent builder: `prompt()->setSystemMessage()->usingProvider()->usingModel()->generateText()`
   - Handles request orchestration

4. **Concrete Providers** (7 implementations):
   - OpenAI, Anthropic, Gemini, DeepSeek: LLM support
   - Voyage, Cohere: Embedding/reranking support
   - Internal: TF-IDF 300D and HashingTF Murmur3 1024D embeddings (free, no API required)

5. **Tool Strategy Layer** — per-provider adapters:
   - OpenAI/DeepSeek native format (function objects)
   - Anthropic native format (tool objects)
   - Gemini native format (function declarations)
   - Fallback: JSON generation + parsing for unsupported providers

**Mapping to Requirements:**
- AV-02 → PA-FR-11, PA-FR-12, PA-FR-13, PA-FR-14, PA-FR-15, PA-FR-16, PA-FR-17, PA-FR-18, PA-FR-19, PA-FR-20

---

## 4. Architecture Runtime View (AV-03)

### 4.1 Connection Creation Sequence

```
Sequence: Create and test a new database provider

Admin UI (Dashboard)
    │
    ├─ POST /wp-json/gg-data/v1/connections
    │                      │
    │                      ▼
    │         REST API Controller
    │                      │
    │         ┌────────────┴─────────────┐
    │         │ Validate config params   │
    │         │ - type, host, creds      │
    │         └────────────┬─────────────┘
    │                      │
    │                      ▼
    │         Connection Manager
    │                      │
    │         ┌────────────┴─────────────────┐
    │         │ Factory::create_provider()   │
    │         └────────────┬─────────────────┘
    │                      │
    │    ┌─────────────────┼─────────────────┐
    │    │                 │                 │
    │    ▼ (if type ==)   ▼                 ▼
    │  'postgresql'    Factory              Error
    │    │             checks               │
    │    ▼             registry             └─ return error
    │  new GG_Data_PostgreSQL_Provider()        response
    │    │                                  
    │    ├─ $provider->construct()         
    │    │                                  
    │    └─ return $provider                
    │                      │
    │                      ▼
    │         Connection Manager
    │                      │
    │         ┌────────────┴────────────────┐
    │         │ call test_connection()      │
    │         └────────────┬────────────────┘
    │                      │
    │         PostgreSQL Provider
    │                      │
    │         ┌────────────┴────────────────┐
    │         │ attempt PDO connect        │
    │         │ SELECT 1 test query        │
    │         └────────────┬────────────────┘
    │                      │
    │    ┌─────────────────┼─────────────────┐
    │    │                 │                 │
    │    ▼                 ▼
    │  Success            Error
    │    │                │
    │    │         Admin UI
    │    └─────────────────┤ (error toast)
    │                      │
    │                      ▼
    │         Admin UI (success notification)
```

### 4.2 AI Generation Sequence

```
Sequence: Generate text via AI provider

Plugin Code:
    │
    ├─ $client = GG_Data_Ai_Client::prompt('What about WordPress?')
    │                      │
    │                      ▼
    │         Ai_Client (fluent builder)
    │                      │
    ├─ ->setSystemMessage('You are a developer')
    │                      │
    ├─ ->usingProvider('openai')
    │                      │
    ├─ ->usingModel('gpt-4o')
    │                      │
    │                      ▼
    │         Ai_Request (store config)
    │                      │
    ├─ ->generateText()
    │                      │
    │                      ▼
    │         Ai_Request::execute()
    │                      │
    │         ┌────────────┴──────────────┐
    │         │ Validate provider + model │
    │         │ (security checks)         │
    │         └────────────┬──────────────┘
    │                      │
    │                      ▼
    │         Registry::get_provider('openai')
    │                      │
    │    ┌─────────────────┴──────────────┐
    │    │ check registered instances     │
    │    │ return GG_Data_OpenAI_Provider │
    │    └─────────────────┬──────────────┘
    │                      │
    │                      ▼
    │         OpenAI Provider
    │                      │
    │         ┌────────────┴──────────────┐
    │         │ build OpenAI API request  │
    │         │ {model: "gpt-4o", msgs}   │
    │         └────────────┬──────────────┘
    │                      │
    │                      ▼
    │         wp_remote_post() to OpenAI
    │                      │
    │         OpenAI Service
    │                      │
    │         ┌────────────┴──────────────┐
    │         │ process & generate        │
    │         │ return response           │
    │         └────────────┬──────────────┘
    │                      │
    │                      ▼
    │         OpenAI Provider
    │                      │
    │         ┌────────────┴──────────────┐
    │         │ parse response            │
    │         │ return array [text, meta] │
    │         └────────────┬──────────────┘
    │                      │
    │                      ▼
    │         Plugin Code  
    │                      │
    │                      ├─ if WP_Error → handle
    │                      └─ else → use text
```

---

## 5. Architecture Decisions (ADRs)

### AD-01: Strategy Pattern for Database Provider Abstraction

**Decision:** Use the Strategy pattern to support swappable database implementations.

**Status:** Decided  
**Linked Requirements:** PA-FR-01, PA-FR-02, PA-FR-03, PA-FR-04, PA-FR-05

**Alternatives Considered:**
1. **Single monolithic provider class** — logic for all DB types in one class
   - Rejected: Unmanageable complexity; tight coupling to all DB APIs
2. **Dynamic code generation** — generate provider classes at runtime
   - Rejected: Difficult to debug; type safety issues
3. **Adapter pattern** — wrap existing DB abstraction libraries
   - Rejected: Introduces dependency; less control over interface contract
4. **Strategy pattern** ✅ — interface + concrete implementations
   - Chosen: Clear contracts; testable; extensible

**Rationale:**
- Plugin must support PostgreSQL (direct PDO) and Supabase (PostgREST HTTP) with identical interface
- Strategy pattern isolates implementation details; business logic references only interface
- Factory pattern enables third-party provider registration without core changes

**Consequences:**
- Plus: Easy to add new DB providers; clear extension points
- Plus: Runtime provider selection without code changes
- Plus: Testable via mocks
- Minus: Slight performance overhead from method indirection (negligible in practice)
- Minus: Developers must implement all 9 interface methods

---

### AD-02: Factory Pattern for DB Provider Instantiation

**Decision:** Implement a factory to control DB provider creation and lifecycle.

**Status:** Decided  
**Linked Requirements:** PA-FR-06, PA-FR-07, PA-FR-08

**Alternatives Considered:**
1. **Direct instantiation** — upstream code calls `new GG_Data_PostgreSQL_Provider()`
   - Rejected: Tight coupling; upstream must know all provider class names
2. **Service container/DI** — use a service locator
   - Rejected: Adds framework dependency; overkill for this scope
3. **Factory pattern** ✅ — central registration and instantiation
   - Chosen: Simple to understand; type-keyed lookup; third-party registration

**Rationale:**
- Factory centralizes provider creation logic and validation
- Enables runtime type validation (reject unsupported providers early)
- Filter hook in WordPress conventions for third-party registration

**Consequences:**
- Plus: Type safety; clear error handling for unknown provider types
- Plus: Third-party providers registered without core modifications
- Plus: Easy to add pre-instantiation hooks (e.g., logging, monitoring)
- Minus: Small indirection layer; minimal performance impact

---

### AD-03: Registry + Filter Pattern for AI Provider Extensibility

**Decision:** Use a registry with WordPress filter hooks for AI provider registration.

**Status:** Decided  
**Linked Requirements:** PA-FR-17, PA-FR-18

**Alternatives Considered:**
1. **Hardcoded provider list** — only built-in AI providers, no third-party
   - Rejected: Prevents ecosystem growth; reduces plugin value
2. **Factory pattern (like DB)** — static factory method calls
   - Rejected: Less aligned with WordPress conventions
3. **Filter-based registration** ✅ — `gg_data_llm_providers` filter hook
   - Chosen: Follows WordPress practice; easy third-party integration

**Rationale:**
- AI provider set is large (7 built-in + unknown future providers)
- Filter hook aligns with WordPress plugin ecosystem practices
- Registry lookup by string ID is simpler than type-keyed factory

**Consequences:**
- Plus: Follows WordPress conventions; expected by integrators
- Plus: Dynamic registration at any point in plugin lifecycle
- Plus: Third-party providers share registry with built-ins
- Minus: Less type-safe than factory pattern (required: string IDs)
- Minus: Registration order might affect behavior if not careful

---

### AD-04: Tool Calling Strategy Layer for Function Calling Formats

**Decision:** Implement a per-provider adapter layer for function calling formats.

**Status:** Decided  
**Linked Requirements:** PA-FR-19, PA-FR-20

**Alternatives Considered:**
1. **Unified function format** — define one format, convert all providers to it
   - Rejected: Forces inefficiency; each provider has native strengths
2. **Provider-specific code in business logic** — let RAG code handle formats
   - Rejected: Scatters provider logic throughout codebase
3. **Strategy pattern with adapters** ✅ — interface + per-provider strategies
   - Chosen: Abstraction of converting to/from native formats

**Rationale:**
- OpenAI, Anthropic, Gemini each have different function calling APIs
- Internal RAG pipeline expects a canonical tool format
- Strategy pattern isolates conversion logic in dedicated classes
- Prompt-based fallback for providers without native tool support

**Consequences:**
- Plus: RAG code remains format-agnostic
- Plus: Easy to add native support for new providers
- Plus: Fallback mechanism for unsupported providers
- Minus: Slight latency for per-provider conversion
- Minus: Must maintain parity as provider APIs evolve

---

### AD-05: Provider-Agnostic Chunk Contract

**Decision:** Maintain chunk production and storage independent of the active DB provider.

**Status:** Decided  
**Linked Requirements:** PA-CR-01

**Alternatives Considered:**
1. **Provider-specific chunking** — each provider defines its own chunk rules
   - Rejected: Fragment data schema; makes migration impossible
2. **Dynamic chunking per provider** — adjust chunk size/boundaries by provider
   - Rejected: Breaks semantic consistency; vector embeddings would drift
3. **Canonical chunk format** ✅ — one format, all providers consume identically
   - Chosen: Provider-agnostic storage; consistent embeddings

**Rationale:**
- Chunks are semantic units; provider implementation must not change boundaries
- Vector embeddings are model-specific but chunk structure must be stable
- `GG_Data_Chunker` produces canonical chunks → `wp_posts_chunks` table
- Both PDO and PostgREST providers read the same chunk rows

**Consequences:**
- Plus: Easy migration between providers (data already in canonical format)
- Plus: Consistent vector embeddings across all provider paths
- Plus: Search relevance independent of provider choice
- Minus: Chunk contract is immutable (design must be correct first)

---

## 6. Constraints

### 6.1 Technology Constraints

| Constraint | Impact | Rationale |
|---|---|---|
| PHP 7.4+ required | Minimum PHP version | WordPress core requirement; enables typed properties, typed return hints |
| WordPress 6.9+ required | Core version | REST API stability; async/lazy loading support |
| PDO with `pdo_pgsql` for PostgreSQL Direct | Optional dependency | VPS/cloud only; no extension = use PostgREST |
| `wp_remote_post()` for HTTP providers | HTTP API requirement | WordPress HTTP client; handles proxies, SSL, authentication |
| PostgreSQL 12+ (minimum) | Database version | pgvector extension; JSON operators; window functions |

### 6.2 Interface Constraints

| Constraint | Impact | Rationale |
|---|---|---|
| DB provider interface is immutable | Breaking changes only in major versions | Third-party code depends on interface contract |
| All DB providers must implement 9 methods | Implementation burden | Enables reliable contract; no optional methods |
| PostgREST runtime keys are canonical-only | Runtime reads use `project_url`, `publishable_key`, and `secret_key` only | Prevents alias drift and keeps provider behavior deterministic |
| Legacy Supabase aliases are ingestion-only | `api_key` and `service_role_key` may be accepted only while saving config, then normalized | Preserves backward compatibility without runtime dependency on legacy keys |
| AI provider interface cannot drop methods | Backward compat required | Existing code and filters depend on method availability |
| Chunk contract is immutable | Requires careful design | Chunks are persisted; changing schema is complex |
| Tool strategy formats must align with provider APIs | Must update if provider APIs change | Native function calling is the contract with AI services |

### 6.3 Performance Constraints

| Constraint | Impact | Rationale |
|---|---|---|
| HTTP-based providers must match PDO speeds | ~100ms max overhead | Sync operations process thousands of posts; latency multiplies |
| Vector search must complete in <1s | UX requirement | Search timeout for user-facing queries |
| Factory instantiation must not exceed 50ms | Per-request overhead | Overhead applied to every data operation |
| AI provider resolution must not exceed 20ms | Per-request overhead | Every AI request must resolve provider first |

---

## 7. Risks and Mitigations

### Risk 1: Performance Parity Between Direct and HTTP Providers

**Risk:** HTTP-based PostgREST provider may be significantly slower than direct PDO.

**Severity:** High  
**Likelihood:** Medium

**Impact:**
- Sync operations (thousands of posts) would be unacceptably slow
- Search queries would timeout
- Admin users experience degraded UX

**Mitigation:**
- RPC function `gg_get_validation_summary` reduces HTTP round trips from 7-10 calls to 1
- Batch operations via RPC combined with bulk upserts
- Load testing with realistic workloads
- Database indexes on vector fields and search query results

**Monitoring:**
- Track sync operation duration by provider type
- Alert if PostgREST operations exceed PDO by >20%

---

### Risk 2: Third-party Provider Registration Errors

**Risk:** Developers register invalid providers; bad code crashes plugin.

**Severity:** Medium  
**Likelihood:** Medium

**Impact:**
- Plugin fails to load if provider registration throws
- Admin UI inaccessible; no way to fix
- User data could be at risk if sync/search fails

**Mitigation:**
- Factory validates provider type string exists in registry
- `is_supported()` check before creation; return graceful error if missing
- Try/catch in factory with logging
- Provider interface type hints catch most errors during testing

**Monitoring:**
- Log all provider creation errors
- Alert on repeated invalid provider requests

---

### Risk 3: Credential Leakage Between Providers

**Risk:** Connection credentials from one provider leak to another.

**Severity:** Critical  
**Likelihood:** Low

**Impact:**
- Unauthorized access to databases or AI services
- Data breach; compliance violations

**Mitigation:**
- Each provider instance holds only its own credentials (no shared state)
- Credentials not logged or exposed in error messages
- Connection Manager isolates provider instances in variable scope
- WordPress nonces on config endpoints
- Capability checks on REST endpoints

**Monitoring:**
- Audit logging of credential usage
- Alert on repeated auth failures

---

### Risk 4: Breaking Changes in Provider Interfaces

**Risk:** Future updates to DB or AI provider interfaces break third-party code.

**Severity:** High  
**Likelihood:** Low

**Impact:**
- Third-party providers stop working
- Developers forced to rewrite implementations
- Ecosystem fragmentation

**Mitigation:**
- Semantic versioning: breaking changes only in major versions
- Deprecation period before removal (e.g., 2+ minor versions)
- Migration guide for breaking changes
- Internal alias patterns (e.g., `GG_Data_LLM_Provider_Interface` → `GG_Data_AI_Provider_Interface`)

---

## 8. Architecture Coverage Mapping

### Functional Requirements Coverage

| Requirement | Architecture Item | Status |
|---|---|---|
| PA-FR-01 (DB interface required) | 3.1 Interface, AD-01 | ✅ Covered |
| PA-FR-02 (Connection methods) | 3.1 Key Components #1 | ✅ Covered |
| PA-FR-03 (Data operations) | 3.1 Key Components #1 | ✅ Covered |
| PA-FR-04 (Vector operations) | 3.1 Key Components #1 | ✅ Covered |
| PA-FR-05 (Schema methods) | 3.1 Key Components #1 | ✅ Covered |
| PA-FR-06 (Factory pattern) | 3.1 Key Components #2, AD-02 | ✅ Covered |
| PA-FR-07 (Runtime registration) | 3.1 Key Components #2, AD-02 | ✅ Covered |
| PA-FR-08 (Error handling) | 3.1 Key Components #2, Risk #2 | ✅ Covered |
| PA-FR-09 (PostgreSQL provider) | 3.1 PostgreSQL Provider | ✅ Covered |
| PA-FR-10 (PostgREST provider) | 3.1 PostgREST Provider | ✅ Covered |
| PA-FR-11 (AI interface required) | 3.2 Key Components #1 | ✅ Covered |
| PA-FR-12 (AI interface methods) | 3.2 Key Components #1 | ✅ Covered |
| PA-FR-13 (OpenAI provider) | 3.2 Concrete Providers | ✅ Covered |
| PA-FR-14 (Anthropic provider) | 3.2 Concrete Providers | ✅ Covered |
| PA-FR-15 (Gemini provider) | 3.2 Concrete Providers | ✅ Covered |
| PA-FR-16 (DeepSeek provider) | 3.2 Concrete Providers | ✅ Covered |
| PA-FR-17 (Registry + filter) | 3.2 Key Components #2, AD-03 | ✅ Covered |
| PA-FR-18 (Client facade) | 3.2 Key Components #3 | ✅ Covered |
| PA-FR-19 (Tool strategy layer) | 3.2 Tool Strategy Layer, AD-04 | ✅ Covered |
| PA-FR-20 (Voyage provider) | 3.2 Concrete Providers | ✅ Covered |

### Interface Requirements Coverage

| Requirement | Architecture Item | Status |
|---|---|---|
| PA-IR-01 (Standard return formats) | 3.1, standardized responses | ✅ Covered |
| PA-IR-02 (Error isolation) | Risk #1, #2 mitigation | ✅ Covered |

### Quality Requirements Coverage

| Requirement | Architecture Item | Status |
|---|---|---|
| PA-QR-01 (Extensibility) | AD-02, AD-03; Factory & Filter | ✅ Covered |
| PA-QR-02 (Performance parity) | Risk #1, Constraint 6.3 | ✅ Covered |

### Constraint Requirements Coverage

| Requirement | Architecture Item | Status |
|---|---|---|
| PA-CR-01 (Chunk independence) | AD-05; 3.1 Shared Chunk | ✅ Covered |

---

## 9. Architecture Readiness Checklist

- [x] Architecture boundary and scope defined (section 1.1)
- [x] Stakeholder concerns identified (section 1.2)
- [x] System context view complete (section 2)
- [x] Component view for DB subsystem complete (section 3.1)
- [x] Component view for AI subsystem complete (section 3.2)
- [x] Runtime interaction sequences documented (section 4)
- [x] Architecture decisions recorded with alternatives (section 5)
- [x] Constraints documented (section 6)
- [x] Risks and mitigations identified (section 7)
- [x] Coverage mapping to SRS requirements complete (section 8)
- [x] Upstream SRS linked ([srs.md](srs.md))
- [x] Next implementation handoff identified (see section 9)

**Readiness Status:** ✅ **Draft Complete**

---

## 10. Next Steps and Implementation Handoff

### Immediate Implementation

This architecture document is now ready for implementation using the `wp-plugin-development` skill:

1. **Implement DB Provider Subsystem** (PA-FR-01 through PA-FR-10)
   - Interface: `includes/providers/interface-gg-db-provider.php`
   - Factory: `includes/providers/class-gg-provider-factory.php`
   - PostgreSQL: `includes/providers/class-gg-postgresql-provider.php`
   - PostgREST: `includes/providers/class-gg-postgrest-provider.php`

2. **Implement AI Provider Subsystem** (PA-FR-11 through PA-FR-20)
   - Interface: `includes/interfaces/interface-gg-data-ai-provider.php`
   - Registry: `includes/ai/class-gg-data-llm-registry.php`
   - Client: `includes/ai/class-gg-data-ai-client.php` + `GG_Data_Ai_Request`
   - Concrete providers (7 implementations)

3. **Implement Tool Calling Strategy Layer**
   - Interface: `includes/ai/strategies/interface-gg-data-tool-calling-strategy.php`
   - Factory: `includes/ai/strategies/class-gg-data-tool-strategy-factory.php`
   - Per-provider strategies (OpenAI, Anthropic, Gemini, Prompt-based)

4. **Implement Admin UI** (REST endpoints for provider configuration)
   - Provider list endpoint
   - Test connection endpoint
   - Save configuration endpoint
   - Delete provider endpoint

### Testing Strategy (use wp-qa-testing skill)

- Unit tests for factory instantiation and provider interface compliance
- Integration tests for DB sync operations (PDO vs PostgREST)
- Load tests for performance parity (Risk #1 mitigation)
- Security tests for credential isolation (Risk #3 mitigation)

### Documentation (use wp-developer-documentation and wp-user-documentation skills)

- Developer guide: How to implement a custom DB provider
- Developer guide: How to implement a custom AI provider
- User guide: Configure providers in admin UI
- Admin guide: Troubleshoot provider connection issues

---

## Document History

| Version | Date | Status | Notes |
|---|---|---|---|
| 1.0.0 | 2026-03-30 | Draft | Initial architecture document |
