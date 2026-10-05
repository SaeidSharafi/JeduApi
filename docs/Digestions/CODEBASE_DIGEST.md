# Codebase Digest: Overview & Reference Guide

## Architecture

Headless Laravel API for educational commerce. Admin and shop interfaces share Actions/Services. Controllers delegate business logic; `app/Data/` uses `spatie/laravel-data` DTOs for request/response contracts. API responses use `apiResponse()`. Admin authenticates staff; shop authenticates customers through separate guards.

Admin spreadsheet imports and private User exports enter through `app/Actions/Admin/ImportExport/`: preview, explicit atomic local approval, post-commit standalone provider-account jobs, run polling, and synchronous filtered export generation/authenticated download. `UserQueryDefinition` shares list/export filters and deterministic ordering. Account capabilities are separate from Enrollment provisioning. The [core logic digest](DIGEST_CORE_LOGIC.md#spreadsheet-importexport-engine-appservicesimportexport) owns this workflow; [API interfaces](DIGEST_API_INTERFACES.md#importexport-controllers-apphttpcontrollersapiadminimportexport) owns request/result contracts.

Catalog separates content (`Course`, `Seminar`, `DigitalAsset`, `Bundle`), commercial `Product`, and buyable `ProductDeliveryOption`. Checkout creates orders with purchase snapshots; payment and enrollment provisioning have separate lifecycles.

## Using uploaded digests in chat

These files support questions about codebase without repository access. Use uploaded content as reference; code paths and class names identify implementation locations, not files chatbot can open. No `AGENTS.md`, README, or other repository documents required to follow this guide.

Select relevant sections by question. Use cross-references only when target file also uploaded. Missing field definitions, validation rules, response shapes, or external decision context → state what supplied docs establish and flag missing detail. DTO/class names alone do not establish contracts. Code and generated API docs can verify details only when actually available.

| Question | Reference file | Contents |
| --- | --- | --- |
| What is entity for? How does it relate to others? | [Data models](DIGEST_DATA_MODELS.md) | Model purpose, relationships, casts, helpers |
| How does operation work? What else does it affect? | [Core logic](DIGEST_CORE_LOGIC.md) | Actions/Services, events/jobs, workflows, side effects, failures |
| Which endpoint? Which auth/input/output? | [API interfaces](DIGEST_API_INTERFACES.md) | Routes, guards, documented request/response contracts, controller/action pointers |
| Which columns, keys, constraints? | [Schema](DIGEST_SCHEMA.md) | Tables, columns, indexes, foreign keys |
| How do major domain concepts fit together? | [Architectural blueprint](Jedu_E-Commerce_Architectural_Blueprint.md) | Cross-domain narrative and examples |

Upload relevant files for narrow questions; upload all digests for broad or cross-domain questions. Overview optional when detailed reference already matches question. Some entries name DTOs without full field definitions; those entries are navigation references, not complete payload specifications.

## Reading with repository access

Locate affected code through Graphify/search; read scoped code/tests first. This overview is optional orientation for unfamiliar domains. Consult matching digest sections only for unresolved domain meaning, workflow boundaries, cross-module effects, or explicit path-rule requirements. Reuse facts already read; no mandatory digest preload. For exact details, use models, DTOs, routes, migrations, or Boost schema tools directly. Current implementation decides conflicts; flag mismatches.

Understanding and maintenance are separate: before finishing code changes, inspect affected digest sections and update changed reference facts, including new coverage. Reuse sections already read; leave unrelated digests unloaded. Preserve detailed references for chatbot uploads.

Use Graphify for code navigation when graph available. For digest navigation:

```bash
rg -n '^#{1,4} ' docs/Digestions/DIGEST_CORE_LOGIC.md
rg -n 'Order|Payment|Checkout' docs/Digestions/DIGEST_CORE_LOGIC.md
sed -n 'START,ENDp' docs/Digestions/DIGEST_CORE_LOGIC.md
```

Replace `START,END` with section bounds. Stop expanding once affected entry points, contracts, invariants, and dependencies understood.

## Reference organization

Preserve detail needed for chatbot questions without code access. Each fact has primary home: relationships/casts in models, columns/indexes/constraints in schema, workflows in core logic, endpoint auth and request/response contracts in API interfaces. Cross-link repeated details; retain brief local context. Useful technical detail remains valuable even when easy to find in code.
