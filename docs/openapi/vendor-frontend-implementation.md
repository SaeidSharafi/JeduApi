# Vendor API — Frontend Clarifications

This document covers only ambiguities in the proposed vendor API that affect client integration. Existing shop product, pricing, pagination, authentication, error, and admin conventions are already implemented and are not repeated here.

The authoritative endpoint and field contract is [`Vendor-API.yaml.txt`](Vendor-API.yaml.txt).

## Vendor identity

The public endpoints use `/vendors/{slug}`. The slug is the public identifier for the landing page and must be used as provided by the API.

## Landing-page availability

`GET /vendors/{slug}` returns 404 when the vendor cannot be shown publicly, including an unknown vendor, a non-public vendor, or a disabled landing page. These cases intentionally have the same client-facing result.

## Course listing

`GET /vendors/{slug}/courses` returns the existing shop product-card representation, limited to course products for the vendor in the URL. The client does not need to send a vendor filter or create a separate product mapper.

`recent_courses` is the initial product collection embedded in the landing-page response. The `/courses` endpoint is the paginated endpoint for loading additional products.

## Product cards and purchase actions

Vendor course cards use the existing product-card and price structures. Delivery-option UUIDs remain the identifiers inside the existing price records; the vendor API does not introduce a new product or delivery-option identifier.

If a product has multiple delivery options, the API does not define which option a card should select automatically. The frontend should use an explicitly selected option or continue through the existing product-detail flow.
