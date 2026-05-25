
## Jedu E-Commerce Platform: A Definitive Architectural Blueprint

### Executive Summary

The Jedu E-Commerce Platform is a headless, API-first system engineered to sell complex educational products with flexibility and scale. Its architecture is founded on the strategic separation of academic content from its commercial packaging, enabling diverse sales models. The system is divided into two distinct worlds—a secure administrative control panel and a streamlined customer interface—powered by a robust backend that prioritizes transactional integrity, extensibility, and automated marketing. Every component, from the pluggable discount engine to the immutable wallet ledger and the proactive compliance monitor, is designed to provide a secure, scalable, and maintainable foundation for the business.

### 1. Guiding Architectural Principles

These non-negotiable principles govern all development and ensure a coherent, high-quality system.

-   **API-First & Headless:** The system is a pure API, completely decoupled from any specific frontend. This provides the freedom to build multiple user experiences (e.g., a website, a mobile app) on top of a single, consistent source of business logic.

-   **Action-Service Pattern (Centralized Logic):** All business logic is encapsulated within single-purpose Action classes and broader Service classes. Controllers are intentionally kept "thin," serving only to route requests. This makes the core logic reusable, easily testable, and independent of the HTTP layer.

-   **DTOs as Strict Contracts:** All data entering or leaving the API is defined by strongly-typed Data Transfer Objects (DTOs). This enforces validation at the edge of the system, provides self-documenting API contracts, and ensures data consistency throughout every operation.

-   **Event-Driven Decoupling:** Key business processes are linked via dispatched events (e.g., UserRegisteredEvent). This allows subsystems like the Wallet Campaign engine to react to events without being tightly coupled to the code that triggers them, creating a highly extensible system.

-   **Security by Design:** Security is woven into the architecture through dual-guard authentication, role-based permissions, private file storage, and a comprehensive audit trail with proactive risk assessment.


----------

### 2. The Product Catalog: Blueprints, Shells, and Offerings

The product system is a three-layer model that moves from abstract concept to a concrete, buyable item.

-   **Layer 1: The Blueprints (Course, Seminar, DigitalAsset):**  
    These are the foundational content assets—the intellectual property—and are not directly for sale.

    -   A **Course** is the academic blueprint: it defines the curriculum, learning objectives, and difficulty level.

    -   A **Seminar** is a blueprint for a one-time event: it holds the schedule and prerequisites.

    -   A **DigitalAsset** is a blueprint for standalone content, like a PDF ebook or a video tutorial.

-   **Layer 2: The Commercial Shell (Product):**  
    The Product model makes a blueprint "sellable" by wrapping it with essential business context: **who** is selling it (Vendor) and **when** it is being offered (Term). For example, a single Course blueprint for "Introduction to Python" could be used to create two distinct Product shells: one for the "Data Science Department" in "Fall 2025" and another for "Corporate Training" in "Winter 2026".

-   **Layer 3: The Purchase Options (ProductDeliveryOption):**  
    This is the specific **SKU** a customer adds to their cart. It defines the precise method and terms of purchase. A single "Intro to Python (Fall 2025)" Product could have multiple delivery options:

    -   **Option 1 (SKU: PYT-F25-LIVE):** "Live Online Cohort" via LIVE_SESSION_BBB - Price: $1,200.

    -   **Option 2 (SKU: PYT-F25-SELF):** "Self-Paced with Moodle Access" via LMS_MOODLE - Price: $600.

    -   **Option 3 (SKU: PYT-F25-EBOOK):** "Course Companion PDF" via DIRECT_DOWNLOAD - Price: $50.


The price of a ProductDeliveryOption is dynamically calculated based on a clear hierarchy: **1. Product-Specific Discounts > 2. Featured (Sale) Price > 3. Standard Price**. For performance, product-specific discounts are pre-calculated and cached, ensuring catalog-wide sales are displayed instantly.

----------

### 3. The Sales & Fulfillment Engine: A Transaction's Lifecycle

This engine orchestrates the journey from purchase to access with a focus on integrity and automation.

-   **Transactional Integrity (Order & OrderItem):**  
    An Order is the master receipt. Crucially, its OrderItems contain **JSON snapshots** of the product and customer data at the moment of purchase. This provides immutable, historical integrity. If a product's price changes a month later, the order record remains an accurate source of truth for what was sold, at what price, and to whom. This is vital for financial reporting and dispute resolution.

-   **Payment Integrity (Per-Attempt Tracking):**
    Every payment attempt is recorded as an immutable **PaymentTransaction** with full gateway request/response capture. The system generates unique sequential references with row-level locking for concurrency safety. This provides a complete audit trail for every gateway interaction, essential for debugging payment failures and reconciling with bank reports.

-   **Fulfillment & Access Provisioning (Enrolment):**  
    The Enrolment is the bridge between a financial transaction and content access. The process is fully automated and driven by the OrderStatusService:

    1.  A Payment is successfully processed, marking the OrderItem as complete.

    2.  This automatically creates an **Enrolment** record, linking the User to their purchased ProductDeliveryOption.

    3.  The system then dispatches **provisioning jobs** based on the delivery method of the purchased option. Each integration provider has its own dedicated, queued job with automatic retries (3 attempts with exponential backoff: 60s, 180s, 600s). The Enrolment stores per-provider provisioning state in a `provisioning_data` JSONB field, enabling idempotent retries and progress tracking.

-   **Provisioning Triggers (Configurable Orchestration):**  
    Not all orders provision immediately. The system supports three trigger modes per product:

    -   **`any_payment`**: Provision as soon as any payment is received (partial or full).
    -   **`full_payment`**: Provision only when the full amount is paid.
    -   **`manual_approval`**: A staff member must explicitly approve the order before provisioning begins. This is used for offline payments (bank transfers) where the funds must be verified manually.

    This flexibility allows the business to handle high-trust purchases (credit card → provision immediately) alongside low-trust ones (bank transfer → provision after verification).

-   **Current Integration Providers:**
    The provisioning system supports four external services, each with a dedicated service class and queued job:

    1.  **IMS (Internal Management System):** REST API for creating student and enrollment records. Logs failures to AdminActionLog for manual review. PII (email, phone) is redacted in logs.

    2.  **Moodle (LMS):** Web Services API for user creation, manual enrollment, course completion checks, grade retrieval, and SSO login URL generation. A separate `SyncMoodleProgressJob` updates enrollment data with Moodle progress when a customer views their course details.

    3.  **SpotPlayer (Video Platform):** License issuance API — generates a license key and player URL for the user.

    4.  **BigBlueButton (Live Sessions):** Meeting creation and join URL generation using SHA1 checksum authentication.

    All services resolve credentials through the SettingsService, allowing configuration via the admin panel without code changes.


----------

### 4. The Commercial & Engagement Engine: Wallets, Promotions & Campaigns

This is the dynamic core of the platform's marketing and financial operations.

-   **The Discount Engine (A Pluggable Rule System):**  
    This is a highly extensible **plugin architecture**. Instead of hard-coding logic, it uses discoverable "handlers" for Conditions (the "if") and Actions (the "then"). An administrator can construct complex promotions through the UI without writing code.

    -   **Example:** A promotion can be created with the rules:

        -   **IF** a cart_value_over $100 (a Condition)...

        -   **THEN**  apply_percentage_off all items (an Action)...

        -   **AND**  add_gift_credit of $10 to the user's wallet (another Action).  
            The system intelligently separates Product-Specific Promotions (which are cached for high-performance browsing) from Cart-Checkout Promotions (which are calculated in real-time).

-   **The Wallet System (An Immutable Ledger):**  
    The user wallet operates like a secure bank ledger with dual balances (balance for real funds, gift_balance for promotional credits).

    -   **Immutable Transactions:** A user's balance is never updated directly. Every single credit or debit is recorded as a new, immutable WalletTransaction entry.

    -   **Central Gateway:** All wallet operations are forced through a single, atomic **RecordWalletTransactionAction**. This action uses **pessimistic database locking** to prevent race conditions, ensuring that even under concurrent requests, the final balance is always correct and every transaction is logged.

-   **The Campaign System (Event-Driven Automation):**  
    This system transforms the wallet into a powerful marketing engine. It listens for key business events and automatically triggers corresponding campaigns.

    -   **Example 1:** A UserRegisteredEvent can trigger a "Welcome Bonus" campaign, automatically adding credit to the new user's wallet.

    -   **Example 2:** A scheduled job dispatches a UserBirthdayEvent, which can trigger a "Birthday Gift" campaign.  
        This event-driven design allows the marketing team to launch new promotions without requiring any changes to the core application code.


----------

### 5. The Dynamic Content & Presentation Layer

This subsystem controls the public-facing experience, managing how the brand and its products are presented.

-   **Composable Content (HomePageBlock, Slider):** The home page and other key landing pages are dynamically assembled from a collection of content models. This allows administrators to curate the user experience and update promotional banners through the admin panel without developer intervention.

-   **The Aggregator Engine (GetHomePageContentAction):** A central action is responsible for gathering these disparate pieces of content, integrating them with real-time pricing data from the ProductPriceService, and delivering a complete, ready-to-render payload to the frontend in a single, efficient API call.


----------

### 6. The Administrative & Compliance Backbone

The Admin Interface is built for granular control and total accountability.

-   **Control (Role-Based Access):** Using Spatie's Permission library, Staff accounts are assigned Roles with specific Permissions, enforcing a strong separation of duties.

-   **Accountability (The Unblinking Eye & Risk Assessment):**  
    Every administrative action is recorded in the AdminActionLog. This is elevated into a proactive **risk management engine** that scores risk based on four key factors:

    1.  **Transaction Volume Risk:** Flagging unusually high-value transactions.

    2.  **Temporal Risk:** Identifying suspicious off-hours activity.

    3.  **Pattern Risk:** Detecting anomalies like a high frequency of round-number transactions.

    4.  **Admin Activity Risk:** Monitoring the frequency of high-stakes admin operations.  
    The system can then generate compliance reports that provide not just data, but an overall risk score and actionable recommendations like "Conduct immediate audit".

-   **Secrets Management (Encrypted at Rest, Redacted in Transit):**  
    Integration credentials (API keys, tokens, passwords for IMS, Moodle, SpotPlayer, BBB) are sensitive data requiring special handling. The SettingsService handles this with three layers of protection:

    1.  **Encryption at Rest:** Secret field values are automatically encrypted via Laravel's `Crypt::encryptString()` when saved through the admin panel. A dedicated `settings:encrypt-secrets` command migrates any legacy plaintext values.
    
    2.  **Transparent Decryption on Read:** When the provisioning system reads these settings, they are automatically decrypted. Legacy plaintext values (from before the encryption feature existed) continue to work without migration — the system gracefully falls back.
    
    3.  **Redaction in API Responses:** When sending settings to the admin frontend for display, all secret field values are replaced with `***REDACTED***`. This prevents accidental exposure in browser dev tools or audit logs. The redaction is also applied to AdminActionLog entries for integration setting changes.

    This means an administrator can update integration credentials through the admin panel, see only redacted values in the UI, yet the provisioning system can read the real values under the hood — all without any manual key management.



----------

### 7. System-Wide Services: Secure File Management

The system enforces a strict separation between public and private assets to protect intellectual property.

-   **Dual Storage Strategy:** Publicly accessible files (like marketing images) are stored on a public disk. Sensitive, paid-for content (like course PDFs) is stored on a local, non-web-accessible disk.

-   **Secure Access Flow:** There are **no direct URLs** to private files. Access is granted exclusively through a controlled API endpoint that first authenticates the user and authorizes their permissions (e.g., Gate::authorize) before securely streaming the file from private storage to the user's browser.


----------

### Conclusion: Built for Growth

This architecture is deliberately designed not just for the features of today, but for the needs of tomorrow. The decoupled, principle-driven approach provides a solid foundation for future expansion: New product types can be added without altering the sales engine; new discount rules can be created and are immediately available to the marketing team; and new frontend experiences can be built without any changes to the backend logic. By adhering to these principles, the Jedu E-Commerce Platform is a robust, secure, and highly adaptable system capable of evolving with the business.
