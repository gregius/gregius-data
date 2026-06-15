# RAG Benchmark Developer Documentation

Standard: ISO/IEC/IEEE 26514:2022

## 1. Overview

This guide documents developer-facing usage and extension details for the RAG benchmark subsystem.

Primary references:
- SRS: [SRS reference](srs.md)
- Architecture: [Architecture reference](architecture.md)
- Command family: `wp gg-data benchmark`

Scope:
1. Runner execution contract
2. Configuration contract
3. Artifact and scorecard outputs
4. Extension and troubleshooting guidance

## 2. Quick Start

1. Copy config template:
- `cp wp-content/plugins/gregius-data/includes/cli/resources/benchmark/rag-benchmark-quality-config.example.json wp-content/uploads/gregius-data/config/rag-benchmark-quality-config.json`

2. Populate required config values:
- `connection`
- `models.embedding`
- `models.agentic`
- `models.rerank`
- `models.answer`

3. Run benchmark:
- `wp gg-data benchmark run`

4. Run single prompt:
- `wp gg-data benchmark run --only=P1`

5. Run single prompt with selected scorecard scope:
- `wp gg-data benchmark run --only=P1 --scorecard-scope=selected`

6. Override output directory (a timestamp subdirectory is always appended):
- `wp gg-data benchmark run --output-dir=/tmp/rag-benchmark` -> `/tmp/rag-benchmark/YYYYMMDD-HHMMSS/`

7. Validate config before running:
- `wp gg-data benchmark validate-config`

8. List configured prompts:
- `wp gg-data benchmark list-prompts`

9. Generate or regenerate scorecard from existing artifacts (no re-execution):
- `wp gg-data benchmark scorecard`

10. List prompts in JSON format for programmatic consumption:
- `wp gg-data benchmark list-prompts --format=json`

11. Validate config without live connection check (CI/offline):
- `wp gg-data benchmark validate-config --skip-connection-check`

[SRS: RB-FR-01, RB-FR-03, RB-FR-04, RB-FR-08, RB-FR-09, RB-FR-11, RB-DR-10, RB-DR-11]

## 3. Configuration Contract

Required fields:
- `connection`
- `models.embedding`
- `models.agentic`
- `models.rerank`
- `models.answer`
- `prompts[]` entries with `id`, `slug`, `prompt`, `expected`

Optional fields:
- `url` — Site URL for provenance and multisite traceability.
- `artifact_root`
- `prompt_id` — Optional integer field per prompt entry referencing a custom system prompt post ID for that prompt.

Behavioral notes:
1. Missing required values cause non-zero exit and diagnostic output.
2. Prompt IDs and semantics should remain stable for comparability.
3. The shipped template lives inside the plugin, but the writable local config should live under `wp-content/uploads/gregius-data/config/` so it survives plugin updates.
4. The `--config=<path>` option validates the supplied path via `realpath()` and rejects paths outside the permitted config directory for security.

[SRS: RB-FR-02, RB-DR-01, RB-DR-02]

## 4. Runner Lifecycle

1. Operator invokes `wp gg-data benchmark run`.
2. Command loads and validates config from the writable uploads-based config path.
3. Command resolves output directory.
4. Command resolves execution prompt set (all prompts or `--only` prompt).
5. Command resolves scorecard row scope (`all` default or explicit `selected`).
6. Command builds scorecard.
7. Service executes each selected prompt through the live RAG pipeline.
8. Service writes raw audit artifact and normalized JSON artifact.
9. Command prints artifact summary.

Scorecard-only flow (`wp gg-data benchmark scorecard`):
1. Command loads config from the default or user-supplied path.
2. Without `--from-dir`: generates a blank scorecard template in a new timestamped directory with links to not-yet-existing artifacts (useful as a pre-run checklist).
3. With `--from-dir=<path>`: reads all `<slug>.json` artifacts from the specified previous run directory, populates the Actual Behavior column from each artifact's `answer` field, and writes the regenerated scorecard in-place.

[SRS: RB-FR-05, RB-FR-06, RB-FR-07, RB-FR-08, RB-FR-10, RB-FR-11]

## 5. Artifact Contract

Per-run artifacts:
1. `<slug>.raw.txt`
2. `<slug>.json`
3. `scorecard.md`

Default artifact root:
1. `wp-content/uploads/gregius-data/artifacts/benchmark/<timestamp>/`

Scorecard scope contract:
1. Default scope (`all`) includes one scorecard row per configured prompt.
2. Selected scope (`selected`) includes one scorecard row per executed prompt set.
3. Scorecard header records scope, prompt counts, and site URL for reviewer clarity.

Expected scorecard columns:
1. ID
2. Prompt
3. Expected Outcome
4. Raw Output
5. JSON Artifact
6. Actual Behavior
7. Verdict
8. Notes

Link policy:
1. Generated links should be portable across supported transports.

JSON artifact schema:
1. The JSON artifact format (`format_json_output`) is governed by the shared `GG_Data_Format_Json_Output` trait, used by both the benchmark and evaluation subsystems.
2. Schema shape: `{query, answer, retrieved_contexts, sources, metadata}`.

[SRS: RB-DR-03, RB-DR-07, RB-FR-12]

## 6. Review Guidance

1. The scorecard Verdict and Notes columns are blank. Reviewers fill them manually (PASS/PARTIAL/FAIL).
2. No automated scoring is implemented. All quality assessment is human review.
3. JSON artifacts contain RAG metadata (policy decision, evidence sources) that reviewers can use for evidence inspection.

[SRS: RB-DR-05, RB-DR-08, RB-QR-03, RB-QR-04, RB-QR-09]

## 7. Extension Points

Supported extension surfaces:
1. Prompt matrix updates via config files.
2. Additional output paths via `--output-dir`.
3. Additional review automation built on raw/json/scorecard contracts.
4. Additional CLI subcommands under `wp gg-data benchmark` when schema stability is preserved.

Guardrails:
1. Preserve canonical prompt IDs or provide explicit migration notes.
2. Do not break scorecard schema stability without versioned change policy.

[SRS: RB-DR-01, RB-DR-02, RB-QR-07, RB-QR-10]

## 8. Troubleshooting

### Symptom: runner exits before prompts execute
Likely cause:
- Missing required config fields.

Resolution:
1. Validate config keys and values.
2. Re-run with corrected config.

### Symptom: JSON artifact empty
Likely cause:
- Output did not contain parseable JSON payload.

Resolution:
1. Inspect corresponding raw artifact.
2. Verify command output format and preamble.

### Symptom: links in scorecard do not open
Likely cause:
- Legacy absolute link format in older scorecards.

Resolution:
1. Regenerate with current runner.
2. Ensure relative link generation remains unchanged.

### Symptom: single-prompt run still shows many scorecard rows
Likely cause:
- Default scorecard scope is `all` for baseline compatibility.

Resolution:
1. Re-run with `--scorecard-scope selected` for targeted remediation review.
2. Keep default `all` scope for canonical baseline and release evidence runs.


## 9. Known Limitations

1. Full machine-checkable PASS/PARTIAL/FAIL rubric is not finalized.
2. Release threshold policy for PARTIAL outcomes is still governance-driven.
3. Retention policy for long-term artifact storage is not yet fully codified.
4. Artifact cleanup should be managed outside the plugin directory to avoid update-time data loss.

## 10. Handoff Notes

When extending benchmark functionality:
1. Keep transport-agnostic architecture intact.
2. Keep RAG metadata fields present in JSON artifacts for reviewer interpretation; do not embed scoring policy in the artifact schema.
3. Preserve evidence portability and scorecard schema stability.
