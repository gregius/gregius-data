# Hooks — Extending Gregius Data Through Filters and Actions

[Editorial: Feature: Hooks | Pages: 1 | Total words: 800 | Sections: 6 (H2: 5, H3: 5) | Screenshots: 0 | Doc category: /docs/category/developer/ | Audience: Developer]

## Overview

Gregius Data offers **site developers and plugin integrators** a controlled orchestration surface — WordPress actions and filters spanning every major subsystem: sync, chunking, search, RAG, interactions, logging, and provider communication. Each extension point ships with a documented signature, a default value, and a stability tier so you know exactly how firmly you can build on it.

### Prerequisites

- WordPress 6.9+ with Gregius Data plugin installed and activated
- Familiarity with WordPress `add_filter` and `add_action` APIs
- Access to theme `functions.php`, an mu-plugin, or a custom plugin for your extension code

---

## Understanding Hook Tiers

Every Gregius Data hook is classified into a stability tier that tells you how safe it is to build on. All hooks use the `gg_data_` prefix for consistent discovery.

### Tier 1 public hooks

Tier 1 hooks are stable, documented extension contracts — tuned for third-party use. Their names, signatures, and default values stay backward compatible within minor releases. Build long-term integrations on Tier 1.

Examples of Tier 1 areas:

- Sync control — which posts to sync, which meta keys to skip
- Chunking strategy — custom chunking logic and payload shaping
- Search tuning — relevance weights, similarity thresholds, rate limits
- Interaction tracking — custom metadata and post-record callbacks
- RAG lifecycle — retrieval filtering, LLM response post-processing, tool registration
- Log governance — retention policy, pre- and post-purge observability
- Vector operations — batch deletion tuning

### Tier 2 semi-public hooks

Tier 2 hooks are in active code and available for advanced integration, but their signatures may evolve more freely. Build on them with defensive guardrails — prefer Tier 1 where a comparable hook exists.

Tier 2 areas include:

- RAG governance — retrieval policy, rerank policy, threshold profiles, abstain messaging, permission filtering
- Provider surface — OpenAI, Anthropic, Gemini, DeepSeek request/response filtering
- Operational controls — batch sizes for clean and orphan operations, CLI batch limits, rate limit tuning
- Observability — streaming failure, token usage tracking and reset

### Tier 3 internal hooks

Tier 3 hooks are implementation internals — not a stable public API. They cover RAG planner internals, comparison pipeline thresholds, and fine-grained policy controls. Do not build critical external integrations on Tier 3 hooks.

---

## Extension Categories

Gregius Data hooks are organized by the subsystem they extend:

| Category | What you can control | Example hooks |
|---|---|---|
| Configuration and batch | Tune throughput for sync, embeddings, vector deletion | `gg_data_post_sync_batch_size`, `gg_data_vector_delete_batch_size` |
| Sync control | Filter which posts and meta keys are synced, enrich metadata | `gg_data_should_sync_post`, `gg_data_metadata_manifest` |
| Chunking | Register custom chunk strategies, shape chunk output | `gg_data_chunking_strategies`, `gg_data_embedding_chunks` |
| Search | Adjust relevance weights, similarity thresholds, rate limits | `gg_data_search_title_weight`, `gg_data_search_rate_limit` |
| Interaction | Add custom analytics metadata, react to recorded interactions | `gg_data_interaction_meta_fields`, `gg_data_interaction_feedback_received` |
| RAG tools | Register tools, intercept tool execution, react to completion | `gg_data_rag_tools`, `gg_data_rag_complete` |
| RAG lifecycle | Filter retrieval queries, pre-retrieval criteria, LLM responses, source relevance | `gg_data_rag_search_query`, `gg_data_rag_llm_response` |
| RAG governance | Set retrieval policy, rerank policy, threshold profiles, rate limits | `gg_data_rag_retrieval_policy`, `gg_data_rag_rate_limit` |
| Provider | Intercept API requests and responses per provider | `gg_data_openai_request`, `gg_data_anthropic_call` |
| Log retention | Override retention period, observe purge events | `gg_data_log_retention_days`, `gg_data_after_purge_logs` |

---

## Getting Started with Hooks

All hooks use standard WordPress `add_filter` and `add_action` APIs. Place your extension code in a custom plugin, an mu-plugin, or your theme's `functions.php`.

When choosing a hook, check its tier in the developer documentation:

- **Tier 1** — safe for production, backward compatible within minor releases
- **Tier 2** — useful for advanced use cases, test after updates
- **Tier 3** — implementation details, avoid for critical integrations

Each documented hook includes its type (filter or action), parameter signature, default value, and source file location. Filters must return the expected value type; actions are fire-and-forget.

For a complete catalog of every hook with signatures, defaults, examples, and source file references, see the developer documentation.
