# JeduShop documentation

Digests serve both repo-connected agents and agents with docs only. Preserve standalone reference detail; reduce repeated facts between documents. Repo-connected agents read scoped code/tests first; consult digests for unresolved domain context or required documentation maintenance. Chatbots use uploaded digests as reference.

## Authority order

1. Current code, migrations, configuration, and tests decide implementation details for repo work.
2. `docs/Digestions/` provides standalone reference for chatbot uploads and conditional domain context for repo agents. `CODEBASE_DIGEST.md` is optional orientation; no upfront digest reading required.
3. `docs/Architecture/` records only non-obvious boundaries, invariants, and failure behavior. It is supporting context, not an API or schema contract.
4. `IP_OWNERSHIP_DOCUMENT.md` and `sales-document-fa.md` are legal/product material, not engineering specifications.

Generated API documentation is authority for client integration when available. Digestion API references retain endpoint auth and request/response contracts needed by docs-only agents. Missing details must be flagged; DTO names alone are insufficient.

## Retained architecture notes

- [Admin order management](Architecture/Admin-Order-Management.md)
- [Authentication and OTP](Architecture/Auth-OTP-Subsystem.md)
- [Checkout, orders, and payments](Architecture/Checkout-Order-Payment-System.md)
- [Discount promotions](Architecture/Discount-Promotion-Engine.md)
- [Enrollment provisioning](Architecture/Enrollment-Provisioning-System.md)
- [Product catalog](Architecture/Product-Catalog.md)
- [Wallet](Architecture/Wallet.md)
- [Wallet campaigns](Architecture/Wallet-Campaign.md)

## Maintenance rule

Prefer existing digest sections for model, schema, workflow, and API coverage. Add architecture notes for non-obvious decisions, reasons, and failure modes. Avoid temporary handoffs, progress reports, and copies of third-party documentation. Keep each fact in its owning reference; cross-link elsewhere with enough context for understanding.

Follow **Digest Maintenance** in `AGENTS.md` before finalizing code tasks. Update affected sections only; preserve reference detail needed without repo access. Update an Architecture note only when underlying boundary or invariant changes.
