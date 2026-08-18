# JeduShop documentation

This directory deliberately contains a small set of documents. Documentation that repeats routes, DTO fields, schemas, file maps, or implementation walkthroughs becomes stale quickly and must not be treated as a second codebase.

## Authority order

1. `docs/Digestions/` is the repository's maintained technical source of truth.
2. Current code, migrations, configuration, and tests decide implementation details.
3. `docs/Architecture/` records only non-obvious boundaries, invariants, and failure behavior. It is supporting context, not an API or schema contract.
4. `IP_OWNERSHIP_DOCUMENT.md` and `sales-document-fa.md` are legal/product material, not engineering specifications.

Generated API documentation is the authority for client integration. Do not recreate endpoint maps or request/response examples here.

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

Add a document only when it captures a decision that cannot be recovered cheaply from code. Prefer a short invariant plus its reason and failure mode. Do not add temporary handoffs, audit snapshots, proposed implementations, progress percentages, exhaustive inventories, or copies of third-party documentation.

When behavior changes, update the relevant Digestion first. Update an Architecture note only when the underlying boundary or invariant changes.
