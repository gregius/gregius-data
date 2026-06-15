# Architecture Description: Tools and Tool Selection Subsystem

Standard: ISO/IEC/IEEE 42010:2022
Component: Tools and Tool Selection Subsystem
Version: 1.0.0
Date: 2026-04-25
Upstream Specification: [srs.md](srs.md)

---

## 1. Architecture Overview

### 1.1 Purpose and Scope

This architecture describes how Gregius Data provides agentic RAG tool selection and execution while supporting multiple LLM providers and enabling third-party extensibility.

Scope includes:
- Tool selection strategy pattern (provider-specific routing)
- Built-in tool definitions and execution model
- Extensibility via hooks and custom strategies
- Fallback mechanisms for unsupported providers

Explicitly excluded:
- RAG retrieval algorithms (see docs/rag/)
- LLM inference details (see docs/providers/)
- Interaction tracking implementation (see docs/interactions/)

### 1.2 Stakeholders and Concerns

| Stakeholder | Concern | Priority |
|---|---|---|
| Plugin integrators | Stable Tier 1 tool contracts; clear extension recipes | High |
| Site developers | Override tool selection for site-specific logic | High |
| LLM strategy implementers | Clear interface for custom providers | Medium |
| Maintainers | Controlled evolution without breaking changes | High |
| Operations | Graceful degradation when agentic model fails | Medium |

---

## 2. Context View (AV-01)

```
User Query
    │
    ▼
RAG Service (generate_answer)
    │
    ├─ Step 0: Security check (gatekeeper)
    │
    ├─ Step 1: Tool Selection
    │    ├─ Factory (detect provider capability)
    │    │
    │    ├─ Strategy Selection
    │    │  ├─ OpenAI/DeepSeek → native function calling
    │    │  ├─ Anthropic → tool use blocks
    │    │  ├─ Gemini → function declarations
    │    │  └─ Other/Fallback → prompt-based JSON routing
    │    │
    │    └─ LLM Invocation
    │        └─ Parse tool selection result
    │
    ├─ Step 2: Tool Execution (via filter hooks)
    │    ├─ search_content → RAG retrieval
    │    ├─ summarize_conversation → history synthesis
    │    ├─ respond_directly → LLM response only
    │    ├─ clarify_previous → elaboration
    │    ├─ compare_content → comparison analysis
    │    └─ custom_tool_{name} → developer extension
    │
    └─ Step 3: Response assembly (answer + sources + metadata)

Hook/Filter Layers
├─ gg_data_rag_tools (register/modify tool definitions)
├─ gg_data_rag_tool_selection (override selection logic)
├─ gg_data_rag_tool_{name} (custom handler per tool)
└─ gg_data_rag_tool_executed (tool execution notification)

Canonical Contracts
├─ docs/tools/srs.md
├─ docs/tools/architecture.md
└─ docs/tools/developer-documentation.md
```

**Mapping to requirements:**
- AV-01 → FR-T01 through FR-T08 (tool workflow)
- AV-01 → FR-T-STRAT-01 through FR-T-STRAT-05 (strategy pattern)

---

## 3. Component View (AV-02)

### 3.1 Tool Selection Layer

**Components:**
1. `GG_Data_Tool_Strategy_Factory` — Creates appropriate strategy based on provider and model capabilities
2. `GG_Data_Tool_Calling_Strategy` (interface) — Defines contract for all strategies
3. Provider-specific strategy implementations:
   - `GG_Data_OpenAI_Tool_Strategy` — OpenAI and DeepSeek function calling
   - `GG_Data_Anthropic_Tool_Strategy` — Claude tool use blocks
   - `GG_Data_Gemini_Tool_Strategy` — Gemini function declarations
   - `GG_Data_Prompt_Tool_Strategy` — Prompt-based fallback (any provider)

**Responsibilities:**
- Auto-detect provider capability (native tool calling support)
- Format tools in provider-specific format
- Invoke LLM with tool calling enabled
- Parse provider response and extract tool name + parameters
- Gracefully fallback on errors

### 3.2 Built-in Tool Definitions

**Tool Category: Information Retrieval**
- `search_content` — Retrieve relevant post/page content for query
  - Selection criteria: information-seeking queries, "what is X", "how to Y"
  - Returns: RAG answer with source citations

**Tool Category: Conversation**
- `summarize_conversation` — Extract context from conversation history
  - Selection criteria: "what did we discuss", "our conversation", "previously you said"
  - Returns: summary of prior exchanges

**Tool Category: Direct Response**
- `respond_directly` — Answer without retrieval
  - Selection criteria: greetings, meta-questions ("what can you do?"), time-based queries
  - Returns: direct LLM response (no sources)

**Tool Category: Clarification**
- `clarify_previous` — Rephrase or elaborate on last response
  - Selection criteria: "explain simpler", "more details", "give me an example"
  - Parameters: clarification_type (simpler|detailed|example|rephrase)
  - Returns: reformatted previous response

**Tool Category: Comparison**
- `compare_content` — Side-by-side comparison of topics
  - Selection criteria: "compare X and Y", "difference between A and B"
  - Parameters: items to compare
  - Returns: structured comparison (table or narrative)

### 3.3 Tool Execution Layer

**Components:**
1. Tool handler methods in `GG_Data_RAG_Service` (built-in tools)
2. Filter hook `gg_data_rag_tool_{name}` (custom tools)
3. Action hook `gg_data_rag_tool_executed` (execution notification)

**Contract:**
All tool handlers return:
```php
array(
    'answer'   => string,           // Main response text
    'sources'  => array,            // Cited references
    'metadata' => array,            // Execution context and metrics
)
```

### 3.4 Extension Contract (v1)

**Contract Motivation:**
To enable premium tool providers (e.g., gregius-intelligence) to extend RAG tools without hardcoding into foundation, we define a stable v1 handler capability surface.

**Contract Methods:**
1. `GG_Data_RAG_Service::stream_llm($prompt, $llm_model_id, $system_prompt, $progress_callback, $options)`
   - Purpose: Generate LLM response with optional token-by-token streaming
   - Visibility: public
   - Returns: array with `{text, reasoning_content, usage, provider, model, execution_time_ms}`

2. `GG_Data_RAG_Service::search_synthesize($query, $llm_model_id, $options, $start_time, $fallback)`
   - Purpose: Hybrid retrieval + LLM synthesis (mirrored search_content logic)
   - Visibility: public
   - Returns: array with `{answer, sources, metadata}`

3. `GG_Data_RAG_Service::append_manifest_metadata($result, $options)`
   - Purpose: Normalize response metadata with manifest tracking
   - Visibility: public
   - Returns: array with manifest metadata appended to `$result['metadata']`

**Contract Guarantees:**
- Method signatures remain stable across v1 minor versions (additive changes only)
- Return shapes documented and versioned
- Custom handlers must validate return shape: `{answer: string, sources: array, metadata: array}`
- All custom handlers execute in same execution context (connection, embedding model, LLM registry)

**Premium Tool Registration:**
- Tool definitions registered via `gg_data_rag_tools` filter
- Tool handlers hooked via `gg_data_rag_tool_{name}` filter
- No hardcoded tool cases in foundation (forced-tool or agentic routing)

**Migration Example (gregius-intelligence):**
```
Phase E: Premium tool provider initializes on gg_intelligence_init hook
├─ Hooks filter 'gg_data_rag_tools' → register summarize_current_entity + recommend_related_content
├─ Hooks filter 'gg_data_rag_tool_summarize_current_entity' → handler method
├─ Hooks filter 'gg_data_rag_tool_recommend_related_content' → handler method
└─ Handlers use contract methods: stream_llm(), search_synthesize(), append_manifest_metadata()
```

### 3.5 Extensibility Layer

**Hook: `gg_data_rag_tools`**
- Purpose: Register/modify tool definitions before selection
- Signature: `apply_filters('gg_data_rag_tools', array $tools): array`
- Use case: add custom tools, modify built-in descriptions, remove tools

**Hook: `gg_data_rag_tool_selection`**
- Purpose: Override tool selection logic
- Signature: `apply_filters('gg_data_rag_tool_selection', array $result, string $query, array $messages): array`
- Use case: force search for product queries, ban tools under conditions, custom routing

**Hook: `gg_data_rag_tool_{name}`**
- Purpose: Implement handler for custom tool
- Signature: `apply_filters("gg_data_rag_tool_{tool_name}", null, string $tool_name, array $context): ?array`
- Use case: custom tool logic without modifying core

**Hook: `gg_data_rag_tool_executed`**
- Purpose: Notify listeners of tool execution
- Signature: `do_action('gg_data_rag_tool_executed', string $tool_name, array $result, array $context): void`
- Use case: analytics, logging, metrics collection

---

## 4. Logical View (AV-03)

### 4.1 Tool Selection Flow

```
generate_answer(query, llm_model_id, options)
  │
  ├─ options['rewrite_model'] exists?
  │  ├─ YES → Proceed with agentic tool selection
  │  │   │
  │  │   ├─ Get agentic model from registry
  │  │   ├─ Factory::create_for_model(agentic_model_id)
  │  │   │   ├─ Detect provider (openai, anthropic, gemini, deepseek, other)
  │  │   │   └─ Detect native tool calling support → Select strategy
  │  │   │
  │  │   ├─ Build tool definitions
  │  │   │   └─ apply_filters('gg_data_rag_tools', $default_tools)
  │  │   │
  │  │   ├─ Invoke strategy→select_tool(query, messages, tools, model_id)
  │  │   │   └─ Provider API call (native or prompt-based)
  │  │   │
  │  │   ├─ Parse response → tool_selection array
  │  │   │
  │  │   ├─ apply_filters('gg_data_rag_tool_selection', result, query, messages)
  │  │   │
  │  │   └─ Route to tool handler (search_content, summarize, etc.)
  │  │
  │  └─ NO → Skip agentic routing, default to search_content
  │
  └─ Execute selected tool → answer + sources + metadata
```

### 4.2 Strategy Auto-Detection

```
Factory::create_for_model(model_id)
  │
  ├─ Lookup model in registry
  ├─ Extract provider (openai, anthropic, gemini, deepseek, other)
  │
  ├─ Check if provider supports native tool calling
  │  ├─ openai, deepseek → YES
  │  ├─ anthropic → YES
  │  ├─ gemini → YES
  │  └─ other → NO (fallback)
  │
  └─ Return strategy instance
     ├─ GG_Data_OpenAI_Tool_Strategy
     ├─ GG_Data_Anthropic_Tool_Strategy
     ├─ GG_Data_Gemini_Tool_Strategy
     └─ GG_Data_Prompt_Tool_Strategy (fallback)
```

---

## 5. Deployment View (AV-04)

Deployment is transparent to developers:
- Strategies are instantiated by factory at runtime
- No configuration required for native tool calling (auto-detected)
- Fallback routing activates automatically for unsupported providers
- Custom strategies can be registered by extending the factory

---

## 6. Architectural Decisions

### AD-T01: Strategy Pattern for Provider Routing

**Decision:** Use strategy pattern to encapsulate provider-specific tool calling.

**Rationale:**
- Decouples tool definitions from provider APIs
- Allows new providers to be added without changing RAG Service
- Enables graceful fallback to prompt-based routing
- Simplifies testing (each strategy testable in isolation)

**Implications:**
- New provider support requires implementing strategy interface
- Factory must be extended for each new provider (or auto-detection added)
- All providers must parse responses into consistent format

### AD-T02: Hook-Based Tool Registration

**Decision:** Use WordPress filters to allow third-party tool registration.

**Rationale:**
- No plugin modifications needed to extend tools
- Leverages existing WordPress extension patterns
- Supports tool removal/modification by consumers
- Enables A/B testing of tool definitions

**Implications:**
- Tools must be registered before RAG Service invocation
- Tool definitions are mutable (order of execution matters)
- Custom tools have responsibility for handler implementation

### AD-T03: Stable Tier 1 Tool Signatures

**Decision:** Commit to stable signatures for built-in tools (search_content, summarize_conversation, etc.).

**Rationale:**
- Developers can build on stable contracts
- Backward compatibility is predictable
- Tool parameters can be extended (optional fields only)

**Implications:**
- Tool signature changes require major version bump
- Deprecation period for signature changes
- Documentation must reflect all signature versions

### AD-T04: Prompt-Based Fallback for Native Tool Calling

**Decision:** Fall back to prompt-based JSON routing when native tool calling is unavailable.

**Rationale:**
- Ensures all LLM providers are supported
- Avoids hard dependencies on specific vendors
- Graceful degradation for new/unsupported providers

**Implications:**
- Prompt-based routing is slower and less reliable than native
- Tool selection may fail with no native support; prompting may not activate custom tools
- Development/testing should prefer native providers when possible

### AD-T05: Agnostic Extension Contract for Premium Tools

**Decision:** Define stable v1 handler capability contract (stream_llm, search_synthesize, append_manifest_metadata) exposed as public methods in foundation RAG service.

**Rationale:**
- Decouples premium tool implementations from foundation architecture
- Eliminates need to modify foundation for each new premium tool
- Ensures consistent LLM capabilities across all tools (foundation + custom)
- Enables vendor lock-in prevention: custom tools use same interface regardless of tool origin

**Implications:**
- Contract methods must remain stable across minor versions (backward compatible)
- Premium tool providers depend on foundation v1.0+; foundation changes require careful versioning
- All premium tools follow same registration pattern (gg_data_rag_tools filter + gg_data_rag_tool_{name} filter)
- Foundation tool definitions are removed from defaults; now registered via filter by premium plugins

**Related Architectural Decisions:**
- AD-T02 (Hook-Based Tool Registration) — uses gg_data_rag_tools filter
- AD-T04 (Prompt-Based Fallback) — custom tools fall through to handler filter like foundation tools

---

## 7. Constraints

- **C-T01:** All tools must return consistent {answer, sources, metadata} structure
- **C-T02:** Tool selection latency must not exceed 500ms (excluding LLM inference)
- **C-T03:** Strategies must gracefully degrade on API errors (fallback or timeout)
- **C-T04:** Built-in Tier 1 tools cannot be removed; only extended or overridden
- **C-T05:** Extension contract v1 method signatures must remain stable (minor versions only)
- **C-T06:** Forced-tool routing validates against available tools; tool not found returns error (no fallback)

---

## 8. Risks and Mitigation

| Risk | Impact | Mitigation |
|---|---|---|
| Provider API changes break native tool calling | High | Version pin models, fallback to prompt-based, monitor provider changelogs |
| Custom tool implementation breaks RAG flow | Medium | Document handler contract, provide examples, validate return shape |
| Tool selection latency degrades performance | Medium | Cache strategy selection, monitor latency metrics, optimize prompt |
| Agentic model becomes unavailable | Medium | Degrade gracefully to search_content, document fallback behavior |
| Tool parameter schema incompatibility | Medium | Use JSON Schema, validate in tests, allow optional fields only |
| Generic forced-tool routing without hardcoded whitelist | Low | Validate forced tool against available_tools list; return error if not found |
| Extension contract method signature changes | Medium | Version contract methods, use additive changes only, document deprecation periods |
| Premium tool dependencies on foundation v1.0+ | Medium | Version foundation clearly, maintain backward compatibility, communicate breaking changes |

---

## 9. Cross-Reference to SRS

- AV-01 → FR-T01 (built-in tools with stable signatures)
- AV-01 → FR-T02 (custom tool registration via hooks)
- AV-02 → FR-T03 (multi-provider support)
- AV-02 → FR-T04 (fallback for unsupported providers)
- AV-03 → FR-T05 (tool selection filtering)
- AV-03 → FR-T06 (consistent response structure)
- AD-T01 → FR-T-STRAT-01 through FR-T-STRAT-05 (strategy interface requirements)

---

## 10. Change Log

| Version | Date | Author | Summary |
|---|---|---|---|
| 1.1 | 2026-04-26 | Gregius | Added v1 extension contract; moved premium tools to gregius-intelligence; generic forced-tool routing; Phase A-D implementation |
| 1.0 | 2026-04-25 | Gregius | Initial architecture for tools subsystem |
