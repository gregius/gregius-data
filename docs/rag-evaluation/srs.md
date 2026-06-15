# Software Requirements Specification (SRS)

Standard: ISO/IEC/IEEE 29148:2018

## Document Information

| Field | Value |
|---|---|
| Project | Gregius Data |
| Software Component | RAG Evaluation |
| Version | 1.0 |
| Date | 2026-04-18 |
| Author | Gregius |
| StRS Reference | N/A - direct architecture and roadmap derivation |
| SyRS Reference | N/A - SRS produced directly from planning and architecture artifacts |
| Status | Draft |

## 1. Introduction

### 1.1 System Purpose

This SRS defines what the RAG Evaluation software component must do to produce framework-ready evaluation datasets from pre-collected RAG interaction prompts, enabling systematic pipeline and LLM response quality measurement.

### 1.2 System Scope

The software component includes evaluation configuration loading, prompt corpus validation, canonical artifact generation, framework-specific adapter output, and evaluation run manifest writing.

Software identifier: gregius-data-rag-evaluation

Repository path: public/wp-content/plugins/gregius-data

### 1.3 System Overview

#### 1.3.1 System Context

The evaluation runner is a plugin-adjacent data preparation component that ingests pre-collected RAG interaction prompts from a standalone configuration file and produces framework-ready evaluation artifacts. It operates fully independently of the RAG benchmark component.

#### 1.3.2 System Functions Summary

- Load and validate evaluation configuration and prompt corpus.
- Write `prompts.jsonl` dataset from validated prompts.
- Dispatch framework-specific adapter output for configured evaluation framework.
- Write a run manifest recording provenance and artifact locations.
- Support full and targeted prompt execution patterns.

#### 1.3.3 User Characteristics

| User Class | Technical Level | Primary Interaction Point |
|---|---|---|
| Plugin Developer | Technical | CLI evaluation runner |
| QA Engineer | Technical | Canonical and framework artifact review |
| ML Engineer | Technical | Framework adapter input files |
| Product Owner | Technical/Operational | Evaluation run evidence and manifest |

### 1.4 Definitions

| Term | Definition |
|---|---|
| Evaluation run | One execution of the evaluation runner producing timestamped artifacts |
| Prompt corpus | Set of pre-collected RAG interaction records defined in configuration |
| Canonical artifact | Framework-agnostic JSONL representation of the prompt corpus |
| Framework adapter | Thin output layer that transforms canonical data into a framework-specific format |
| Run manifest | JSON file recording run metadata, schema version, prompt counts, artifact paths, and hashes |
| Prompt | One interaction record containing user input, response, retrieved contexts, and optional reference fields |
| Single-turn prompt | A prompt representing one user query and one response without multi-turn history |

### 1.5 Abbreviations and Acronyms

| Abbreviation | Expansion |
|---|---|
| SRS | Software Requirements Specification |
| CLI | Command-Line Interface |
| RAG | Retrieval-Augmented Generation |
| JSONL | JSON Lines (newline-delimited JSON) |
| LLM | Large Language Model |

## 2. References

- docs/rag-evaluation/architecture.md
- wp gg-data evaluation
- includes/cli/resources/evaluation/rag-evaluation-config.example.json

## 3. Software Requirements

Requirement notation:
- MUST: mandatory
- SHOULD: strongly recommended
- MAY: optional

### 3.1 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| RE-FR-01 | The software MUST load evaluation runtime configuration from a JSON configuration file before prompt processing starts. | Must |
| RE-FR-02 | The software MUST validate required runtime fields and terminate with a non-zero exit result when required fields are missing. | Must |
| RE-FR-03 | The software MUST validate each prompt in the corpus and terminate with a non-zero exit result when required prompt fields are missing or duplicated. | Must |
| RE-FR-04 | The software MUST process the full prompt corpus by default when no prompt filter is provided. | Must |
| RE-FR-05 | The software MUST support processing of a single prompt by prompt ID when an explicit prompt filter is provided. | Must |
| RE-FR-06 | The software MUST write a `prompts.jsonl` artifact containing one JSON object per processed prompt at the run root output directory. | Must |
| RE-FR-07 | The software MUST dispatch framework-specific adapter output when a supported evaluation framework is specified. | Must |
| RE-FR-08 | The software MUST reject unsupported framework values at argument parse time and report the list of supported frameworks. | Must |
| RE-FR-09 | The software MUST write a run manifest recording run identity, schema version, prompt counts, framework configuration, prompts dataset path, and adapter artifact paths. | Must |
| RE-FR-10 | The software MUST write artifacts to a timestamped output directory and MUST support explicit output directory override by user option. | Must |
| RE-FR-11 | The software MUST emit progress information and artifact locations to support operator verification and troubleshooting. | Must |

### 3.2 Data and Contract Requirements

| ID | Requirement | Priority |
|---|---|---|
| RE-DR-01 | Each prompt MUST contain a stable unique ID and a `user_input` field. | Must |
| RE-DR-02 | Each processed canonical sample MUST contain a `response` field representing the live RAG pipeline answer. | Must |
| RE-DR-03 | Each processed canonical sample MUST contain a `retrieved_contexts` field as an array of context strings captured from live execution. | Must |
| RE-DR-04 | Each prompt SHOULD contain a `reference` field providing the ground-truth answer against which response quality is measured. | Should |
| RE-DR-05 | Each prompt MAY contain a `reference_contexts` field as an array of ideal context strings. | May |
| RE-DR-06 | Each prompt MAY contain a `rubric` field providing evaluation-specific scoring guidance. | May |
| RE-DR-07 | The `prompts.jsonl` artifact MUST represent each prompt as one JSON object per line with field names matching the evaluation framework schema for the configured sample type. | Must |
| RE-DR-08 | The Ragas adapter output MUST produce a JSONL file compatible with Ragas `SingleTurnSample` field expectations. | Must |
| RE-DR-09 | The run manifest MUST include `run_id`, `created_at`, `schema_version`, `framework`, `sample_type`, `sample_count`, `scope`, `failed_count`, `executed_count`, `prompts` dataset path (with SHA-256 hash), `config` path and URL, `errors` artifact path (with SHA-256 hash, empty when no failures), `scope_detail` (with failure breakdown), and framework adapter artifact paths (with SHA-256 hashes). | Must |
| RE-DR-10 | Artifact and manifest links MUST be portable across supported execution transports. | Must |
| RE-DR-11 | Prompt IDs MUST remain stable across evaluation runs for cross-run comparability. | Must |

### 3.3 Software Operations

| ID | Requirement | Priority |
|---|---|---|
| RE-OR-01 | The software MUST use PHP as the evaluation orchestration runtime. | Must |
| RE-OR-02 | The software MUST remain transport-agnostic and MUST NOT require a specific execution transport such as Docker, VM, host shell, or CI runner. | Must |
| RE-OR-03 | The software MUST default runtime artifacts to a location outside documentation directories. | Must |
| RE-OR-04 | The software MUST support framework-scoped output directories within each timestamped run directory. | Must |
| RE-OR-05 | The software MUST fail fast on unknown command options and unknown framework values and report usage guidance. (WP-CLI handles unknown options automatically; unknown framework values are validated and reported via RE-FR-08.) | Must |
| RE-OR-06 | The software SHOULD allow environment-specific binary and working-directory configuration without modifying source code. | Should |
| RE-OR-07 | The software MUST separate transient run artifacts from durable planning documentation snapshots. | Must |
| RE-OR-08 | The software MUST support future evaluation framework additions through framework enum expansion without architectural changes. | Must |

### 3.4 Quality and Non-Functional Requirements

| ID | Requirement | Verification Method |
|---|---|---|
| RE-QR-01 | The canonical JSONL artifact MUST be valid JSONL and MUST be readable by a standard JSON Lines parser. | File parse check |
| RE-QR-02 | The Ragas adapter output MUST match the expected field schema for `SingleTurnSample`. | Schema inspection |
| RE-QR-03 | The run manifest MUST be valid JSON and MUST pass schema validation. | JSON parse + field check |
| RE-QR-04 | Sample validation MUST detect and reject missing required fields and duplicate IDs before any artifact is written. | Validation unit check |
| RE-QR-05 | The runner MUST produce identical canonical output for the same config across repeated runs. | Rerun consistency check |
| RE-QR-06 | Framework adapter additions MUST not require changes to canonical artifact generation logic. | Code review |
| RE-QR-07 | The runner SHOULD preserve artifact portability across supported execution transports. | Cross-environment check |

## 4. Verification and Acceptance

| Requirement Group | Verification Method | Tool or Evidence |
|---|---|---|
| Functional requirements | Execution and artifact inspection | Runner output + artifact files |
| Data and contract requirements | Schema and content inspection | Canonical JSONL + manifest review |
| Operations requirements | Configuration and portability checks | Multi-environment run inspection |
| Quality requirements | Artifact parse and schema validation | JSON parse tools + schema inspection |
