## Product Catalog Subsystem — Architecture Reference

### 1) Subsystem Flow Diagram 

#### High Level View
```mermaid
flowchart TB
    A[Admin/Shop API Request] --> B[Controller]
    B --> C[Action Layer]
    C --> D[(DB: Productable/Product/PDO)]

    D --> E[Model/Observer Events]
    C --> F[Explicit Domain Events]
    E --> G[Invalidation Events]
    F --> G

    G --> H[Queue Listeners]
    H --> I[Jobs]

    I --> J[(ProductPrice / PDO Discount Index)]
    I --> K[(Product availability snapshot on products)]
    I --> L[(Search Engine Index)]

    M[Shop Read Path] --> N[Product scopes + Query services]
    J --> N
    K --> N
    L --> N
```

#### Detailed View

```mermaid
flowchart TB
    A[Admin Catalog API] --> B[Create/Update/Delete Productable
Course/Seminar/DigitalAsset]
    A --> C[Create/Update/Delete Product]
    A --> D[Create/Update/Delete ProductDeliveryOption]
    D --> E[Teacher assignment
attach/sync pivot]

    B --> F[ProductableAvailabilityObserver]
    C --> G[ProductCacheInvalidated]
    C --> H[ProductAvailabilityCacheInvalidated]
    C --> I[ProductSearchIndexInvalidated]
    D --> G
    D --> H
    D --> I

    G --> J[QueueProductPriceCacheUpdate]
    J --> K[UpdateProductPricingJob]
    K --> L[ProductPriceService
upsert product_prices + price_data_cache]

    H --> M[QueueProductAvailabilityUpdate]
    M --> N[UpdateProductAvailabilityJob]
    N --> O[Write denormalized availability fields]

    I --> P[QueueProductSearchIndexSynchronization]
    P --> Q[SynchronizeProductSearchIndexJob]

    R[Checkout/Order Flow] --> S["CreateOrderAction
lockForUpdate(PDO)"]
    S --> T[OrderStatusService]
    T --> U[EnrollmentStatusChanged]
    U --> V[UpdateProductDeliveryOptionEnrolledCount]
    V --> N
```

### 2) Key Execution Path

```mermaid
sequenceDiagram
    participant Admin
    participant Ctrl as Admin ProductDeliveryOptionController
    participant Act as UpdateProductDeliveryOptionAction
    participant DB as PostgreSQL
    participant Ev as Event Bus
    participant Lis as Queue Listeners
    participant Job as Update Jobs
    participant IDX as Search/Price Index

    Admin->>Ctrl: PUT /admin/product/{id}/delivery-option/{id}
    Ctrl->>Act: handle(data, option)
    Act->>DB: BEGIN + update PDO + sync teachers + COMMIT
    Act->>Ev: dispatch ProductCacheInvalidated
    Act->>Ev: dispatch ProductAvailabilityCacheInvalidated (conditional)
    Act->>Ev: dispatch ProductSearchIndexInvalidated (conditional)
    Ev->>Lis: queue listeners
    Lis->>Job: dispatch pricing/availability/search jobs
    Job->>DB: recompute snapshots/index rows
    Job->>IDX: sync searchable documents
```

### 3) State Transitions

```mermaid
stateDiagram-v2
    [*] --> Draft
    Draft --> Scheduled: publish later
    Draft --> Published: immediate publish
    Scheduled --> Published: start time reached/manual
    Published --> Archived: archive action
    Published --> Draft: unpublish rollback
    Archived --> Draft: restore/edit cycle

    state "Visibility Gate (Shop)" as VG {
[*] --> Hidden
Hidden --> Visible: Product=Published
Visible --> Hidden: Product!=Published
Visible --> Hidden: is_visible=false
Visible --> Hidden: no published PDO
Visible --> Hidden: productable not published
Visible --> Hidden: term inactive
}
```

### 4) Edge Case & Failure Matrix (Expected/Intended Behavior)

| Case | Expected Behavior | Enforced By |
|---|---|---|
| Product is `published` but has zero published delivery options | Hidden from shop/search until a published PDO exists | Product visibility/search scopes + searchable gate |
| Product term is inactive | Product excluded from shop/search without deleting data | `activeTerm` scope + availability projection |
| Productable (course/seminar/asset) is not published | Product remains non-searchable/non-listable | `publishedProductable` scope + searchable gate |
| Registration window not started/ended | Checkout rejects item even if item exists in cart | `CreateOrderFromCartAction::validateCartItems` |
| Capacity reached | Checkout rejects with sold-out validation message | checkout validation against capacity/enrolled_count |
| Duplicate gateway callback for completed payment | Callback ignored / blocked, no second completion | Payment processor gatekeeper checks |
| Product delete when order items exist | Delete blocked with domain exception | `DeleteProductAction` guard |
| Category delete when mapped to entities | Delete blocked to preserve taxonomy integrity | `DeleteCategoryAction` guard |

### 5) Developer Guardrails (Strict Do/Don’t)

1. **DO** keep controllers thin; **DON’T** write catalog business logic in controllers.
2. **DO** mutate Product/Productable/PDO via Action classes + DTO validation; **DON’T** bypass with ad-hoc model updates.
3. **DO** dispatch side-effect events/jobs after successful persistence boundaries; **DON’T** introduce pre-commit queue side effects for catalog projections.
4. **DO** preserve snapshot/projection pipeline (price index, availability snapshot, search sync) when changing status/date/relationship fields; **DON’T** change write paths without updating invalidation triggers.
5. **DO** protect destructive paths with invariant guards (orders/enrollments/relations); **DON’T** rely only on FK cascades for access-critical records.
