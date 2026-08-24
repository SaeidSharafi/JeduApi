# Digital asset downloads use flat list, uuid URLs, and S3 redirects

We need the student endpoint `GET /api/v1/shop/student/digital-assets` to list bought Digital Asset products with name, file type, file size, thumbnail, and a stable download link. The data model allows N `main` files per asset and course-attached assets, but in practice admin uploads one main file and `DigitalAssetDownloadController` only serves `getMedia('main')->first()`. For shop-facing URLs every other resource (Enrollment, ProductDeliveryOption, Payment) is addressed by uuid/slug, not integer id.

We decided: the list returns flat rows per asset (one row per file, `enrollment_uuid` + asset `uuid`), `DigitalAsset` gets a `uuid` (v7, backfilled in the original create migration — dev phase, `migrate:fresh`), and `DigitalAssetDownloadController` keeps ownership/active/asset-membership checks but responds with a `302` redirect to an S3 `temporaryUrl` valid for 7 days.

Flat rows make the "my downloads" UI trivial and handle course-attached assets without nesting. Uuid avoids enumerable ids and matches house style. S3 redirect offloads bandwidth from the app; 7 days is the AWS Signature V4 maximum and allows resume over slow 3G for large files where a 5-minute expiry would break both the initial transfer and any Range-based resume days later.

Considered: nested `files[]` per enrollment, streaming via `Storage::download` through the app, and short-lived (5-minute) URLs. Streaming still serves local-disk files via the app anyway; nested shape added no value for this endpoint.

Consequences: the original `create_digital_assets` migration is edited in place (dev only); the download check still loads the first `main` media before redirecting — multiple `main` files per asset remain stored but unaddressable until a per-file route is added.
