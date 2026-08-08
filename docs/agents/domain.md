# Domain Docs

How engineering skills consume this repo's domain documentation.

## Before exploring, read these

- `CONTEXT.md` at the repository root.
- Relevant ADRs under `docs/adr/`.

If these files do not exist, proceed silently. The domain-modeling skill creates them lazily when terminology or architectural decisions are resolved.

## File structure

This repository uses a single-context layout:

```text
/
├── CONTEXT.md
└── docs/
    └── adr/
        ├── 0001-example-decision.md
        └── 0002-another-decision.md
```

## Use the glossary's vocabulary

When output names a domain concept—including issue titles, refactoring proposals, hypotheses, and test names—use the term defined in `CONTEXT.md`.

If a needed concept is missing, reconsider whether the term belongs to the project or note the gap for the domain-modeling skill.

## Flag ADR conflicts

Explicitly identify output that contradicts an existing ADR instead of silently overriding it.
