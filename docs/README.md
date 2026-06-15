# Gregius Data Plugin - Documentation

## Documentation Structure

This documentation is organized for **active development**. User-facing documentation will be written before public release.
## 📁 Current Documentation

### Architecture & Reference (Root)

**Technical documentation for developers building and extending the plugin:**

- **[interface/](interface/)** - Interface subsystem documentation
  - **[interface/srs.md](interface/srs.md)** - Software Requirements Specification for dashboard contracts, interface state behavior, and feature integration obligations
  - **[interface/architecture.md](interface/architecture.md)** - Architecture views, decisions, constraints, and risks for dashboard presentation and client integration flows
  - **[interface/developer-documentation.md](interface/developer-documentation.md)** - Developer reference for app shell/tab patterns, stores, apiFetch integration, and troubleshooting

- **[rag-benchmark/architecture.md](rag-benchmark/architecture.md)** - RAG quality benchmark architecture
  - Canonical benchmark architecture reference
  - Prompt matrix and execution model
  - Artifact and scorecard architecture
  - Runner direction and transition notes

- **[rag-evaluation/](rag-evaluation/)** - RAG evaluation subsystem documentation
  - **[rag-evaluation/srs.md](rag-evaluation/srs.md)** - Software Requirements Specification for RAG evaluation, scoring, and quality measurement
  - **[rag-evaluation/architecture.md](rag-evaluation/architecture.md)** - Architecture views, decisions, constraints, and risks for evaluation framework and artifact management
  - **[rag-evaluation/developer-documentation.md](rag-evaluation/developer-documentation.md)** - Developer reference for evaluation runners, scorecards, and benchmark integration

- **[search/](search/)** - Search subsystem documentation
  - **[search/srs.md](search/srs.md)** - Software Requirements Specification for search orchestration and controls
  - **[search/architecture.md](search/architecture.md)** - Architecture views, decisions, constraints, and risks for search execution
  - **[search/developer-documentation.md](search/developer-documentation.md)** - Developer reference for SQL functions, provider paths, endpoints, and extension points

- **[vectors/](vectors/)** - Vectors and embeddings subsystem documentation
  - **[vectors/srs.md](vectors/srs.md)** - Software Requirements Specification for vector generation, vocabulary lifecycle, and model contracts
  - **[vectors/architecture.md](vectors/architecture.md)** - Architecture views, decisions, constraints, and risks for vector orchestration and storage contracts
  - **[vectors/developer-documentation.md](vectors/developer-documentation.md)** - Developer reference for strategies, endpoints, storage contracts, and troubleshooting

- **[schema/](schema/)** - Schema management subsystem documentation
  - **[schema/srs.md](schema/srs.md)** - Software Requirements Specification for schema lifecycle, versioning, and provider-aware setup
  - **[schema/architecture.md](schema/architecture.md)** - Architecture views, decisions, constraints, and risks for schema creation and upgrade orchestration
  - **[schema/developer-documentation.md](schema/developer-documentation.md)** - Developer reference for schema manager APIs, routes, and provider-specific setup flows

- **[updates/](updates/)** - Updates and schema versioning subsystem documentation
  - **[updates/srs.md](updates/srs.md)** - Software Requirements Specification for version checks, schema version tracking, and update orchestration
  - **[updates/architecture.md](updates/architecture.md)** - Architecture views, decisions, constraints, and risks for update and upgrade flows
  - **[updates/developer-documentation.md](updates/developer-documentation.md)** - Developer reference for activator/schema APIs, REST routes, and migration maintenance patterns

- **[lifecycle/](lifecycle/)** - Plugin lifecycle subsystem documentation
  - **[lifecycle/srs.md](lifecycle/srs.md)** - Software Requirements Specification for activation, deactivation, upgrade checks, and uninstall cleanup
  - **[lifecycle/architecture.md](lifecycle/architecture.md)** - Architecture views, decisions, constraints, and risks for plugin startup, pause, and removal behavior
  - **[lifecycle/developer-documentation.md](lifecycle/developer-documentation.md)** - Developer reference for hook registration, option defaults, cleanup flows, and lifecycle caveats

- **[sync/](sync/)** - Sync orchestration subsystem documentation
  - **[sync/srs.md](sync/srs.md)** - Software Requirements Specification for real-time and batch sync, content cleaning, and multisite coordination
  - **[sync/architecture.md](sync/architecture.md)** - Architecture views, decisions, constraints, and risks for sync lifecycle orchestration
  - **[sync/developer-documentation.md](sync/developer-documentation.md)** - Developer reference for lifecycle hooks, batch processors, routes, and extension points

- **[security/](security/)** - Security audit and compliance documentation
  - **[security/security-audit-report.md](security/security-audit-report.md)** - Security audit report covering authentication, authorization, data protection, and vulnerability assessment

- **[retry-queue/](retry-queue/)** - Retry queue subsystem documentation
  - **[retry-queue/srs.md](retry-queue/srs.md)** - Software Requirements Specification for error classification, retry scheduling, and dead letter management
  - **[retry-queue/architecture.md](retry-queue/architecture.md)** - Architecture views, decisions, constraints, and risks for sync failure recovery
  - **[retry-queue/developer-documentation.md](retry-queue/developer-documentation.md)** - Developer reference for error handler, queue manager APIs, REST routes, and troubleshooting

- **[hooks/](hooks/)** - Hooks and extension surface documentation
  - **[hooks/srs.md](hooks/srs.md)** - Software Requirements Specification for hook contracts, stability tiers, and governance requirements
  - **[hooks/architecture.md](hooks/architecture.md)** - Architecture views, decisions, constraints, and risks for hook emission and extension boundaries
  - **[hooks/developer-documentation.md](hooks/developer-documentation.md)** - Developer reference for Tier 1/2/3 hook catalogs, signatures, and integration recipes

- **[interactions/](interactions/)** - Interaction tracking subsystem documentation
  - **[interactions/srs.md](interactions/srs.md)** - Software Requirements Specification for interaction capture, contracts, and access controls
  - **[interactions/architecture.md](interactions/architecture.md)** - Architecture views, decisions, constraints, and risks for interaction recording flows
  - **[interactions/developer-documentation.md](interactions/developer-documentation.md)** - Developer reference for CPT contracts, REST endpoints, hooks, and integration recipes

- **[abilities/](abilities/)** - Abilities API integration documentation
  - **[abilities/srs.md](abilities/srs.md)** - Software Requirements Specification for abilities registration, contracts, and permission controls
  - **[abilities/architecture.md](abilities/architecture.md)** - Architecture views, decisions, constraints, and risks for abilities integration
  - **[abilities/developer-documentation.md](abilities/developer-documentation.md)** - Developer reference for ability schemas, callbacks, metadata, and troubleshooting

- **[logs/](logs/)** - Logs subsystem documentation
  - **[logs/srs.md](logs/srs.md)** - Software Requirements Specification for logging contracts, operations, and controls
  - **[logs/architecture.md](logs/architecture.md)** - Architecture views, decisions, constraints, and risks for logging services
  - **[logs/developer-documentation.md](logs/developer-documentation.md)** - Developer reference for logger API, REST/CLI contracts, and dashboard integration

- **[rag/](rag/)** - RAG subsystem documentation
  - **[rag/srs.md](rag/srs.md)** - Software Requirements Specification for the RAG subsystem
  - **[rag/architecture.md](rag/architecture.md)** - Architecture views, decisions, constraints, and risks for the RAG subsystem
  - **[rag/developer-documentation.md](rag/developer-documentation.md)** - Developer reference for RAG service usage, REST contracts, hooks, and custom tools

- **[providers/](providers/)** - Provider-specific implementation documentation
  - **[providers/srs.md](providers/srs.md)** - Software Requirements Specification for the provider architecture
  - **[providers/architecture.md](providers/architecture.md)** - Architecture views, decisions, constraints, and risks
  - **[providers/developer-documentation.md](providers/developer-documentation.md)** - Developer reference for contributors and third-party integrators

- **[prompt/](prompt/)** - Prompt management subsystem documentation
  - **[prompt/srs.md](prompt/srs.md)** - Software Requirements Specification for prompt templates, variable interpolation, and prompt lifecycle
  - **[prompt/architecture.md](prompt/architecture.md)** - Architecture views, decisions, constraints, and risks for prompt construction and delivery
  - **[prompt/developer-documentation.md](prompt/developer-documentation.md)** - Developer reference for prompt builders, template contracts, and extension points

- **[rest-api/](rest-api/)** - REST API subsystem documentation
  - **[rest-api/srs.md](rest-api/srs.md)** - Software Requirements Specification for the REST API subsystem
  - **[rest-api/architecture.md](rest-api/architecture.md)** - Architecture views, decisions, constraints, and risks for `gg-data/v1`
  - **[rest-api/developer-documentation.md](rest-api/developer-documentation.md)** - Endpoint catalog, permissions, integration guidance, and troubleshooting

- **[wpcli/](wpcli/)** - WP-CLI command interface documentation
  - **[wpcli/srs.md](wpcli/srs.md)** - Software Requirements Specification for CLI command contracts, validation, and operations
  - **[wpcli/architecture.md](wpcli/architecture.md)** - Architecture views, decisions, constraints, and command orchestration flow
  - **[wpcli/developer-documentation.md](wpcli/developer-documentation.md)** - Developer reference for command catalog, options, output formats, and extension points

- **[testing/](testing/)** - Testing subsystem documentation
  - **[testing/srs.md](testing/srs.md)** - Software Requirements Specification for test coverage, framework contracts, and quality gates
  - **[testing/architecture.md](testing/architecture.md)** - Architecture views, decisions, constraints, and risks for the test harness and isolation strategy
  - **[testing/developer-documentation.md](testing/developer-documentation.md)** - Developer reference for test types, bootstrap, WP test framework setup, and troubleshooting

- **[tools/](tools/)** - Tools and utilities subsystem documentation
  - **[tools/srs.md](tools/srs.md)** - Software Requirements Specification for developer tooling, utilities, and helper contracts
  - **[tools/architecture.md](tools/architecture.md)** - Architecture views, decisions, constraints, and risks for utility services and tooling infrastructure
  - **[tools/developer-documentation.md](tools/developer-documentation.md)** - Developer reference for utility APIs, helper classes, and tooling integration patterns

---

## 🎯 Quick Navigation

**I want to...**

### Build a New Feature
1. Read [interface/srs.md](interface/srs.md) - Learn interface contract obligations and implemented capability boundaries
2. Read [interface/architecture.md](interface/architecture.md) - Learn dashboard composition and integration boundaries
3. Read [interface/developer-documentation.md](interface/developer-documentation.md) - Follow feature integration workflow and store/API patterns
4. Reference [rest-api/developer-documentation.md](rest-api/developer-documentation.md) for endpoint patterns

### Understand REST API Contracts
1. Read [rest-api/srs.md](rest-api/srs.md) — requirements and contract obligations
2. Read [rest-api/architecture.md](rest-api/architecture.md) — architecture views, access models, and ADRs
3. Read [rest-api/developer-documentation.md](rest-api/developer-documentation.md) — route catalog, permissions, and integration patterns
4. Use controller source links in [rest-api/developer-documentation.md](rest-api/developer-documentation.md) for endpoint examples and payload details

### Understand Search Contracts
1. Read [search/srs.md](search/srs.md) — requirements and contract obligations for search behavior
2. Read [search/architecture.md](search/architecture.md) — architecture views, provider paths, and ADRs
3. Read [search/developer-documentation.md](search/developer-documentation.md) — SQL function contracts, endpoint catalog, and extension points

### Understand Vectors and Embeddings Contracts
1. Read [vectors/srs.md](vectors/srs.md) — requirements and contract obligations for vector and vocabulary behavior
2. Read [vectors/architecture.md](vectors/architecture.md) — architecture views, strategy boundaries, and ADRs
3. Read [vectors/developer-documentation.md](vectors/developer-documentation.md) — endpoint catalog, strategy extension guidance, and storage contracts

### Understand Schema Contracts
1. Read [schema/srs.md](schema/srs.md) — requirements and contract obligations for schema lifecycle behavior
2. Read [schema/architecture.md](schema/architecture.md) — architecture views, provider-aware flows, and ADRs
3. Read [schema/developer-documentation.md](schema/developer-documentation.md) — route catalog, versioning contracts, and troubleshooting

### Understand Updates and Schema Versioning
1. Read [updates/srs.md](updates/srs.md) — requirements for plugin version checks, connection-scoped schema versions, and update contracts
2. Read [updates/architecture.md](updates/architecture.md) — architecture views for update orchestration, provider-aware paths, and rollback boundaries
3. Read [updates/developer-documentation.md](updates/developer-documentation.md) — API/route reference, migration patterns, and troubleshooting

### Understand Lifecycle Contracts
1. Read [lifecycle/srs.md](lifecycle/srs.md) — requirements for activation, deactivation, version checks, and uninstall cleanup
2. Read [lifecycle/architecture.md](lifecycle/architecture.md) — architecture views, lifecycle ADRs, and cleanup boundaries
3. Read [lifecycle/developer-documentation.md](lifecycle/developer-documentation.md) — hook registration, option defaults, cleanup sequence, and troubleshooting

### Understand Sync Contracts
1. Read [sync/srs.md](sync/srs.md) — requirements and contract obligations for real-time and batch sync behavior
2. Read [sync/architecture.md](sync/architecture.md) — architecture views, provider-aware routing, and ADRs
3. Read [sync/developer-documentation.md](sync/developer-documentation.md) — component overview, route catalog, and extension points

### Understand Retry Queue Contracts
1. Read [retry-queue/srs.md](retry-queue/srs.md) — requirements for error classification, retry scheduling, and dead letter behavior
2. Read [retry-queue/architecture.md](retry-queue/architecture.md) — architecture views, backoff ADRs, and WP-Cron design decisions
3. Read [retry-queue/developer-documentation.md](retry-queue/developer-documentation.md) — API reference, error tables, DB integration points, and troubleshooting

### Understand Hooks and Extension Contracts
1. Read [hooks/srs.md](hooks/srs.md) — requirements and governance obligations for hook contracts and stability tiers
2. Read [hooks/architecture.md](hooks/architecture.md) — architecture views, runtime hook flow, and hook-surface ADRs
3. Read [hooks/developer-documentation.md](hooks/developer-documentation.md) — Tier 1/2/3 hook catalog, signatures, dynamic hook patterns, and recipes

### Understand Interaction Contracts
1. Read [interactions/srs.md](interactions/srs.md) — requirements and contract obligations for interaction capture and access control
2. Read [interactions/architecture.md](interactions/architecture.md) — architecture views, runtime recording flow, and ADRs
3. Read [interactions/developer-documentation.md](interactions/developer-documentation.md) — CPT/REST contracts, hooks, payload examples, and troubleshooting

### Understand Abilities Contracts
1. Read [abilities/srs.md](abilities/srs.md) — requirements and contract obligations for abilities registration and execution
2. Read [abilities/architecture.md](abilities/architecture.md) — architecture views, runtime registration/execution flow, and ADRs
3. Read [abilities/developer-documentation.md](abilities/developer-documentation.md) — ability schemas, permissions, metadata contracts, and troubleshooting

### Understand Logs Contracts
1. Read [logs/srs.md](logs/srs.md) — requirements and contract obligations for logging storage, access, and controls
2. Read [logs/architecture.md](logs/architecture.md) — architecture views, runtime logging flows, and ADRs
3. Read [logs/developer-documentation.md](logs/developer-documentation.md) — logger APIs, REST/CLI contracts, masking behavior, and troubleshooting

### Understand WP-CLI Interface and Commands
1. Read [wpcli/srs.md](wpcli/srs.md) — requirements and contract obligations for CLI command surface and runtime behavior
2. Read [wpcli/architecture.md](wpcli/architecture.md) — architecture views, command orchestration, and delegation boundaries
3. Read [wpcli/developer-documentation.md](wpcli/developer-documentation.md) — command catalog, options/defaults, output formats, and troubleshooting

### Understand RAG Requirements
1. Read [rag/srs.md](rag/srs.md) — software requirements and current subsystem scope
2. Read [rag/architecture.md](rag/architecture.md) — architecture views, runtime model, and ADRs
3. Read [rag/developer-documentation.md](rag/developer-documentation.md) — service usage, REST contracts, hooks, and troubleshooting
4. Read [rag/manifest-contract.md](rag/manifest-contract.md) — canonical manifest schema, deterministic tool override, and observability contract

### Understand Database Abstraction
1. Read [providers/srs.md](providers/srs.md) — software requirements for the provider architecture
2. Read [providers/architecture.md](providers/architecture.md) — architecture views, decisions, constraints, and design rationale
3. Read [providers/developer-documentation.md](providers/developer-documentation.md) — API contracts, extension points, and integration recipes
4. Review database provider interface contract and concrete implementations
5. See PostgreSQL (direct PDO) and Supabase (PostgREST) implementation examples

### Extend or Integrate Providers
1. Start with [providers/developer-documentation.md](providers/developer-documentation.md)
2. Use extension points documented for DB factory registration and AI provider filters
3. Validate requirements and constraints with [providers/srs.md](providers/srs.md)
4. Confirm architecture alignment in [providers/architecture.md](providers/architecture.md)

---

## 📊 Documentation Status

**Current (Actively Maintained):**
- ✅ interface/srs.md
- ✅ interface/architecture.md
- ✅ interface/developer-documentation.md
- ✅ rag/srs.md
- ✅ rag/architecture.md
- ✅ rag/developer-documentation.md
- ✅ rag-evaluation/srs.md
- ✅ rag-evaluation/architecture.md
- ✅ rag-evaluation/developer-documentation.md
- ✅ search/srs.md
- ✅ search/architecture.md
- ✅ search/developer-documentation.md
- ✅ vectors/srs.md
- ✅ vectors/architecture.md
- ✅ vectors/developer-documentation.md
- ✅ schema/srs.md
- ✅ schema/architecture.md
- ✅ schema/developer-documentation.md
- ✅ security/security-audit-report.md
- ✅ updates/srs.md
- ✅ updates/architecture.md
- ✅ updates/developer-documentation.md
- ✅ providers/srs.md
- ✅ providers/architecture.md
- ✅ providers/developer-documentation.md
- ✅ prompt/srs.md
- ✅ prompt/architecture.md
- ✅ prompt/developer-documentation.md
- ✅ rest-api/srs.md
- ✅ rest-api/architecture.md
- ✅ rest-api/developer-documentation.md
- ✅ interactions/srs.md
- ✅ interactions/architecture.md
- ✅ interactions/developer-documentation.md
- ✅ abilities/srs.md
- ✅ abilities/architecture.md
- ✅ abilities/developer-documentation.md
- ✅ logs/srs.md
- ✅ logs/architecture.md
- ✅ logs/developer-documentation.md
- ✅ wpcli/srs.md
- ✅ wpcli/architecture.md
- ✅ wpcli/developer-documentation.md
- ✅ testing/srs.md
- ✅ testing/architecture.md
- ✅ testing/developer-documentation.md
- ✅ tools/srs.md
- ✅ tools/architecture.md
- ✅ tools/developer-documentation.md

**Deferred (Write Before Release):**
- ⏳ User installation guide
- ⏳ Dashboard user guide
- ⏳ FAQ
- ⏳ Developer contribution guide
- ⏳ Documentation index/navigation

---

## 🔄 Documentation Philosophy

**During Development:**
- Focus on architectural guidance and implementation planning
- Document patterns and conventions as they're established
- Keep technical reference current with codebase
- Defer user-facing docs until features are stable

**Before Public Release:**
- Write comprehensive installation guide
- Create user-facing dashboard documentation
- Develop FAQ based on actual development questions
- Update developer guide with contribution workflow
- Add screenshots and real examples

**After Release:**
- Maintain user documentation with each version
- Expand FAQ based on actual user questions
- Keep API reference synchronized with endpoints
- Document new features as they're added

---

## 📝 Contributing to Documentation

**Updating Existing Docs:**
1. Verify all code examples work
2. Check all internal links

**Adding New Features:**
1. Document REST endpoints in `rest-api/developer-documentation.md`
2. Add architectural patterns to relevant architecture doc

---


