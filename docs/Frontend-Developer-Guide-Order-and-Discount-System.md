# Frontend Developer Guide - JeduShop Order and Discount API

## Table of Contents
1. [Overview](#overview)
2. [Order Creation API Flow](#order-creation-api-flow)
3. [Discount System API](#discount-system-api)
4. [Dynamic Form Requirements](#dynamic-form-requirements)
5. [Business Rules and Validation](#business-rules-and-validation)
6. [API Endpoints Reference](#api-endpoints-reference)
7. [Data Structures](#data-structures)
8. [Error Handling](#error-handling)
9. [Common Pitfalls and Edge Cases](#common-pitfalls-and-edge-cases)

---

## Overview

This guide explains how to integrate with the JeduShop backend APIs for order creation and discount management. The system has specific business rules and validation requirements that frontend developers must understand to build proper user interfaces.

### System Phases
1. **Order Calculation & Preview** - Use the preview API to show calculated totals with discounts
2. **Order Creation** - Submit the final order data
3. **Payment Processing** - Create payments for orders

### Discount System Architecture
- **Product-Specific Discounts** - Pre-calculated and cached in database
- **Cart-Level Discounts** - Calculated during checkout
- **Dynamic Rule System** - Conditions and actions are loaded from metadata APIs
- **Priority-Based Application** - Multiple discounts follow priority rules

---

## Order Creation API Flow

### Step 1: Order Calculation Preview (Required)

**Endpoint**: `POST /api/v1/admin/order/preview`

Before creating any order, you MUST call this preview endpoint to get the calculated totals with all applicable discounts. This endpoint does NOT create any data in the database.

**Request Data** (same `OrderCreateData` DTO as order creation):
```json
{
  "customer_id": 123,
  "items": [
    {
      "product_delivery_option_id": 456,
      "qty_ordered": 2,
      "payment_type": "full_payment"
    }
  ],
  "applied_coupon_code": "SUMMER2024"
}
```

**Response Structure**:
```json
{
  "subtotal": 50000,
  "discount_amount": 12500,
  "grand_total": 37500,
  "items": [
    {
      "product_delivery_option": {
        "id": 456,
        "price": 25000,
        "product": { "name": "Course Name" }
      },
      "qty": 2,
      "price": 25000,
      "discount_amount": 12500,
      "total": 37500,
      "payment_type": "full_payment",
      "applied_discount_details": [
        {
          "promotion_id": 1,
          "promotion_name": "Summer Sale",
          "applied_amount": 12500,
          "coupon_code": "SUMMER2024"
        }
      ]
    }
  ],
  "applied_cart_discounts": [
    {
      "promotion_id": 1,
      "promotion_name": "Summer Sale",
      "total_discount_amount": 12500
    }
  ],
  "triggered_by_coupon_code": "SUMMER2024"
}
```

**Important**: Always show users the preview results before allowing them to confirm the order.

### Step 2: Order Creation

**Endpoint**: `POST /api/v1/admin/order`

Use the exact same data structure as the preview to create the actual order.

**Request Data**:
```json
{
  "status": "pending",
  "customer_id": 123,
  "items": [
    {
      "product_delivery_option_id": 456,
      "qty_ordered": 2,
      "payment_type": "full_payment"
    }
  ],
  "applied_coupon_code": "SUMMER2024",
  "admin_notes": "Special handling required"
}
```

### Step 3: Payment Creation

**Endpoint**: `POST /api/v1/admin/order/{order}/payment`

**Critical**: Payment amounts are NEVER provided by the frontend. The backend calculates the required amount automatically.

**Request Data** (no `order_id` — it's in the URL):
```json
{
  "method": "bank_transfer",
  "status": "completed",
  "admin_notes": "Payment confirmed via bank transfer",
  "data": {
    "transaction_id": "TXN123456",
    "transaction_date": "2024-03-15",
    "sender_name": "John Doe",
    "notes": "Payment reference details"
  }
}
```

**Payment Methods and Required Data**:
- **bank_transfer**: Requires `transaction_id`, `transaction_date`, `sender_name`
- **cash**: No additional data required
- **card**: No additional data required (handled by payment processor)

---

## Discount System API

### Metadata API - Dynamic Form Data

The discount system is completely dynamic. You must fetch metadata to build forms for creating discount promotions.

**Endpoint**: `GET /api/v1/admin/discount-info`

**Response Structure**:
```json
{
  "cart": {
    "conditions": [
      {
        "key": "cart_value_over",
        "name": "Minimum Cart Value",
        "description": "This condition is evaluated based on the total value of the customer's cart.",
        "handler_class": "App\\Services\\Discounts\\Cart\\Conditions\\CartValueCondition",
        "configuration_schema": {
          "operator": {
            "key": "operator",
            "type": "enum",
            "label": "Comparison Type",
            "required": true,
            "description": "How the customer's cart value is compared to the specified amount.",
            "cases": [
              {"value": "greater_than_or_equal", "label": "Greater than or equal"},
              {"value": "greater_than", "label": "Greater than"},
              {"value": "less_than_or_equal", "label": "Less than or equal"},
              {"value": "less_than", "label": "Less than"},
              {"value": "equal", "label": "Equal to"}
            ]
          },
          "value": {
            "key": "value",
            "type": "integer",
            "label": "Amount",
            "required": true,
            "description": "The amount the customer's cart is compared against (in Rials)."
          },
          "include_prepayments": {
            "key": "include_prepayments",
            "type": "boolean",
            "label": "Include Prepayments",
            "required": true,
            "description": "If enabled, prepayment amounts are also included in this condition's calculation."
          }
        }
      },
      {
        "key": "specific_products_in_cart",
        "name": "Specific Products in Cart",
        "description": "This condition checks whether the specified products are present in the cart.",
        "handler_class": "App\\Services\\Discounts\\Cart\\Conditions\\SpecificProductsInCartCondition",
        "configuration_schema": {
          "product_ids": {
            "key": "product_ids",
            "type": "array",
            "label": "Products",
            "required": true,
            "description": "The products that must be present in the cart for this condition to be met.",
            "item_type": "model",
            "model_reference": {
              "table": "products",
              "column": "id",
              "display_column": "name"
            }
          },
          "match_policy": {
            "key": "match_policy",
            "type": "enum",
            "label": "Match Policy",
            "required": true,
            "description": "Specifies whether the cart must contain at least one of the selected products, or all of them.",
            "default": "any",
            "cases": [
              {"value": "any", "label": "At least one of the selected items"},
              {"value": "all", "label": "All selected items"}
            ]
          }
        }
      }
    ],
    "actions": [
      {
        "key": "apply_percentage_off",
        "name": "Percentage Discount on Cart",
        "description": "A specified percentage is deducted from the total value of cart items.",
        "handler_class": "App\\Services\\Discounts\\Cart\\Actions\\ApplyPercentageDiscountToItemsAction",
        "configuration_schema": {
          "percentage": {
            "key": "percentage",
            "type": "integer",
            "label": "Discount Percentage",
            "required": true,
            "description": "The discount percentage to be deducted from the items' total; for example, 15 is equivalent to a 15% discount."
          }
        }
      },
      {
        "key": "apply_tiered_percentage_off",
        "name": "Tiered Percentage Off Cart",
        "description": "Applies a tiered percentage discount to the cart based on defined tiers.",
        "handler_class": "App\\Services\\Discounts\\Cart\\Actions\\ApplyTieredPercentageOffAction",
        "configuration_schema": {
          "tiers": {
            "key": "tiers",
            "type": "array",
            "label": "Discount Tiers",
            "required": true,
            "description": "The tiers defining amount thresholds and their corresponding discount percentages.",
            "item_type": "data",
            "item_class": "App\\Services\\Discounts\\Configs\\TierData",
            "item_schema": {
              "min_amount": {
                "key": "min_amount",
                "type": "integer",
                "label": "Min Amount",
                "required": true,
                "description": "Min Amount (integer)"
              },
              "percentage": {
                "key": "percentage",
                "type": "number",
                "label": "Percentage",
                "required": true,
                "description": "Percentage (number)"
              }
            }
          }
        }
      },
      {
        "key": "gift_product",
        "name": "Gift Product",
        "description": "Adds a product to the cart as a gift.",
        "handler_class": "App\\Services\\Discounts\\Cart\\Actions\\GiftProductAction",
        "configuration_schema": {
          "product_delivery_option_id": {
            "key": "product_delivery_option_id",
            "type": "integer",
            "label": "Gift Product",
            "required": true,
            "description": "The product delivery option to add as a gift.",
            "model_reference": {
              "table": "product_delivery_options",
              "column": "id",
              "display_column": "name"
            }
          }
        }
      }
    ]
  },
  "product": {
    "conditions": [
      {
        "key": "product_in_category",
        "name": "Product Category",
        "description": "This condition checks whether the product (or cart products) are within the specified categories.",
        "handler_class": "App\\Services\\Discounts\\Product\\Conditions\\ProductCategoryCondition",
        "configuration_schema": {
          "category_ids": {
            "key": "category_ids",
            "type": "array",
            "label": "Categories",
            "required": true,
            "description": "The categories the product must belong to for this condition to be met.",
            "item_type": "model",
            "model_reference": {
              "table": "categories",
              "column": "id",
              "display_column": "name"
            }
          },
          "match_policy": {
            "key": "match_policy",
            "type": "enum",
            "label": "Match Policy",
            "required": true,
            "description": "Specifies whether the product needs to be in at least one of the selected categories, or must be in all of them.",
            "default": "any",
            "cases": [
              {"value": "any", "label": "At least one of the selected items"},
              {"value": "all", "label": "All selected items"}
            ]
          }
        }
      },
      {
        "key": "delivery_method_is",
        "name": "Delivery Method",
        "description": "This condition checks whether the product's delivery method matches the selected options.",
        "handler_class": "App\\Services\\Discounts\\Product\\Conditions\\DeliveryMethodIsCondition",
        "configuration_schema": {
          "delivery_methods": {
            "key": "delivery_methods",
            "type": "array",
            "label": "Delivery Methods",
            "required": true,
            "description": "The allowed delivery methods for this condition.",
            "item_type": "enum",
            "item_enum": "App\\Enums\\Product\\DeliveryMethodEnum",
            "item_cases": [
              {"value": "lms_moodle", "label": "enums.DeliveryMethodEnum.lms_moodle"},
              {"value": "direct_download", "label": "enums.DeliveryMethodEnum.direct_download"},
              {"value": "video_platform_spotplayer", "label": "enums.DeliveryMethodEnum.video_platform_spotplayer"},
              {"value": "in_person", "label": "enums.DeliveryMethodEnum.in_person"},
              {"value": "live_session_bbb", "label": "enums.DeliveryMethodEnum.live_session_bbb"},
              {"value": "live_session_skyroom", "label": "enums.DeliveryMethodEnum.live_session_skyroom"}
            ]
          }
        }
      }
    ],
    "actions": [
      {
        "key": "apply_percentage_off_product",
        "name": "Product Percentage Discount",
        "description": "A specified percentage is deducted from the product price, and the new price is displayed to the customer on the product page and lists.",
        "handler_class": "App\\Services\\Discounts\\Product\\Actions\\ApplyPercentageDiscountToProductAction",
        "configuration_schema": {
          "percentage": {
            "key": "percentage",
            "type": "integer",
            "label": "Discount Percentage",
            "required": true,
            "description": "The discount percentage deducted from the product price; for example, 15 is equivalent to a 15% discount."
          }
        }
      }
    ]
  }
}
```

> **Note on `item_cases` labels**: Enum cases that use the `AdvanceEnum` trait return translation keys (e.g. `enums.DeliveryMethodEnum.lms_moodle`) rather than localized strings. Resolve them through your i18n pipeline before rendering. Non-AdvanceEnum cases return humanized titles directly.

### Creating Discount Promotions

**Endpoint**: `POST /api/v1/admin/discount-promotion`

**Request Structure**:
```json
{
  "name": "Summer Sale 2024",
  "description": "25% off electronics",
  "type": "cart_checkout",
  "is_active": true,
  "starts_at": "2024-06-01 00:00:00",
  "ends_at": "2024-08-31 23:59:59",
  "priority": 100,
  "stop_processing_subsequent_rules": false,
  "usage_limit_total": 1000,
  "usage_limit_per_customer": 1,
  "rules": [
    {
      "type": "condition",
      "handler": "cart_value_over",
      "configuration": {
        "operator": ">=",
        "value": 50000,
        "include_prepayments": false
      }
    },
    {
      "type": "action",
      "handler": "apply_percentage_off",
      "configuration": {
        "percentage": 25
      }
    }
  ],
  "coupons": [
    {
      "code": "SUMMER2024",
      "is_active": true,
      "usage_limit": 100
    }
  ]
}
```

### Available Endpoints for Metadata

- `GET /api/v1/admin/discount-info` - Complete metadata (conditions + actions)
- `GET /api/v1/admin/discount-info/conditions` - Only conditions
- `GET /api/v1/admin/discount-info/actions` - Only actions
- `GET /api/v1/admin/discount-info/operators` - Available math operators
- `GET /api/v1/admin/discount-info/types` - Promotion types

---

## Dynamic Form Requirements

### Understanding Configuration Schemas

Each condition and action has a `configuration_schema` that defines the form fields required. You must dynamically build forms based on this schema.

**Field Types** (`type`):
- `integer` - Number input (whole numbers only)
- `number` - Decimal number input
- `string` - Text input
- `boolean` - Checkbox / toggle
- `enum` - Dropdown with predefined options (see `cases`)
- `array` - A list — inspect `item_type` to pick the right widget (see below)
- `mixed` - Fallback when the type can't be inferred; render a generic JSON input

**Required Fields**: Check the `required` property — all required fields must be filled. Optional fields may carry a `default` value you should pre-populate.

### Enum Fields

When `type: "enum"`, use the `cases` array to populate a dropdown:
```json
{
  "type": "enum",
  "required": true,
  "cases": [
    {"value": "greater_than_or_equal", "label": "Greater than or equal"},
    {"value": "greater_than", "label": "Greater than"}
  ]
}
```

Enum cases that use the `AdvanceEnum` trait return translation keys in `label` (e.g. `enums.MatchPolicyEnum.any`). Resolve them through your i18n pipeline before rendering. Other enums return humanized titles directly.

### Array Fields (Important)

When `type: "array"`, you MUST inspect `item_type` to choose the right widget. The array no longer means "multi-select of random values" — the metadata tells you exactly what each item is.

| `item_type` | Extra keys present | Frontend widget |
|---|---|---|
| `integer` / `string` / `number` / `boolean` | (none) | Tag input or multi-text input. Validate each item against `item_type`. |
| `enum` | `item_enum`, `item_cases` | Multi-select dropdown populated from `item_cases`. Submit an array of `value`s. |
| `model` | `model_reference: {table, column, display_column}` | **Model picker** — resolve `model_reference.table` to an admin listing endpoint via a frontend-maintained local map (see [Model Picker Pattern](#model-picker-pattern)). Display the `display_column` value, submit an array of `column` values (typically IDs). |
| `data` | `item_class`, `item_schema` | **Nested repeater** — render a repeating group of fields described by `item_schema` (which is itself a `configuration_schema`). See [Nested Data Repeater Pattern](#nested-data-repeater-pattern) below. |
| `mixed` | (none) | Fallback — generic JSON input. |

**Example — array of model IDs**:
```json
{
  "key": "product_ids",
  "type": "array",
  "required": true,
  "item_type": "model",
  "model_reference": {
    "table": "products",
    "column": "id",
    "display_column": "name"
  }
}
```
Render a multi-select that shows product names but submits product IDs.

**Example — array of enums**:
```json
{
  "key": "delivery_methods",
  "type": "array",
  "required": true,
  "item_type": "enum",
  "item_enum": "App\\Enums\\Product\\DeliveryMethodEnum",
  "item_cases": [
    {"value": "lms_moodle", "label": "enums.DeliveryMethodEnum.lms_moodle"},
    {"value": "in_person", "label": "enums.DeliveryMethodEnum.in_person"}
  ]
}
```
Render a multi-select dropdown populated from `item_cases`.

**Example — array of nested Data (repeater)**:
```json
{
  "key": "tiers",
  "type": "array",
  "required": true,
  "item_type": "data",
  "item_class": "App\\Services\\Discounts\\Configs\\TierData",
  "item_schema": {
    "min_amount": { "type": "integer", "required": true },
    "percentage": { "type": "number", "required": true }
  }
}
```
Render a repeater where each row has two inputs (`min_amount`, `percentage`) driven by `item_schema`. Submit an array of objects.

### Model Picker Pattern

Scalar foreign-key fields (e.g. `product_delivery_option_id`) now carry a `model_reference` object at the same level as `type`. Render a single-select model picker:

```json
{
  "key": "product_delivery_option_id",
  "type": "integer",
  "required": true,
  "model_reference": {
    "table": "product_delivery_options",
    "column": "id",
    "display_column": "name"
  }
}
```

For array-of-model fields, `model_reference` sits at the same level as `item_type: "model"` — same lookup, but the widget is a multi-select.

**How to resolve `model_reference` to an API endpoint**

The backend does NOT emit an API path — it only emits the DB `table` name as a stable identifier. The frontend maintains its own local map from table name → admin listing endpoint. This keeps the discount metadata decoupled from the routing layer (which is a frontend concern).

```typescript
// Frontend — local map maintained by FE team. Table names are stable;
// renaming a table requires a migration, so this map rarely changes.
const MODEL_ENDPOINTS: Record<string, string> = {
  products:                  '/api/v1/admin/product',
  categories:                '/api/v1/admin/category',
  vendors:                   '/api/v1/admin/vendor',
  product_delivery_options:  '/api/v1/admin/product-delivery-option',
};

function resolveModelEndpoint(ref: ModelReference): string {
  const endpoint = MODEL_ENDPOINTS[ref.table];
  if (!endpoint) {
    throw new Error(`No listing endpoint registered for model table "${ref.table}". ` +
      `Add it to MODEL_ENDPOINTS in your discount form module.`);
  }
  return endpoint;
}
```

Steps when rendering a model picker:
1. Read `model_reference.table` from the field schema.
2. Look up the endpoint in your local `MODEL_ENDPOINTS` map.
3. Fetch a paginated list from that endpoint (with whatever search/filter params your listing API supports — typically `?search=` for typeahead pickers).
4. Display each option's `display_column` value to the user.
5. Submit the `column` value (usually `id`) as the field's value.

**Contract**: the backend guarantees `table`, `column`, and `display_column` are real columns on a real table. The frontend is responsible for knowing which admin listing endpoint serves that table. If you encounter a `model_reference.table` that isn't in your map, fail loudly — the FE team needs to add an entry.

### Nested Data Repeater Pattern

When `item_type: "data"`, the `item_schema` key holds a full nested `configuration_schema` (same shape as the parent). Render a repeater group where each row is a sub-form built from `item_schema`. Recurse to arbitrary depth — `item_schema` fields can themselves be arrays with their own `item_type` and `item_schema`.

```text
tiers (array, item_type: data)
└── item_schema
    ├── min_amount (integer, required)
    └── percentage (number, required)
```

Submit the parent field as an array of objects matching `item_schema`.

### Form Validation Requirements

The backend validates configurations using the schema. Ensure your frontend validates:
1. **Required fields** are filled
2. **Field types** match (integer vs string vs boolean)
3. **Enum values** are from the allowed `cases`
4. **Array fields** contain valid data — validate each item against `item_type`
5. **Model references** submit IDs that exist in the referenced table (the backend enforces `exists:table,column` on rules). Resolve `model_reference.table` to an admin listing endpoint via your frontend's local `MODEL_ENDPOINTS` map — see [Model Picker Pattern](#model-picker-pattern).
6. **Nested Data** items match their `item_schema` exactly

**Example Configuration Validation**:
```json
// For cart_value_over condition
{
  "operator": "greater_than_or_equal",  // Must be from enum cases (greater_than, less_than, equal, ...)
  "value": 50000,                       // Must be integer
  "include_prepayments": false           // Must be boolean
}

// For specific_products_in_cart condition
{
  "product_ids": [12, 34],              // Array of existing product IDs (see model_reference)
  "match_policy": "any"                 // Must be from enum cases (any | all)
}

// For apply_tiered_percentage_off action
{
  "tiers": [                            // Array of objects matching item_schema
    {"min_amount": 50000, "percentage": 10},
    {"min_amount": 100000, "percentage": 20}
  ]
}

// For gift_product action
{
  "product_delivery_option_id": 456     // Must be an existing product_delivery_options.id (see model_reference)
}
```

---

## Business Rules and Validation

### Critical Rule: Condition vs Action Requirements

This is a crucial business rule that affects form validation:

**For Product-Specific Discounts (`type: "product_specific"`)**:
- **MUST have at least 1 condition AND at least 1 action**
- Both are always required

**For Cart-Level Discounts (`type: "cart_checkout"`)**:
- **WITH coupon codes**: Only actions are required (conditions are optional)
- **WITHOUT coupon codes**: MUST have at least 1 condition AND at least 1 action

**Implementation Logic**:
```javascript
function validatePromotionRules(promotionData) {
  const { type, rules, coupons } = promotionData;
  
  const hasCondition = rules.some(rule => rule.type === 'condition');
  const hasAction = rules.some(rule => rule.type === 'action');
  const hasCoupons = coupons && coupons.length > 0;
  
  if (type === 'product_specific') {
    if (!hasCondition || !hasAction) {
      return 'Product discounts require both conditions and actions';
    }
  }
  
  if (type === 'cart_checkout') {
    if (!hasAction) {
      return 'All promotions require at least one action';
    }
    
    if (!hasCoupons && !hasCondition) {
      return 'Cart promotions without coupons require at least one condition';
    }
  }
  
  return null; // Valid
}
```

### Payment Type Business Rules

**full_payment**:
- Customer pays the complete amount immediately
- Gets immediate access to courses/products
- Discount applies to the full amount

**pre_payment**:
- Customer makes a partial payment upfront
- **CRITICAL**: Pre-payment items are NEVER discounted by cart-level promotions
- Only the pre-payment amount is collected initially
- Remaining balance collected later

### Pricing Hierarchy

Products have a specific pricing hierarchy that affects what users see:

1. **Product-specific discount price** (highest priority, if active promotion exists)
2. **Featured price** (manual sale price, if active within date range)
3. **Standard price** (default product price)

**Important**: Product-specific discounts are pre-calculated and cached. They appear as the base price in order calculations.

### Coupon Code Rules

- **One coupon per order** maximum
- **Usage limits** enforced at both global and per-customer levels
- **Case-sensitive** codes
- **Date range validation** based on promotion start/end dates
- **Active status** must be true for both promotion and coupon

### Discount Priority and Layering

- **Lower priority numbers = higher priority** (priority 1 beats priority 100)
- **stop_processing_subsequent_rules**: If true, no further promotions are evaluated
- **Product discounts apply before cart discounts**
- **Multiple conditions** in one promotion must ALL pass (AND logic)
- **Multiple actions** in one promotion ALL execute

### Refund Business Rules

**Deduction Options**:
- Provide EITHER `deduction_amount` (fixed Rials) OR `deduction_percent` (percent of original item price), not both
- Percentage is always calculated against the **original item price**, not the amount paid
- If deduction exceeds paid amount → refund amount = 0 (customer receives nothing)

**Per-Item vs Full-Order**:
- Per-item refund: refunds a single order item
- Full-order refund: refunds ALL refundable items at once
- Full-order is required for Digipay orders when `DIGIPAY_ALLOW_PARTIAL_REFUND` is `false` (default)

**`skip_gateway` Flag**:
- Requires `refunds.skip-gateway` permission
- When `true`, skips the payment gateway call entirely
- Use for manual refunds (bank transfer / Mellat) where admin wires money out-of-band

**Status Transitions**:
- Can only edit/delete refunds in `pending` status
- `pending` → `processing`, `completed`, `cancelled`
- `processing` → `completed`, `failed`
- `completed`, `failed`, `cancelled` are terminal

**Digipay Partial Refund Gate**:
- When `DIGIPAY_ALLOW_PARTIAL_REFUND=false` (default), per-item Digipay refunds return 422
- Admin must use the full-order refund endpoint instead

### Validation Edge Cases

**Empty Rules Array**:
- Not allowed - every promotion must have at least one action
- Validation will fail on the backend

**Invalid Handler Keys**:
- Must match exactly with available handlers from metadata API
- Case-sensitive matching

**Configuration Mismatches**:
- Each handler's configuration must match its schema exactly
- Type mismatches will cause validation failures

**Date Validation**:
- `starts_at` must be before `ends_at` (if both provided)
- Dates must be in 'Y-m-d H:i:s' format
- Past dates are allowed for starts_at

**Usage Limits**:
- `usage_limit_per_customer` cannot exceed `usage_limit_total`
- Zero means unlimited (but zero is different from null/empty)

---

## API Endpoints Reference

### Order Management
- `POST /api/v1/admin/order/preview` - Calculate order totals with discounts
- `POST /api/v1/admin/order` - Create new order
- `GET /api/v1/admin/order` - List orders with filtering
- `GET /api/v1/admin/order/{id}` - Get specific order details

### Payment Management
- `POST /api/v1/admin/order/{order}/payment` - Create payment for order (amount calculated automatically)
- `GET /api/v1/admin/order/{order}/payment` - List payments for an order
- `GET /api/v1/admin/order/{order}/payment/{id}` - Get specific payment details

### Discount Promotion Management
- `GET /api/v1/admin/discount-promotion` - List promotions with filtering
- `POST /api/v1/admin/discount-promotion` - Create new promotion
- `GET /api/v1/admin/discount-promotion/{id}` - Get specific promotion
- `PUT /api/v1/admin/discount-promotion/{id}` - Update promotion
- `DELETE /api/v1/admin/discount-promotion/{id}` - Delete promotion
- `PUT /api/v1/admin/discount-promotion/{id}/status` - Toggle active status

### Discount Metadata APIs (Essential for Dynamic Forms)
- `GET /api/v1/admin/discount-info` - Complete metadata (recommended)
- `GET /api/v1/admin/discount-info/conditions` - Available conditions only
- `GET /api/v1/admin/discount-info/actions` - Available actions only
- `GET /api/v1/admin/discount-info/types` - Promotion types

### Statistics and Reporting
- `GET /api/v1/admin/discount-promotion-statistics` - Promotion usage statistics

### Refund Management
- `GET /api/v1/admin/refund` - List refunds for an order item
- `POST /api/v1/admin/refund` - Create refund for a single order item
- `GET /api/v1/admin/refund/{refund}` - View refund details
- `PUT /api/v1/admin/refund/{refund}` - Edit a PENDING refund
- `DELETE /api/v1/admin/refund/{refund}` - Delete a PENDING refund
- `PUT /api/v1/admin/refund/{refund}/status` - Transition refund status (state machine)
- **`POST /api/v1/admin/order/{order}/refund`** - Refund entire order (all refundable items at once)

### Refund Status Values
- `pending` - Created, awaiting processing
- `processing` - Being processed
- `completed` - Money returned, access revoked (terminal)
- `failed` - Processing failed, can retry (terminal)
- `cancelled` - Admin cancelled (terminal)

### Refund Transaction Details (bank info)
```json
{
  "receiver_name": "Ali Rezaei",
  "card_number": "1234567890123456",
  "iban_number": "IR123456789012345678901234",
  "tracking_code": "TRK987654"
}
```

---

## Data Structures

### Order Creation Request
```typescript
interface OrderCreateRequest {
  status: 'pending' | 'processing' | 'completed' | 'cancelled';
  customer_id: number;
  items: OrderItemRequest[];
  applied_coupon_code?: string;
  promotion_id?: number;
  admin_notes?: string;
}

interface OrderItemRequest {
  product_delivery_option_id: number;
  qty_ordered: number;
  payment_type: 'full_payment' | 'pre_payment';
}
```

### Payment Creation Request
```typescript
interface PaymentCreateRequest {
  method: 'cash' | 'card' | 'bank_transfer';
  status: 'pending' | 'completed' | 'failed';
  admin_notes?: string;
  data?: PaymentMethodData;
}

interface PaymentMethodData {
  // For bank_transfer method
  transaction_id?: string;
  transaction_date?: string; // YYYY-MM-DD format
  sender_name?: string;
  notes?: string;
}
```

### Refund Request Data

**Per-Item Refund** (`POST /refund`):
```typescript
interface RefundCreateRequest {
  order_item_id: number;
  deduction_amount?: number;         // Fixed deduction in Rials
  deduction_percent?: number;        // Percentage deduction (0-100), based on original price
  transaction_details: {             // Bank info for manual refunds
    receiver_name: string;
    card_number: string;             // 16 digits
    iban_number: string;
    tracking_code?: string;
  };
  status: string;                    // RefundStatusEnum value
  skip_gateway?: boolean;            // Skip payment gateway (needs permission)
  admin_notes?: string;
}
```

**Full-Order Refund** (`POST /order/{order}/refund`):
```typescript
interface RefundOrderRequest {
  deduction_amount?: number;         // Flat deduction from total refundable sum
  deduction_percent?: number;        // Percentage applied per-item against original price
  skip_gateway?: boolean;            // Skip payment gateway (needs refunds.skip-gateway permission)
  admin_notes?: string;
  receiver_name?: string;            // Bank transfer receiver
  card_number?: string;              // 16-digit card number
  iban?: string;                     // IBAN number
}
```

**Status Update** (`PUT /refund/{refund}/status`):
```typescript
interface RefundStatusUpdateRequest {
  status: 'completed' | 'processing' | 'failed' | 'cancelled';
  tracking_code?: string;
  skip_gateway?: boolean;
  admin_notes?: string;
}
```

### Discount Promotion Request
```typescript
interface DiscountPromotionRequest {
  name: string;
  description?: string;
  type: 'product_specific' | 'cart_checkout';
  is_active: boolean;
  starts_at?: string; // 'YYYY-MM-DD HH:mm:ss' format
  ends_at?: string;   // 'YYYY-MM-DD HH:mm:ss' format
  priority: number;   // Lower numbers = higher priority
  stop_processing_subsequent_rules: boolean;
  usage_limit_total?: number;
  usage_limit_per_customer?: number;
  rules: PromotionRule[];
  coupons: CouponRequest[];
}

interface PromotionRule {
  type: 'condition' | 'action';
  handler: string; // Must match handler key from metadata
  configuration: Record<string, any>; // Must match handler's schema
}

interface CouponRequest {
  code: string;     // Case-sensitive
  is_active: boolean;
  usage_limit?: number;
}
```

### Response Data Structures

**Order Preview Response**:
```typescript
interface OrderPreviewResponse {
  subtotal: number;           // Total before discounts (in cents)
  discount_amount: number;    // Total discount applied (in cents)
  grand_total: number;        // Final total after discounts (in cents)
  items: CalculatedOrderItem[];
  applied_cart_discounts: AppliedDiscount[];
  triggered_by_coupon_code?: string;
}

interface CalculatedOrderItem {
  product_delivery_option: ProductDeliveryOption;
  qty: number;
  price: number;              // Unit price (in cents)
  discount_amount: number;    // Total discount for this item (in cents)
  total: number;              // Final total for this item (in cents)
  payment_type: string;
  applied_discount_details: DiscountDetail[];
}
```

**Metadata Response**:
```typescript
interface MetadataResponse {
  cart: {
    conditions: HandlerMetadata[];
    actions: HandlerMetadata[];
  };
  product: {
    conditions: HandlerMetadata[];
    actions: HandlerMetadata[];
  };
}

interface HandlerMetadata {
  key: string;                    // Handler identifier — used in rule `handler` field
  name: string;                   // Human-readable name
  description: string;            // Description for UI
  handler_class: string;          // Backend class name (informational)
  configuration_schema: Record<string, FieldSchema>;
}

interface FieldSchema {
  key: string;
  type: 'integer' | 'number' | 'string' | 'boolean' | 'array' | 'enum' | 'mixed' | string;
  label: string;
  required: boolean;
  description: string;

  // Present only when `type: "enum"`
  cases?: EnumCase[];

  // Present when a default value is declared
  default?: unknown;

  // --- Array fields (`type: "array"`) ---
  // Inspect `item_type` to pick the right widget.
  item_type?: 'integer' | 'number' | 'string' | 'boolean' | 'enum' | 'model' | 'data' | 'mixed';

  // When `item_type: "enum"` — the enum class + selectable cases
  item_enum?: string;        // Fully-qualified enum class name
  item_cases?: EnumCase[];   // Same shape as `cases`

  // When `item_type: "model"` — where to fetch options
  // Also present at the field level when a scalar field is a foreign key
  model_reference?: {
    table: string;
    column: string;             // submit this column's value (usually "id")
    display_column: string;     // show this column's value to the user
  };

  // When `item_type: "data"` — nested schema for a repeater group
  item_class?: string;                        // Fully-qualified Data class name
  item_schema?: Record<string, FieldSchema>;  // Same shape as `configuration_schema`
}

interface EnumCase {
  value: string;
  label: string;  // May be a translation key (e.g. "enums.MatchPolicyEnum.any") for AdvanceEnum-backed enums
}
```

---

## Error Handling

### HTTP Status Codes and Responses

**422 Unprocessable Entity** - Validation Errors:
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "rules.0.configuration.value": [
      "The value field is required."
    ],
    "rules.0.configuration.operator": [
      "The selected operator is invalid."
    ],
    "applied_coupon_code": [
      "The coupon code has exceeded its usage limit."
    ],
    "items.0.product_delivery_option_id": [
      "The selected product delivery option id is invalid."
    ]
  }
}
```

**400 Bad Request** - Business Logic Errors:
```json
{
  "message": "Customer already has an active enrollment for this product.",
  "code": "DUPLICATE_ENROLLMENT"
}
```

**403 Forbidden** - Authorization Errors:
```json
{
  "message": "This action is unauthorized.",
  "code": "UNAUTHORIZED"
}
```

**404 Not Found** - Resource Not Found:
```json
{
  "message": "No query results for model [DiscountPromotion] 123"
}
```

### Discount-Specific Error Messages

**Invalid Handler Configuration**:
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "rules": [
      "The configuration is invalid for handler 'cart_value_over': The operator field is required, The value field must be an integer."
    ]
  }
}
```

**Missing Required Rules**:
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "rules": [
      "Product discounts require both conditions and actions"
    ]
  }
}
```

**Handler Not Found**:
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "rules": [
      "Handler 'invalid_handler' is not recognized"
    ]
  }
}
```

### Common Validation Scenarios

**Order Creation Errors**:
- Invalid product delivery option IDs
- Invalid customer ID
- Invalid payment type for product
- Duplicate enrollment attempts
- Product not available or published
- Capacity exceeded

**Payment Creation Errors**:
- Order not found
- Order already fully paid
- Invalid payment method data
- Missing required fields for bank transfers

**Refund Errors**:
- Order has no completed payments (`no_completed_payments`)
- Item already refunded (`already_refunded`)
- Existing refund request for this item (`refund_request_exists`)
- Digipay partial refund not supported — use full-order endpoint instead (`digipay_partial_refund_not_supported`)
- No refundable items in order (`no_refundable_items`)
- Deduction amount conflicts with deduction percentage (`deduction_conflict`)
- Missing `refunds.skip-gateway` permission for `skip_gateway` flag

**Discount Creation Errors**:
- Invalid handler keys
- Configuration doesn't match schema
- Missing required conditions/actions
- Invalid date ranges
- Duplicate coupon codes

### Error Response Handling

Always check HTTP status codes and handle the `errors` object for field-specific validation messages. The `errors` object maps field names to arrays of error messages, allowing you to display validation errors next to the appropriate form fields.

---

## Common Pitfalls and Edge Cases

### Order Creation Pitfalls

**1. Skipping Order Preview**
- ❌ Never create orders without showing preview first
- ✅ Always call `/order/preview` before order creation
- Users need to see final prices with discounts applied

**2. Mismatching Preview and Order Data**
- ❌ Don't modify order data between preview and creation
- ✅ Use identical data for both preview and creation calls
- Any changes should trigger a new preview

**3. Payment Amount Handling**
- ❌ Never send payment amounts from frontend
- ✅ Backend calculates all payment amounts automatically
- Sending amounts will be ignored or cause errors

### Discount Creation Pitfalls

**4. Ignoring Condition/Action Requirements**
- ❌ Product discounts without both conditions AND actions
- ❌ Cart discounts without coupons and without conditions
- ✅ Follow the business rules exactly as documented

**5. Configuration Type Mismatches**
- ❌ Sending string "123" for integer fields
- ❌ Sending integer 1 for boolean fields
- ✅ Match exact types from configuration schema

**6. Handler Key Case Sensitivity**
- ❌ Using "Cart_Value_Over" instead of "cart_value_over"
- ✅ Use exact handler keys from metadata API

**7. Enum Value Validation**
- ❌ Using "greater_than" instead of ">" for operators
- ✅ Use exact enum values from the cases array

### Coupon Code Edge Cases

**8. Case Sensitivity**
- Coupon codes are case-sensitive
- "SUMMER2024" ≠ "summer2024"

**9. Usage Limit Checking**
- Zero usage_limit means unlimited
- Null/undefined usage_limit also means unlimited
- Check both promotion and individual coupon limits

**10. Date Range Validation**
- Coupon validity follows promotion date ranges
- Inactive promotions make coupons invalid regardless of coupon status

### Form Building Edge Cases

**11. Missing Schema Handling**
- Some handlers might not have configuration schemas
- Always check if configuration_schema exists before building forms

**12. Default Value Handling**
- Some fields have default values in the schema
- Pre-populate forms with these defaults

**13. Required Field Validation**
- Always validate required fields before submission
- Backend validation will catch this, but frontend should prevent submission

### API Response Edge Cases

**14. Empty Discount Arrays**
- Some responses may have empty applied_cart_discounts arrays
- Handle empty arrays gracefully in UI

**15. Null vs Undefined Values**
- APIs may return null for optional fields
- Handle both null and undefined in frontend code

**16. Decimal vs Integer Confusion**
- All monetary amounts are in cents (integers)
- Percentages are decimals (25.5 for 25.5%)
- Convert appropriately for display

### Performance Considerations

**17. Metadata Caching**
- Discount metadata changes rarely
- Cache metadata responses in frontend
- Refresh when creating new promotions

**18. Preview Throttling**
- Don't call preview on every keystroke
- Debounce preview calls when users are typing
- Show loading states during preview calculations

### Date Format Requirements

**19. DateTime Format Strictness**
- Use 'YYYY-MM-DD HH:mm:ss' format exactly
- Time zone handling depends on backend configuration
- Invalid formats will cause validation errors

**20. Date Range Logic**
- starts_at can be in the past (for already running promotions)
- ends_at must be after starts_at if both are provided
- Null dates mean no restrictions

These pitfalls and edge cases represent common issues encountered when integrating with the JeduShop API. Following these guidelines will help avoid most integration problems.
