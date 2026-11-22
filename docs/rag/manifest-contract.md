# RAG Manifest Contract

Standard: ISO/IEC/IEEE 26514:2022

## 1. Purpose and Ownership

This document is the canonical contract reference for RAG request manifest handling and deterministic tool overrides.

Contract owner: RAG subsystem (`docs/rag/*`).

Consumer surfaces:
- Tools onboarding and tool implementer workflows (`docs/tools/*`)
- REST transport contracts (`docs/rest-api/*`)
- Interaction telemetry persistence (`docs/interactions/*`)

This page intentionally centralizes schema and behavior rules to prevent drift.

## 2. Scope and Boundaries

In scope:
- Request fields `manifest` and `forced_tool`
- Manifest normalization behavior and compatibility rules
- Deterministic override precedence
- Metadata fields emitted in metadata and persisted by interactions

Out of scope:
- Full retrieval policy documentation
- LLM provider-specific tool-calling details
- UI rendering behavior in downstream clients

## 3. Request Contract

### 3.1 Request Fields

- `manifest` (object, optional)
  - Structured context envelope for entity and scoped recommendation/summarization behavior.
  - Normalized before downstream tool handlers consume it.
- `forced_tool` (string, optional)
  - Deterministic tool override.
  - Valid values are any tool registered via the `gregius_data_rag_tools` filter (for example `search_content`, `compare_content`, `search_entity_content`).

### 3.2 Deterministic Override Behavior

- When `forced_tool` matches an allowed deterministic tool, that tool executes before optional agentic routing.
- When `forced_tool` is absent, empty, or unsupported, routing falls back to standard behavior.
- Unsupported values are non-fatal and do not alter baseline retrieval behavior.

## 4. Canonical Manifest Shape

Manifest payloads are normalized to a canonical schema (version `1.0.0`) before tool execution.

Example normalized shape:

```json
{
  "manifest_version": "1.0.0",
  "state": {
    "conversation_id": "",
    "journey_depth_turns": 0,
    "is_historical_resume": false,
    "active_mode": "default",
    "recent_mixed_initiatives": []
  },
  "entity": {
    "id": 377,
    "type": "page",
    "status": "",
    "title": { "raw": "", "rendered": "" },
    "dates": { "created_gmt": "", "modified_gmt": "" },
    "author": { "id": 0, "name": "" },
    "taxonomies": {},
    "meta": {},
    "raw_context": {}
  },
  "search_capabilities": {
    "primary_index": "wp_posts_clean",
    "supported_filters": [],
    "fallback_strategies": []
  }
}
```

Notes:
- Producers may send partial payloads; missing fields are filled with safe defaults.
- Normalization drops unknown or malformed blocks instead of failing the turn.
- Consumers should depend on normalized outputs, not producer-specific raw shapes.

## 5. Normalization Rules

- Normalize manifest to supported schema version before tool handlers read it.
- Preserve deterministic behavior when no valid manifest exists.
- Treat malformed manifest blocks as optional data failures, not request failures.
- Keep unknown keys non-breaking unless explicitly promoted into the canonical schema.

Canonical validator implementation reference:
- `includes/rag/class-gregius-data-manifest-validator.php`

## 6. Execution Precedence

High-level order:
1. Validate/sanitize request inputs.
2. Normalize `manifest`.
3. Evaluate deterministic `forced_tool` override.
4. If no deterministic override applies, run standard routing.
5. Execute selected tool or standard retrieval/generation path.

This preserves backward compatibility for callers that do not send `manifest` or `forced_tool`.

## 7. Metadata and Persistence Contract

When manifest context is present, metadata may include:
- `manifest`
- `manifest_hash`
- `manifest_size_bytes`
- `manifest_version`

Interaction recording consumers persist these values for diagnostics, replay, and drift checks.

## 8. Transport Parity Requirements

RAG request paths that support chat execution must preserve parity for:
- `manifest`
- `forced_tool`

This applies to REST and SSE transport surfaces so deterministic behavior does not depend on interface choice.

## 9. Compatibility and Versioning

- Current contract version: `1.0.0`
- Additive changes should be backward compatible where possible.
- Any key/type change to canonical fields requires migration notes in this document and linked SRS/architecture docs.
- Deprecated fields should be documented with transition guidance before removal.

## 10. Consumer Matrix

| Consumer | Responsibility |
|---|---|
| `docs/rag/*` | Canonical contract ownership and runtime behavior |
| `docs/tools/*` | Implementer onboarding summary and integration examples |
| `docs/rest-api/*` | Request/transport contract references and endpoint payload notes |
| `docs/interactions/*` | Persistence and telemetry metadata behavior |

## 11. Traceability

Primary requirement references:
- `RAG-FR-24` to `RAG-FR-25`
- `RAG-DR-13`
- `REST-FR-18`
- `REST-DR-13`
- `INT-DR-08`

See:
- `docs/rag/srs.md`
- `docs/rest-api/srs.md`
- `docs/interactions/srs.md`
