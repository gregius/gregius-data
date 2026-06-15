# Developer Documentation: Tools and Tool Selection

Standard: ISO/IEC/IEEE 26514:2022
Component: Tools and Tool Selection Subsystem
Version: 1.0.0
Date: 2026-04-25
Upstream Architecture: [architecture.md](architecture.md)
Upstream Specification: [srs.md](srs.md)

---

## 1. Quick Start

Use WordPress-native filter and action APIs to extend tools:

```php
// Register a custom tool
add_filter( 'gg_data_rag_tools', function( $tools ) {
    $tools['my_tool'] = array(
        'name'        => 'my_tool',
        'description' => 'Does something useful',
        'parameters'  => array(
            'type'       => 'object',
            'properties' => array(
                'topic' => array( 'type' => 'string' ),
            ),
        ),
    );
    return $tools;
}, 10 );

// Implement the tool handler
add_filter( 'gg_data_rag_tool_my_tool', function( $result, $tool_name, $context ) {
    $answer = 'Custom response for: ' . $context['query'];
    return array(
        'answer'   => $answer,
        'sources'  => array(),
        'metadata' => array( 'custom_field' => 'value' ),
    );
}, 10, 3 );

// Listen for tool execution
add_action( 'gg_data_rag_tool_executed', function( $tool_name, $result, $context ) {
    error_log( "Tool executed: $tool_name" );
}, 10, 3 );
```

---

## 2. Built-in Tool Catalog (Tier 1)

All built-in tools have stable signatures and consistent return structure.

### 2.1 Tool: `search_content`

**Purpose:** Retrieve relevant content from the knowledge base and synthesize an answer.

**When Invoked:**
- User asks information-seeking questions: "What is X?", "How do I Y?", "Tell me about Z"
- Agentic model detects high confidence that content retrieval is needed
- No ambiguity in the query

**Selection Example:**
```json
{
  "tool": "search_content",
  "search_query": "how to reset password",
  "reason": "information-seeking query with clear intent"
}
```

**Handler Signature:**
```php
/**
 * Execute search_content tool (built-in).
 *
 * @param string $query          User's original query.
 * @param string $llm_model_id   LLM model to use for generation.
 * @param array  $options        RAG options (num_chunks, messages, etc.).
 * @return array {
 *     'answer'   => string,           // Full RAG answer with inline citations
 *     'sources'  => array,            // Cited references with metadata
 *     'metadata' => array,            // Execution details (tokens, model, etc.)
 * }
 */
```

**Response Structure:**
```php
array(
    'answer'   => 'The process involves clicking Settings > Security > Change Password...',
    'sources'  => array(
        array(
            'post_id'  => 42,
            'title'    => 'Account Management',
            'url'      => 'https://...',
            'excerpt'  => 'Settings page allows you to...',
        ),
    ),
    'metadata' => array(
        'chunks_used'     => 3,
        'embedding_model' => 'text-embedding-3-small',
        'llm_model'       => 'gpt-4o-mini',
        'execution_time'  => 1245,  // milliseconds
        'tokens_used'     => array( 'input' => 450, 'output' => 120 ),
    ),
)
```

**Parameters in Tool Selection:**
- `search_query` (string, optional) — Optimized search query (may differ from original)

---

### 2.2 Tool: `summarize_conversation`

**Purpose:** Summarize conversation history to answer questions about prior exchanges.

**When Invoked:**
- User asks: "What did we discuss?", "Our conversation said...", "Previously you mentioned..."
- Requires conversation history in `options['messages']`

**Selection Example:**
```json
{
  "tool": "summarize_conversation",
  "reason": "user asking about prior discussion"
}
```

**Handler Signature:**
```php
/**
 * Execute summarize_conversation tool (built-in).
 *
 * @param string $query          User's query about conversation.
 * @param string $llm_model_id   LLM model to use.
 * @param array  $options        RAG options (messages, conversation_id, etc.).
 * @return array {
 *     'answer'   => string,
 *     'sources'  => array,    // Empty or contains turn references
 *     'metadata' => array,
 * }
 */
```

**Response Structure:**
```php
array(
    'answer'   => 'We discussed password reset procedures and security best practices...',
    'sources'  => array(),  // No external sources; based on conversation
    'metadata' => array(
        'turns_summarized' => 8,
        'llm_model'        => 'gpt-4o-mini',
        'execution_time'   => 850,
        'tokens_used'      => array( 'input' => 650, 'output' => 95 ),
    ),
)
```

**Parameters in Tool Selection:**
- (none — uses `messages` from options)

---

### 2.3 Tool: `respond_directly`

**Purpose:** Answer without retrieving external content.

**When Invoked:**
- User greets: "Hi", "Hello", "Good morning"
- User asks meta-questions: "What can you do?", "Who are you?"
- Query is ambiguous or under-specified: "The thing with the...stuff?"
- Query is time-sensitive: "What time is it?"

**Selection Example:**
```json
{
  "tool": "respond_directly",
  "reason": "greeting"
}
```

**Handler Signature:**
```php
/**
 * Execute respond_directly tool (built-in).
 *
 * @param string $query        User's query.
 * @param string $llm_model_id LLM model to use.
 * @param array  $options      RAG options.
 * @return array {
 *     'answer'   => string,
 *     'sources'  => array,    // Always empty
 *     'metadata' => array,
 * }
 */
```

**Response Structure:**
```php
array(
    'answer'   => 'Hello! I\'m here to help answer questions about...',
    'sources'  => array(),
    'metadata' => array(
        'tool_type'      => 'direct_response',
        'llm_model'      => 'gpt-4o-mini',
        'execution_time' => 280,
        'tokens_used'    => array( 'input' => 50, 'output' => 45 ),
    ),
)
```

**Parameters in Tool Selection:**
- `reason` (string, optional) — Why direct response was chosen (greeting, meta_question, etc.)

---

### 2.4 Tool: `clarify_previous`

**Purpose:** Rephrase or elaborate on the previous response.

**When Invoked:**
- User requests rephrasing: "Explain that simpler", "In other words...", "Can you rephrase?"
- User requests elaboration: "More details", "Tell me more", "Give me an example"
- User seeks different angle: "Try a different approach", "What about...?"

**Selection Example:**
```json
{
  "tool": "clarify_previous",
  "clarification_type": "simpler",
  "reason": "user requested simpler explanation"
}
```

**Handler Signature:**
```php
/**
 * Execute clarify_previous tool (built-in).
 *
 * @param string $query                User's clarification request.
 * @param string $llm_model_id         LLM model to use.
 * @param array  $options              RAG options (messages with prior response).
 * @param string $clarification_type   Type: 'simpler', 'detailed', 'example', 'rephrase'.
 * @return array {
 *     'answer'   => string,           // Reformatted previous response
 *     'sources'  => array,            // Same sources as original
 *     'metadata' => array,
 * }
 */
```

**Response Structure:**
```php
array(
    'answer'   => 'In simpler terms: you change your password by...',
    'sources'  => array(
        // Same as original response
    ),
    'metadata' => array(
        'clarification_type' => 'simpler',
        'based_on_turn'      => 3,      // Conversation turn number
        'llm_model'          => 'gpt-4o-mini',
        'execution_time'     => 620,
        'tokens_used'        => array( 'input' => 400, 'output' => 80 ),
    ),
)
```

**Parameters in Tool Selection:**
- `clarification_type` (string, required) — One of: 'simpler', 'detailed', 'example', 'rephrase'

---

### 2.5 Tool: `compare_content`

**Purpose:** Compare multiple topics or items side-by-side.

**When Invoked:**
- User compares: "Compare X and Y", "What's the difference between A and B?"
- User requests pros/cons analysis: "Advantages and disadvantages of..."
- Multi-item comparison: "How do these three approaches differ?"

**Selection Example:**
```json
{
  "tool": "compare_content",
  "items": ["password reset", "two-factor authentication"],
  "reason": "user asking for comparison"
}
```

**Handler Signature:**
```php
/**
 * Execute compare_content tool (built-in).
 *
 * @param string $query        Comparison query.
 * @param string $llm_model_id LLM model to use.
 * @param array  $options      RAG options.
 * @param array  $tool_selection Tool selection result (includes items).
 * @return array {
 *     'answer'   => string,           // Comparison (table or narrative)
 *     'sources'  => array,            // Sources for both items
 *     'metadata' => array,
 * }
 */
```

**Response Structure:**
```php
array(
    'answer'   => "| Feature | Password Reset | 2FA |\n|---------|---|---|\n| Speed | Fast | Moderate |\n...",
    'sources'  => array(
        array( 'title' => 'Password Reset Guide', ... ),
        array( 'title' => 'Two-Factor Authentication', ... ),
    ),
    'metadata' => array(
        'items_compared'  => 2,
        'embedding_model' => 'text-embedding-3-small',
        'llm_model'       => 'gpt-4o-mini',
        'execution_time'  => 1890,
        'tokens_used'     => array( 'input' => 520, 'output' => 180 ),
    ),
)
```

**Parameters in Tool Selection:**
- `items` (array, optional) — List of items/topics to compare

---

## 3. Tool Selection Contract

All tools receive the same execution context via the `gg_data_rag_tool_{name}` filter:

```php
/**
 * Custom tool handler filter.
 *
 * @since 1.0.0
 * @param null|array $result             Null to proceed; array to short-circuit.
 * @param string     $tool_name          The selected tool name.
 * @param array      $context {
 *     @type string   $query             User's original query.
 *     @type string   $llm_model_id      LLM model ID for generation.
 *     @type array    $options {
 *         @type array        $messages            Conversation history.
 *         @type string       $connection_name     Database connection name.
 *         @type string       $rewrite_model       Agentic model ID.
 *         @type int          $num_chunks          Number of chunks to retrieve.
 *         @type array        $post_types          Post types to include.
 *         @type callable|null $progress_callback  Callback for SSE progress.
 *         @type string       $conversation_id     Conversation tracking ID.
 *     }
 *     @type array    $tool_selection {
 *         @type string   $tool                Tool name.
 *         @type string   $reason              Why this tool was selected.
 *         @type string   $search_query        Optimized search query (for search_content).
 *         @type string   $clarification_type  Type of clarification (for clarify_previous).
 *     }
 *     @type string   $trigger            'llm' = agentic selection, 'direct' = fallback.
 *     @type string   $connection_name    Connection name (same as in options).
 * }
 * @return array|null Tool result or null to fall back to built-in handler.
 */
apply_filters( "gg_data_rag_tool_{tool_name}", null, $tool_name, $context )
```

### 3.1 Manifest Contract for Tool Implementers

Tool implementers should treat manifest behavior as a RAG-owned contract and use this section as an onboarding summary.

Canonical contract reference:
- [RAG Manifest Contract](../rag/manifest-contract.md)

Compact integration guidance:
- Send `manifest` as an object when your tool behavior depends on entity or taxonomy context.
- Send `forced_tool` when deterministic execution is required (`summarize_current_entity`, `recommend_related_content`).
- Do not depend on producer-specific raw manifest shapes; rely on normalized manifest behavior documented in the canonical contract.
- Preserve parity for `manifest` and `forced_tool` across REST and SSE clients.
- When `forced_tool` is unsupported, expect standard routing fallback behavior.

---

## 4. Extension Recipes

### Recipe 1: Register a Custom Tool

**Scenario:** Add a custom tool that searches a third-party API (e.g., internal wiki).

```php
add_filter( 'gg_data_rag_tools', function( $tools ) {
    $tools['wiki_search'] = array(
        'name'        => 'wiki_search',
        'description' => 'Search the internal wiki for knowledge base articles',
        'parameters'  => array(
            'type'       => 'object',
            'properties' => array(
                'query'     => array(
                    'type'        => 'string',
                    'description' => 'Search query for the wiki',
                ),
                'category'  => array(
                    'type'        => 'string',
                    'description' => 'Optional wiki category to search',
                ),
            ),
            'required'   => array( 'query' ),
        ),
    );
    return $tools;
}, 10 );
```

**Key Points:**
- Tool name must be lowercase with underscores
- `parameters` follows JSON Schema for native tool calling compatibility
- Tool appears in agentic model's tool list (if available)
- Tool is now eligible for selection

---

### Recipe 2: Implement a Custom Tool Handler

**Scenario:** Handle the `wiki_search` tool by querying an external API.

```php
add_filter( 'gg_data_rag_tool_wiki_search', function( $result, $tool_name, $context ) {
    if ( null !== $result ) {
        return $result; // Another filter handled it
    }

    $query = $context['tool_selection']['search_query'] ?? $context['query'];
    $category = $context['tool_selection']['category'] ?? '';

    // Call external wiki API
    $wiki_results = wp_remote_post( 'https://wiki.example.com/api/search', array(
        'body' => wp_json_encode( array(
            'q'        => $query,
            'category' => $category,
        ) ),
    ) );

    if ( is_wp_error( $wiki_results ) ) {
        return array(
            'answer'   => 'Sorry, I could not reach the wiki.',
            'sources'  => array(),
            'metadata' => array( 'error' => $wiki_results->get_error_message() ),
        );
    }

    $wiki_data = json_decode( wp_remote_retrieve_body( $wiki_results ), true );
    $sources = array();
    $answer = 'Found ' . count( $wiki_data['results'] ?? array() ) . ' results: ';

    foreach ( $wiki_data['results'] ?? array() as $result ) {
        $sources[] = array(
            'title'   => $result['title'],
            'url'     => $result['url'],
            'excerpt' => $result['snippet'] ?? '',
        );
        $answer .= $result['title'] . ', ';
    }

    return array(
        'answer'   => rtrim( $answer, ', '),
        'sources'  => $sources,
        'metadata' => array(
            'tool_type'      => 'external_api',
            'wiki_results'   => count( $wiki_data['results'] ?? array() ),
            'execution_time' => intval( $context['options']['progress_callback'] ?? 0 ),
        ),
    );
}, 10, 3 );
```

**Key Points:**
- Handler receives `$context` with query, messages, model, options
- Return null to let built-in handler run; return array to short-circuit
- All handlers must return {answer, sources, metadata}
- Handler is invoked after tool selection

---

### Recipe 3: Override Tool Selection Logic

**Scenario:** Always use `search_content` for product-related queries.

```php
add_filter( 'gg_data_rag_tool_selection', function( $result, $query, $messages ) {
    // Force search_content for product queries
    if ( preg_match( '/\b(product|feature|specification|pricing)\b/i', $query ) ) {
        return array(
            'tool'         => 'search_content',
            'search_query' => $query,
            'reason'       => 'product_query_guardrail',
        );
    }

    return $result; // Use agentic model's selection
}, 10, 3 );
```

**Key Points:**
- Hook runs after agentic model selects tool
- You can override selection or let agentic model decide
- Return consistent tool selection array
- Use for policy enforcement (guardrails, access control)

---

### Recipe 4: Listen to Tool Execution

**Scenario:** Track which tools are used for analytics.

```php
add_action( 'gg_data_rag_tool_executed', function( $tool_name, $result, $context ) {
    // Log tool usage to analytics service
    wp_remote_post( 'https://analytics.example.com/api/track', array(
        'body' => wp_json_encode( array(
            'event'        => 'tool_executed',
            'tool_name'    => $tool_name,
            'query'        => $context['query'],
            'is_error'     => is_wp_error( $result ),
            'execution_ms' => $result['metadata']['execution_time'] ?? 0,
        ) ),
    ) );
}, 10, 3 );
```

**Key Points:**
- Action fires after every tool execution (built-in and custom)
- Use for analytics, logging, metrics collection
- Does not affect tool execution; informational only
- `$result` may be WP_Error if tool execution failed

---

## 5. Tool Strategy Reference

The strategy pattern abstracts away provider-specific tool calling. Developers typically don't need to implement new strategies unless adding support for a new LLM provider.

### 5.1 Strategy Interface Overview

All strategies implement `GG_Data_Tool_Calling_Strategy`:

```php
interface GG_Data_Tool_Calling_Strategy {

    /**
     * Select the appropriate tool for the query.
     *
     * @param string $query    User's query.
     * @param array  $messages Conversation history.
     * @param array  $tools    Available tools with descriptions.
     * @param string $model_id Model ID to use for selection.
     * @return array|WP_Error {
     *     @type string $tool               Tool name (e.g., 'search_content').
     *     @type string $reason             Why tool was selected (optional).
     *     @type string $search_query       Optimized search terms (for search_content).
     *     @type string $clarification_type Clarification type (for clarify_previous).
     * }
     */
    public function select_tool( string $query, array $messages, array $tools, string $model_id ): array|WP_Error;

    /**
     * Format tools for the provider's API.
     *
     * @param array $tools Tool definitions in standard format.
     * @return array Formatted tools for API request.
     */
    public function format_tools( array $tools ): array;

    /**
     * Parse the tool selection response.
     *
     * @param mixed $response Provider's raw response.
     * @return array Parsed tool selection.
     */
    public function parse_response( $response ): array;
}
```

### 5.2 Built-in Strategy Implementations

**OpenAI Strategy** (`GG_Data_OpenAI_Tool_Strategy`)
- Used for: OpenAI (gpt-4o, gpt-4o-mini) and DeepSeek models
- Format: OpenAI `tools` array with `type: "function"` wrapper
- Parser: Extracts `tool_calls[0].function.name` and parses arguments

**Anthropic Strategy** (`GG_Data_Anthropic_Tool_Strategy`)
- Used for: Claude models (all versions)
- Format: Anthropic `tools` array with `input_schema` (JSON Schema)
- Parser: Extracts `tool_use` content block with name and input

**Gemini Strategy** (`GG_Data_Gemini_Tool_Strategy`)
- Used for: Google Gemini 2.5, 2.0, 1.5 models
- Format: Gemini `tools` with `function_declarations` array
- Parser: Extracts `functionCall` from response parts

**Prompt-Based Strategy** (`GG_Data_Prompt_Tool_Strategy`)
- Used for: All providers (fallback)
- Format: Tools as plain text list in system prompt with JSON response format instruction
- Parser: Extracts JSON from response text, handles markdown code blocks

### 5.3 Provider Auto-Detection

Factory auto-detects provider capability:

```php
$strategy = GG_Data_Tool_Strategy_Factory::create_for_model( $model_id );
```

Detection logic:
1. Lookup model in registry → extract provider
2. Check if provider is in native tools list (openai, anthropic, gemini, deepseek)
3. If yes → instantiate provider-specific strategy
4. If no → instantiate fallback prompt-based strategy
5. Return strategy instance

---

## 6. Troubleshooting

### "My custom tool is not being called"

**Checklist:**
1. ✓ Is the tool registered in `gg_data_rag_tools` filter?
   ```php
   add_filter('gg_data_rag_tools', function($tools) {
       if ( !isset($tools['my_tool']) ) {
           error_log('Tool not registered!');
       }
       return $tools;
   }, 10);
   ```

2. ✓ Does the agentic model (rewrite_model) exist and have provider support?
   - Check: `wp db query "SELECT * FROM wp_gg_data_models WHERE model_key='your_agentic_model'"`
   - Provider must be in: openai, anthropic, gemini, deepseek

3. ✓ Is the tool description clear enough for the LLM to select it?
   - Avoid vague descriptions like "Does stuff"
   - Use active, clear language: "Search for product pricing information"

4. ✓ Are you checking logs for tool selection?
   - Enable debug logging: `define('GG_DATA_DEBUG', true);`
   - Check: `wp debug.log` for "Tool selected: my_tool"

### "Tool selection always defaults to search_content"

**Diagnosis:**
- Agentic model is not configured or not in registry
- Provider doesn't support native tool calling (falling back to prompt-based)
- Prompt-based routing is failing silently

**Solution:**
1. Verify agentic model is configured:
   ```php
   if (!empty($options['rewrite_model'])) {
       error_log('Agentic model: ' . $options['rewrite_model']);
   } else {
       error_log('No agentic model configured');
   }
   ```

2. Check model in registry:
   ```php
   $registry = new GG_Data_Model_Registry();
   $model = $registry->get_model('gregius-data', $options['rewrite_model']);
   if (!$model) {
       error_log('Model not found in registry');
   }
   ```

3. Verify provider is native-capable:
   ```php
   $provider = $model['provider'] ?? 'unknown';
   $native_providers = array('openai', 'anthropic', 'gemini', 'deepseek');
   if (!in_array($provider, $native_providers)) {
       error_log("Provider $provider does not support native tool calling");
   }
   ```

### "Provider doesn't support my tool"

**Issue:** Native tool calling providers (OpenAI, Anthropic, Gemini) validate tool schemas strictly.

**Solutions:**
1. Use valid JSON Schema for `parameters`:
   ```php
   'parameters' => array(
       'type'       => 'object',
       'properties' => array(
           'query' => array('type' => 'string'),
           'limit' => array('type' => 'integer', 'minimum' => 1, 'maximum' => 100),
       ),
       'required'   => array('query'),
   ),
   ```

2. Make all parameters optional except critical ones
3. Test with prompt-based strategy first (most forgiving):
   - Temporarily disable agentic model
   - Check if tool appears in prompt
   - Verify JSON response parsing works

4. Consult provider API docs for schema requirements:
   - OpenAI: https://platform.openai.com/docs/api-reference/chat/create#tools
   - Anthropic: https://docs.anthropic.com/claude/reference/tool-use
   - Gemini: https://ai.google.dev/api/rest/v1beta/tools/create

---

## 7. API Reference

### GG_Data_RAG_Service::select_tool()

```php
/**
 * Select appropriate tool for query using agentic routing.
 *
 * @since 1.0.0
 * @param string $query          User query.
 * @param array  $messages       Conversation history.
 * @param string $model_id       Agentic model ID.
 * @return array|WP_Error {
 *     @type string $tool        Selected tool name.
 *     @type string $reason      Selection reason.
 * }
 */
private function select_tool( $query, $messages, $model_id )
```

**Usage:**
```php
$result = $this->select_tool('How do I reset my password?', array(), 'gpt-4o-mini');
if (!is_wp_error($result)) {
    echo "Selected tool: " . $result['tool'];
}
```

### GG_Data_Tool_Strategy_Factory::create_for_model()

```php
/**
 * Create strategy for a model.
 *
 * @since 1.0.0
 * @param string $model_id Model ID (e.g., 'gpt-4o-mini', 'claude-opus-4-20250514').
 * @return GG_Data_Tool_Calling_Strategy Strategy instance.
 */
public static function create_for_model( string $model_id ): GG_Data_Tool_Calling_Strategy
```

**Usage:**
```php
$strategy = GG_Data_Tool_Strategy_Factory::create_for_model('gpt-4o-mini');
$selection = $strategy->select_tool($query, $messages, $tools, 'gpt-4o-mini');
```

---

## 8. Performance Considerations

- **Tool selection latency:** Typically 200-500ms (excluding LLM inference)
- **Native tool calling:** Faster (API-native support)
- **Prompt-based fallback:** Slower (extra prompt engineering, JSON parsing)
- **Custom tool latency:** Depends on handler implementation; avoid blocking calls

**Optimization Tips:**
- Cache strategy selection if possible
- Use prompt-based strategy for development; native for production
- Monitor tool execution times in metadata

---

## 9. Compatibility Notes

- **WordPress:** 6.0+
- **PHP:** 7.4+
- **LLM Providers:** OpenAI (GPT-4o, 4o-mini), Anthropic (Claude), Google (Gemini), DeepSeek
- **Other providers:** Supported via fallback prompt-based routing

---

## 10. Related Documentation

- [SRS: Tool Subsystem Requirements](srs.md)
- [Architecture: Tool Selection and Strategy Pattern](architecture.md)
- [docs/hooks/](../hooks/developer-documentation.md) — Hook extensions (similar pattern)
- [docs/rag/](../rag/developer-documentation.md) — RAG workflow integration
- [docs/providers/](../providers/developer-documentation.md) — LLM provider setup

---

## 11. Change Log

| Version | Date | Author | Summary |
|---|---|---|---|
| 1.0 | 2026-04-25 | Gregius | Initial developer documentation for tools subsystem |
