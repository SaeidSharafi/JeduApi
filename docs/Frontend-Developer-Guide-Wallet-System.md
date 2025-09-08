# Frontend Developer API Guide - JeduShop Wallet System

## Overview
This guide provides comprehensive documentation for frontend developers to integrate with JeduShop's Digital Wallet System. The system handles user wallet management, discount promotions, and campaign-based allocations.

## Authentication
All API endpoints require authentication using Bearer tokens:
```javascript
headers: {
  'Authorization': 'Bearer YOUR_TOKEN',
  'Accept': 'application/json',
  'Content-Type': 'application/json'
}
```

## Base URLs
- **Production**: `https://api.jedushop.com/api/v1/admin`
- **Development**: `http://localhost/api/v1/admin`

---

## 1. Wallet Management System

### 1.1 Deposit to Wallet
**Endpoint:** `POST /wallets/{wallet_id}/deposit`

Add money to a user's wallet balance.

**Request:**
```json
{
  "amount": 50000,
  "description": "Manual deposit by admin",
  "metadata": {
    "reference": "DEP-2024-001",
    "admin_note": "Customer service compensation"
  }
}
```

**Response (201):**
```json
{
  "message": "Wallet transaction created successfully.",
  "data": {
    "id": 123,
    "type": {
      "value": "deposit",
      "label": "Deposit"
    },
    "amount": 50000,
    "balance_after": 75000,
    "gift_balance_after": 25000,
    "source_type": {
      "value": "staff",
      "label": "Staff"
    },
    "source": {
      "id": 1,
      "name": "Admin User",
      "email": "admin@example.com"
    },
    "description": "Manual deposit by admin",
    "metadata": {
      "reference": "DEP-2024-001",
      "admin_note": "Customer service compensation"
    },
    "created_at": "2025-09-05 10:30:00"
  }
}
```

### 1.2 Withdraw from Wallet
**Endpoint:** `POST /wallets/{wallet_id}/withdraw`

Remove money from a user's wallet balance.

**Request:**
```json
{
  "amount": 25000,
  "description": "Manual withdrawal - refund processing",
  "metadata": {
    "reference": "WTH-2024-002",
    "reason": "Order cancellation refund"
  }
}
```

**Response (201):**
```json
{
  "message": "Wallet transaction created successfully.",
  "data": {
    "id": 124,
    "type": {
      "value": "withdrawal", 
      "label": "Withdrawal"
    },
    "amount": -25000,
    "balance_after": 50000,
    "gift_balance_after": 25000,
    "source_type": {
      "value": "staff",
      "label": "Staff"
    },
    "description": "Manual withdrawal - refund processing",
    "created_at": "2025-09-05 10:35:00"
  }
}
```

### 1.3 Adjust Wallet Balance
**Endpoint:** `POST /wallets/{wallet_id}/adjust`

Make positive or negative adjustments for error corrections.

**Request:**
```json
{
  "amount": -5000,
  "reason": "Double payment correction",
  "description": "Correcting duplicate credit entry",
  "metadata": {
    "correction_type": "duplicate_reversal",
    "original_transaction_id": 120
  }
}
```

**Response (201):**
```json
{
  "message": "Wallet transaction created successfully.",
  "data": {
    "id": 125,
    "type": {
      "value": "adjustment",
      "label": "Adjustment"
    },
    "amount": -5000,
    "balance_after": 45000,
    "metadata": {
      "reason": "Double payment correction",
      "adjustment_type": "debit",
      "correction_type": "duplicate_reversal",
      "original_transaction_id": 120
    },
    "created_at": "2025-09-05 10:40:00"
  }
}
```

---

## 2. Discount Promotion Wallet Actions

The system includes two discount promotion actions that add wallet credits during order processing:

### 2.1 Add Regular Wallet Credit
Adds credit to the user's main wallet balance when orders meet promotion criteria.

**Configuration in Promotion Rules:**
```json
{
  "type": "action",
  "handler": "add_wallet_credit",
  "configuration": {
    "amount": 10000,
    "per_item": false,
    "description": "Order completion bonus"
  }
}
```

**Per-Item Configuration:**
```json
{
  "type": "action", 
  "handler": "add_wallet_credit",
  "configuration": {
    "amount": 2000,
    "per_item": true,
    "description": "Per item cashback"
  }
}
```

### 2.2 Add Gift Credit
Adds credit to the user's gift balance with optional expiration.

**Configuration in Promotion Rules:**
```json
{
  "type": "action",
  "handler": "add_gift_credit", 
  "configuration": {
    "amount": 15000,
    "per_item": false,
    "expires_days": 30,
    "description": "Holiday gift bonus"
  }
}
```

**Key Differences:**
- **Regular Credit**: Added to `balance`, can be used for any purchase
- **Gift Credit**: Added to `gift_balance`, separate tracking with optional expiration

---

## 3. Wallet Campaign System

### 3.1 Get Wallet Campaigns
**Endpoint:** `GET /wallet-campaigns`

Retrieve list of all wallet campaigns.

**Query Parameters:**
- `page` (int): Page number for pagination
- `per_page` (int): Items per page (max 100)
- `type` (string): Filter by campaign type
- `is_active` (boolean): Filter by active status

**Response (200):**
```json
{
  "message": "Wallet campaigns retrieved successfully.",
  "data": [
    {
      "id": 1,
      "name": "Welcome Gift Campaign",
      "description": "Welcome bonus for new users",
      "type": {
        "value": "welcome_gift",
        "label": "Welcome Gift"
      },
      "amount": 25000,
      "is_active": true,
      "usage_limit_total": 1000,
      "usage_limit_per_user": 1,
      "total_usage_count": 245,
      "starts_at": "2025-01-01 00:00:00",
      "ends_at": "2025-12-31 23:59:59",
      "created_at": "2025-01-01 08:00:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 5
  }
}
```

### 3.2 Trigger Individual Campaign Allocation
**Endpoint:** `POST /users/{user_id}/wallet-campaigns/{campaign_id}/trigger-allocation`

Manually trigger a campaign allocation for a specific user.

**Request:**
```json
{
  "trigger_type": "manual",
  "reason": "Customer service compensation",
  "metadata": {
    "support_ticket": "TKT-2024-5678",
    "admin_notes": "Resolved billing issue"
  }
}
```

**Response (200):**
```json
{
  "message": "Gift credit allocated successfully.",
  "data": {
    "id": 126,
    "type": {
      "value": "gift",
      "label": "Gift"
    },
    "amount": 25000,
    "balance_after": 45000,
    "gift_balance_after": 50000,
    "source_type": {
      "value": "campaign",
      "label": "Campaign"
    },
    "source": {
      "id": 1,
      "name": "Welcome Gift Campaign",
      "type": "welcome_gift"
    },
    "description": "Customer service compensation",
    "metadata": {
      "campaign_id": 1,
      "trigger_type": "manual",
      "support_ticket": "TKT-2024-5678",
      "admin_notes": "Resolved billing issue"
    },
    "created_at": "2025-09-05 11:00:00"
  }
}
```

### 3.3 Trigger Bulk Campaign Allocation
**Endpoint:** `POST /wallet-campaigns/{campaign_id}/bulk-trigger-allocation`

Trigger campaign allocation for multiple users at once.

**Request:**
```json
{
  "user_ids": [1, 2, 3, 4, 5],
  "trigger_type": "manual",
  "reason": "Holiday bonus distribution",
  "metadata": {
    "batch_id": "HOLIDAY-2024-001",
    "distribution_date": "2025-09-05"
  }
}
```

**Response (200):**
```json
{
  "message": "Bulk allocation completed successfully. 4 users processed.",
  "data": {
    "success_count": 4,
    "failure_count": 1,
    "total_count": 5,
    "results": [
      {
        "user_id": 1,
        "status": "success",
        "transaction_id": 127,
        "amount": 25000
      },
      {
        "user_id": 2,
        "status": "success", 
        "transaction_id": 128,
        "amount": 25000
      },
      {
        "user_id": 3,
        "status": "success",
        "transaction_id": 129,
        "amount": 25000
      },
      {
        "user_id": 4,
        "status": "success",
        "transaction_id": 130,
        "amount": 25000
      },
      {
        "user_id": 5,
        "status": "failed",
        "error": "User already received this campaign allocation"
      }
    ]
  }
}
```

**Partial Success Response (207):**
```json
{
  "message": "Bulk allocation completed partially. 3 successful, 2 failed.",
  "data": {
    "success_count": 3,
    "failure_count": 2,
    "total_count": 5,
    "results": [...]
  }
}
```

```

---

## 4. Compliance Report System

The compliance report system provides comprehensive audit capabilities for monitoring wallet transactions, admin activities, and risk assessment. This section covers the API endpoints and UI implementation for compliance reporting.

### 4.1 Generate Compliance Report
**Endpoint:** `POST /admin/audit/compliance-report`

Generate comprehensive compliance reports with transaction analysis, admin activity monitoring, and risk assessment.

**Request:**
```json
{
  "date_from": "1403-06-15",
  "date_to": "1403-07-15",
  "include_transaction_analysis": true,
  "include_admin_activity": true,
  "include_risk_assessment": true
}
```

**Response (200):**
```json
{
  "message": "Compliance report generated successfully.",
  "data": {
    "report_period": {
      "from": "1403-06-15",
      "to": "1403-07-15"
    },
    "summary": {
      "total_transactions": 1250,
      "total_transaction_value": 15750000000,
      "total_admin_actions": 89,
      "risk_level": "medium"
    },
    "report_sections": {
      "transaction_analysis": {
        "by_type": {
          "deposit": {
            "count": 567,
            "total_amount": 8900000000,
            "average_amount": 15696476
          },
          "withdrawal": {
            "count": 234,
            "total_amount": -3200000000,
            "average_amount": -13675213
          },
          "gift": {
            "count": 449,
            "total_amount": 4050000000,
            "average_amount": 9021381
          }
        },
        "high_risk_transactions": [
          {
            "id": 12847,
            "amount": 75000000,
            "type": "deposit",
            "user_id": 1234,
            "created_at": "1403-06-20 02:30:00",
            "risk_factors": ["high_amount", "off_hours"]
          }
        ],
        "daily_breakdown": [
          {
            "date": "1403-06-15",
            "transaction_count": 45,
            "total_amount": 567000000,
            "risk_level": "low"
          }
        ]
      },
      "admin_activity": {
        "total_admin_actions": 89,
        "by_risk_level": {
          "low": 34,
          "medium": 28,
          "high": 23,
          "critical": 4
        },
        "by_action_type": {
          "wallet_deposit": 15,
          "wallet_withdraw": 8,
          "user_profile_update": 23,
          "campaign_allocation": 12
        },
        "failed_actions": 7,
        "success_rate": "92.1%"
      },
      "risk_assessment": {
        "overall_risk_score": 64,
        "risk_level": "high",
        "risk_factors": {
          "transaction_volume_risk": {
            "high_amount_transactions": 23,
            "high_amount_percentage": 18.4,
            "risk_level": "high"
          },
          "temporal_risk": {
            "off_hours_transactions": 67,
            "off_hours_percentage": 5.4,
            "risk_level": "low"
          },
          "pattern_risk": {
            "round_number_transactions": 89,
            "round_number_percentage": 7.1,
            "high_risk_transactions": 12,
            "high_risk_percentage": 0.96,
            "risk_level": "medium"
          },
          "admin_activity_risk": {
            "high_risk_admin_actions": 27,
            "high_risk_admin_percentage": 30.3,
            "failed_admin_actions": 7,
            "failed_admin_percentage": 7.9,
            "risk_level": "high"
          }
        },
        "recommendations": [
          {
            "priority": "high",
            "category": "transaction_volume",
            "message": "High volume of large transactions detected. Enhanced monitoring recommended.",
            "action": "implement_enhanced_monitoring"
          },
          {
            "priority": "medium",
            "category": "admin_activity",
            "message": "Increased admin activity requires review of authorization procedures.",
            "action": "review_admin_procedures"
          }
        ]
      }
    }
  }
}
```

### 4.2 Understanding Risk Assessment Data

**Risk Score Interpretation:**
- **0-39**: Low Risk (Green) - Normal operation
- **40-59**: Medium Risk (Yellow) - Monitor closely  
- **60-79**: High Risk (Orange) - Investigation needed
- **80-100**: Critical Risk (Red) - Immediate action required

**Risk Factor Examples:**

**Transaction Volume Risk:**
```javascript
// Example: Out of 1,000 transactions, 180 are ≥ 50M IRR
{
  "high_amount_transactions": 180,
  "high_amount_percentage": 18.0,  // 180/1000 = 18%
  "risk_level": "high"             // ≥15% threshold
}
```

**Temporal Risk:**
```javascript
// Example: 45 transactions occurred between 22:00-06:00
{
  "off_hours_transactions": 45,
  "off_hours_percentage": 4.5,     // 45/1000 = 4.5%
  "risk_level": "low"              // <10% threshold
}
```

**Pattern Risk:**
```javascript
// Example: Round numbers + metadata flagged transactions
{
  "round_number_transactions": 150,  // Amounts like 1M, 2M, 5M IRR
  "round_number_percentage": 15.0,   // 150/1000 = 15%
  "high_risk_transactions": 8,       // From transaction metadata
  "high_risk_percentage": 0.8,       // 8/1000 = 0.8%
  "risk_level": "medium"             // max(15%, 0.8%) = 15% ≥ 10%
}
```

### 4.3 Error Handling for Compliance Reports

**Common Error Responses:**
```json
// Date validation error (422)
{
  "message": "Validation error.",
  "errors": {
    "date_from": ["The date from field is required."],
    "date_to": ["The date to must be after date from."]
  }
}

// Permission error (403)
{
  "message": "Insufficient permissions.",
  "errors": {
    "permission": ["You do not have permission to view compliance reports."]
  }
}

// Date range too large (422)
{
  "message": "Date range validation failed.",
  "errors": {
    "date_range": ["Date range cannot exceed 365 days."]
  }
}
```

---

## 5. Suspicious Activity Monitoring

The system provides real-time monitoring and reporting of suspicious wallet activities and admin actions.

### 5.1 Get Suspicious Activity Alerts
**Endpoint:** `GET /admin/audit/suspicious-activities`

Retrieve suspicious activity alerts and patterns.

**Query Parameters:**
- `date_from` (string): Start date (Jalali format: YYYY-MM-DD)
- `date_to` (string): End date (Jalali format: YYYY-MM-DD)
- `risk_level` (string): Filter by risk level (low, medium, high, critical)
- `activity_type` (string): Filter by type (transaction, admin_action, pattern)
- `status` (string): Filter by status (pending, reviewed, resolved)
- `page` (int): Page number
- `per_page` (int): Items per page (max 100)

**Response (200):**
```json
{
  "message": "Suspicious activities retrieved successfully.",
  "data": [
    {
      "id": 1001,
      "type": "high_value_transaction",
      "risk_level": "high",
      "status": "pending",
      "detected_at": "1403-07-10 14:25:00",
      "transaction": {
        "id": 45678,
        "amount": 85000000,
        "type": "deposit",
        "user_id": 2345,
        "created_at": "1403-07-10 14:20:00"
      },
      "risk_factors": [
        "amount_exceeds_threshold",
        "user_unusual_pattern",
        "first_large_transaction"
      ],
      "automatic_actions_taken": [
        "transaction_flagged",
        "admin_notification_sent",
        "enhanced_monitoring_enabled"
      ],
      "description": "User deposited 850,000 Toman, significantly above their usual pattern of 50,000-100,000 Toman transactions."
    },
    {
      "id": 1002,
      "type": "off_hours_activity",
      "risk_level": "medium",
      "status": "reviewed",
      "detected_at": "1403-07-09 03:15:00",
      "transaction": {
        "id": 45012,
        "amount": 25000000,
        "type": "withdrawal",
        "user_id": 3456,
        "created_at": "1403-07-09 03:10:00"
      },
      "risk_factors": [
        "off_hours_transaction",
        "weekend_activity"
      ],
      "description": "Withdrawal transaction processed at 3:10 AM on weekend."
    },
    {
      "id": 1003,
      "type": "rapid_transaction_sequence",
      "risk_level": "high",
      "status": "resolved",
      "detected_at": "1403-07-08 16:45:00",
      "transactions": [
        {
          "id": 44801,
          "amount": 10000000,
          "created_at": "1403-07-08 16:30:00"
        },
        {
          "id": 44802,
          "amount": 10000000,
          "created_at": "1403-07-08 16:31:00"
        },
        {
          "id": 44803,
          "amount": 10000000,
          "created_at": "1403-07-08 16:32:00"
        }
      ],
      "user_id": 4567,
      "risk_factors": [
        "rapid_sequence",
        "identical_amounts",
        "round_numbers"
      ],
      "description": "User performed 8 identical transactions of 100,000 Toman within 5 minutes."
    },
    {
      "id": 1004,
      "type": "admin_high_risk_action",
      "risk_level": "critical",
      "status": "pending",
      "detected_at": "1403-07-07 11:30:00",
      "admin_action": {
        "id": 7890,
        "action_type": "bulk_wallet_adjustment",
        "admin_id": 12,
        "affected_users": 150,
        "total_amount": 750000000,
        "created_at": "1403-07-07 11:25:00"
      },
      "risk_factors": [
        "bulk_operation",
        "high_total_value",
        "unusual_for_admin"
      ],
      "description": "Admin performed bulk wallet adjustment affecting 150 users with total value of 7.5M Toman."
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 45,
    "total_pages": 3
  },
  "summary": {
    "total_alerts": 45,
    "pending_review": 12,
    "critical_alerts": 3,
    "trend": {
      "compared_to_last_period": "+15%",
      "most_common_type": "high_value_transaction"
    }
  }
}
```

### 5.2 Update Suspicious Activity Status
**Endpoint:** `PUT /admin/audit/suspicious-activities/{activity_id}/status`

Update the review status of a suspicious activity alert.

**Request:**
```json
{
  "status": "resolved",
  "notes": "Verified as legitimate transaction - customer received inheritance",
  "reviewer_id": 123,
  "resolution_actions": [
    "verified_documentation",
    "contacted_customer",
    "approved_transaction"
  ]
}
```

**Response (200):**
```json
{
  "message": "Suspicious activity status updated successfully.",
  "data": {
    "id": 1001,
    "status": "resolved",
    "updated_at": "1403-07-10 16:30:00",
    "reviewer": {
      "id": 123,
      "name": "Ahmad Mohammadi",
      "role": "Senior Compliance Officer"
    },
    "resolution_notes": "Verified as legitimate transaction - customer received inheritance",
    "resolution_date": "1403-07-10 16:30:00"
  }
}
```

### 5.3 Suspicious Activity Dashboard Metrics
**Endpoint:** `GET /admin/audit/suspicious-activities/dashboard`

Get dashboard metrics for suspicious activity monitoring.

**Response (200):**
```json
{
  "message": "Dashboard metrics retrieved successfully.",
  "data": {
    "current_period": {
      "total_alerts": 45,
      "by_risk_level": {
        "critical": 3,
        "high": 12,
        "medium": 18,
        "low": 12
      },
      "by_status": {
        "pending": 12,
        "under_review": 8,
        "resolved": 23,
        "false_positive": 2
      },
      "by_type": {
        "high_value_transaction": 18,
        "off_hours_activity": 12,
        "rapid_sequence": 8,
        "admin_high_risk": 4,
        "pattern_anomaly": 3
      }
    },
    "trends": {
      "daily_alerts": [
        {"date": "1403-07-05", "count": 3},
        {"date": "1403-07-06", "count": 5},
        {"date": "1403-07-07", "count": 8},
        {"date": "1403-07-08", "count": 4},
        {"date": "1403-07-09", "count": 6},
        {"date": "1403-07-10", "count": 7}
      ],
      "compared_to_last_month": {
        "percentage_change": "+22%",
        "absolute_change": 8
      }
    },
    "top_risk_factors": [
      {
        "factor": "high_amount_transactions",
        "count": 23,
        "percentage": 51.1
      },
      {
        "factor": "off_hours_activity",
        "count": 15,
        "percentage": 33.3
      },
      {
        "factor": "round_number_patterns",
        "count": 12,
        "percentage": 26.7
      }
    ],
    "response_times": {
      "average_resolution_time": "2.5 hours",
      "pending_longest": "15 hours",
      "sla_compliance": "94%"
    }
  }
}
```

---

## 6. User Interface Guidelines

### 6.1 Wallet Management UI Flow

**User Profile → Wallet Tab:**
1. Display current balances:
   ```javascript
   // Example wallet display
   {
     regular_balance: 45000,     // Main spendable balance
     gift_balance: 50000,        // Gift credits with expiration
     total_balance: 95000        // Combined for display
   }
   ```

2. Transaction history with filtering
3. Available campaigns section
4. Quick action buttons for admin operations

### 6.2 Campaign Management UI Flow

**Campaign List View:**
- Filter by type, status, date range
- Bulk selection for mass operations
- Usage statistics display

**Campaign Detail View:**
- Campaign information
- Usage analytics
- User allocation interface
- Bulk allocation tools

### 6.3 Error Handling

**Common Error Responses:**
```json
// Validation Error (422)
{
  "message": "Validation error.",
  "errors": {
    "amount": ["The amount field is required."],
    "user_ids": ["The user ids must not have more than 100 items."]
  }
}

// Insufficient Balance (422)
{
  "message": "Insufficient wallet balance.",
  "errors": {
    "balance": ["User has insufficient balance for this operation."]
  }
}

// Campaign Limit Reached (422)
{
  "message": "Campaign usage limit reached.",
  "errors": {
    "campaign": ["This campaign has reached its usage limit."]
  }
}
```

### 6.4 Status Codes Summary

- **200**: Success
- **201**: Created successfully
- **207**: Multi-Status (partial success in bulk operations)
- **400**: Bad Request
- **401**: Unauthorized
- **403**: Forbidden
- **404**: Not Found
- **422**: Validation Error
- **500**: Server Error


