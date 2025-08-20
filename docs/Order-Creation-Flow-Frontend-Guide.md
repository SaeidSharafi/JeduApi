# Order Creation Flow - Frontend Developer Guide

## Overview
This document explains the order creation and payment flow from a frontend perspective, focusing on API endpoints, data structures, and user interactions.

## Table of Contents
1. [System Architecture Overview](#system-architecture-overview)
2. [Order Creation Process](#order-creation-process)
3. [Payment Processing](#payment-processing)
4. [API Endpoints Reference](#api-endpoints-reference)
5. [Data Structures](#data-structures)
6. [Error Handling](#error-handling)
7. [User Experience Flows](#user-experience-flows)

---

## System Architecture Overview

The order management system consists of three main phases:

```
Order Creation → Payment Processing → Fulfillment
     ↓               ↓                 ↓
   Order Bill    Payment Record    Status Updates
```

### Key Concepts

1. **Order** - A bill/invoice that records what a customer wants to purchase
2. **Order Items** - Individual products with their pricing and payment type
3. **Payment** - Separate records that track money received for orders
4. **Enrolment** - Access records created for each purchased item
5. **Discount System** - Automated promotion and coupon handling

---

## Order Creation Process

### Step 1: Product Selection & Configuration

Before creating an order, gather:

- **Product Delivery Options** - The specific product variants/options
- **Payment Type Choice** - Per item: `full_payment` or `pre_payment`
- **Quantities** - Number of each item (if allowed)
- **Coupon Code** - Optional discount code

### Step 2: Create Order API Call

**Endpoint:** `POST /api/v1/admin/orders`

**Request Body:**
```json
{
  "status": "pending",
  "customer_id": 123,
  "applied_coupon_code": "SAVE10",
  "admin_notes": "Order created via admin panel",
  "items": [
    {
      "product_delivery_option_id": 456,
      "payment_type": "full_payment",
      "qty_ordered": 1
    },
    {
      "product_delivery_option_id": 789,
      "payment_type": "pre_payment", 
      "qty_ordered": 2
    }
  ]
}
```

### Step 3: Order Creation Response

The system will:
1. **Validate** all items and payment types
2. **Apply discounts** automatically based on promotions
3. **Calculate totals** including taxes and discounts
4. **Create enrolments** in pending state
5. **Return complete order** with calculated prices

**Response:**
```json
{
  "data": {
    "id": 1001,
    "increment_id": "2001",
    "status": "pending",
    "customer_id": 123,
    "grand_total": 150000,
    "subtotal": 180000,
    "discount_amount": 30000,
    "balance_due": 150000,
    "applied_coupon_code": "SAVE10",
    "items": [
      {
        "id": 1,
        "name": "Advanced Laravel Course",
        "payment_type": "full_payment",
        "price": 100000,
        "total": 80000,
        "discount_amount": 20000
      },
      {
        "id": 2,
        "name": "Vue.js Workshop",
        "payment_type": "pre_payment",
        "price": 80000,
        "total": 70000,
        "discount_amount": 10000
      }
    ]
  }
}
```

---

## Payment Processing

### Understanding Payment Types

1. **Full Payment** - Customer pays the complete price immediately
2. **Pre-payment** - Customer pays a smaller amount now, rest later

### Step 1: Check Next Payment Details

**Endpoint:** `GET /api/v1/admin/orders/{order}/next-payment-details`

This tells you:
- How much needs to be paid
- What items are included
- Payment stage (initial or final balance)

**Response:**
```json
{
  "data": {
    "type": "initial_payment",
    "amount": 150000,
    "line_details": [
      {
        "type": {
          "value": "full_payment", 
          "label": "Full Payment"
        },
        "items": ["Advanced Laravel Course"],
        "amount": 80000
      },
      {
        "type": {
          "value": "pre_payment",
          "label": "Pre-payment" 
        },
        "items": ["Vue.js Workshop (2x)"],
        "amount": 70000
      }
    ]
  }
}
```

### Step 2: Create Payment

**Endpoint:** `POST /api/v1/admin/orders/{order}/payments`

**Request Body:**
```json
{
  "method": "bank_transfer",
  "status": "completed",
  "admin_notes": "Payment received via bank transfer",
  "data": {
    "transaction_id": "TXN123456",
    "transaction_date": "2025-08-15",
    "sender_name": "John Doe",
    "notes": "Payment for Laravel course"
  }
}
```

### Step 3: Automatic Status Updates

When a payment is marked as "completed", the system automatically:

1. **Updates Order Items** to "completed" status
2. **Activates Enrolments** giving students access
3. **Updates Order Status** to "completed" or "processing"
4. **Triggers Provisioning** for course materials/access

---

## API Endpoints Reference

### Orders
- `GET /api/v1/admin/orders` - List orders with filters
- `POST /api/v1/admin/orders` - Create new order
- `GET /api/v1/admin/orders/{order}` - Get order details
- `PUT /api/v1/admin/orders/{order}` - Update order status
- `DELETE /api/v1/admin/orders/{order}` - Delete pending order

### Payments
- `GET /api/v1/admin/orders/{order}/payments` - List order payments
- `POST /api/v1/admin/orders/{order}/payments` - Create payment
- `GET /api/v1/admin/orders/{order}/payments/{payment}` - Payment details
- `PUT /api/v1/admin/orders/{order}/payments/{payment}` - Update payment
- `DELETE /api/v1/admin/orders/{order}/payments/{payment}` - Delete pending payment

### Utilities
- `GET /api/v1/admin/orders/{order}/next-payment-details` - Payment info

---

## Data Structures

### Order States
- `pending` - Just created, no payments
- `processing` - Partial payment received
- `completed` - Fully paid and fulfilled
- `cancelled` - Cancelled by admin
- `refunded` - Fully refunded
- `partially_refunded` - Some items refunded

### Payment Methods
- `bank_transfer` - Manual bank transfer
- `credit_card` - Card payment
- `cash` - Cash payment
- `no_payment` - Free orders

### Payment Statuses
- `pending` - Payment initiated but not confirmed
- `completed` - Payment received and confirmed
- `failed` - Payment attempt failed
- `cancelled` - Payment cancelled

---

## Error Handling

### Common Validation Errors

#### Product Availability
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "items.0": ["Product 'Advanced Laravel Course' is not available"]
  }
}
```

#### Duplicate Purchase
```json
{
  "message": "The given data was invalid.", 
  "errors": {
    "items": ["Customer already has active enrollment for: Laravel Course"]
  }
}
```

#### Payment Errors
```json
{
  "message": "Order is already fully paid.",
  "errors": null
}
```

#### Capacity Issues
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "items.0": ["Insufficient capacity for 'Workshop'. Only 2 spots available."]
  }
}
```

---

## User Experience Flows

### Flow 1: Simple Order Creation

```
1. Admin selects customer
2. Admin adds products to cart
3. Admin chooses payment types per item
4. Admin applies coupon (optional)
5. System calculates discounts automatically
6. Admin reviews totals and creates order
7. System creates order + enrolments in pending state
```

### Flow 2: Payment Processing

```
1. Admin views order details
2. Admin clicks "Add Payment"
3. System shows payment breakdown
4. Admin enters payment details
5. Admin marks payment as completed
6. System automatically:
   - Updates order items to completed
   - Activates student enrolments
   - Updates order status
   - Triggers course access provisioning
```

### Flow 3: Mixed Payment Types

```
Order with both full payment and pre-payment items:

Initial Payment:
- Full payment items: Pay complete amount
- Pre-payment items: Pay pre-payment amount

Later Payment (when pre-payment items are due):
- Pre-payment items: Pay remaining balance
```

### Flow 4: Discount Application

```
1. Admin enters coupon code
2. System finds applicable promotion
3. System checks all conditions (cart value, product categories, etc.)
4. System applies discounts automatically
5. System shows breakdown of applied discounts
6. Discounts are preserved in order audit trail
```

---

## Frontend Implementation Tips

### 1. Real-time Price Calculation
- Call discount calculation service when coupon codes change
- Show live totals as user modifies cart
- Display discount breakdowns clearly

### 2. Payment Flow UX
- Always check next payment details before showing payment form
- Display clear breakdown of what's being paid for
- Show payment history and remaining balances

### 3. Error Prevention
- Validate product availability before order creation
- Check for duplicate enrollments
- Warn about capacity constraints

### 4. Status Tracking
- Show order progression visually
- Display enrollment status per item
- Provide clear payment history

### 5. Multi-step Payments
- For pre-payment orders, clearly show what's paid vs. what's pending
- Provide reminders for outstanding balances
- Make final balance payments obvious

---

## Security Considerations

### Admin Authentication
- All endpoints require admin authentication
- Use proper permission checks for order operations

### Payment Data
- Bank transfer details are validated and stored securely
- Sensitive payment information is encrypted

### Order Integrity
- Orders use database transactions to prevent partial states
- Pessimistic locking prevents race conditions
- Automatic calculation prevents price manipulation
