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

---

## 7. Frontend Implementation Examples

### 7.1 Compliance Report Component

```javascript
// React component for generating compliance reports
const ComplianceReportGenerator = ({ onReportGenerated }) => {
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [includeSections, setIncludeSections] = useState({
    transaction_analysis: true,
    admin_activity: true,
    risk_assessment: true
  });
  const [loading, setLoading] = useState(false);
  const [report, setReport] = useState(null);

  const generateReport = async () => {
    setLoading(true);
    try {
      const response = await api.post('/admin/audit/compliance-report', {
        date_from: dateFrom,
        date_to: dateTo,
        include_transaction_analysis: includeSections.transaction_analysis,
        include_admin_activity: includeSections.admin_activity,
        include_risk_assessment: includeSections.risk_assessment
      });
      
      setReport(response.data.data);
      onReportGenerated?.(response.data.data);
    } catch (error) {
      console.error('Report generation failed:', error.response?.data);
      // Handle error display
    } finally {
      setLoading(false);
    }
  };

  const getRiskLevelColor = (score) => {
    if (score >= 80) return '#dc3545'; // Critical - Red
    if (score >= 60) return '#fd7e14'; // High - Orange  
    if (score >= 40) return '#ffc107'; // Medium - Yellow
    return '#28a745'; // Low - Green
  };

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('fa-IR', {
      style: 'currency',
      currency: 'IRR',
      minimumFractionDigits: 0
    }).format(amount);
  };

  return (
    <div className="compliance-report-generator">
      <div className="form-section">
        <h3>Generate Compliance Report</h3>
        
        <div className="date-inputs">
          <label>
            From Date (Jalali):
            <input
              type="text"
              value={dateFrom}
              onChange={(e) => setDateFrom(e.target.value)}
              placeholder="1403-06-15"
              pattern="\\d{4}-\\d{2}-\\d{2}"
            />
          </label>
          
          <label>
            To Date (Jalali):
            <input
              type="text"
              value={dateTo}
              onChange={(e) => setDateTo(e.target.value)}
              placeholder="1403-07-15"
              pattern="\\d{4}-\\d{2}-\\d{2}"
            />
          </label>
        </div>

        <div className="section-toggles">
          <label>
            <input
              type="checkbox"
              checked={includeSections.transaction_analysis}
              onChange={(e) => setIncludeSections(prev => ({
                ...prev,
                transaction_analysis: e.target.checked
              }))}
            />
            Transaction Analysis
          </label>
          
          <label>
            <input
              type="checkbox"
              checked={includeSections.admin_activity}
              onChange={(e) => setIncludeSections(prev => ({
                ...prev,
                admin_activity: e.target.checked
              }))}
            />
            Admin Activity
          </label>
          
          <label>
            <input
              type="checkbox"
              checked={includeSections.risk_assessment}
              onChange={(e) => setIncludeSections(prev => ({
                ...prev,
                risk_assessment: e.target.checked
              }))}
            />
            Risk Assessment
          </label>
        </div>

        <button 
          onClick={generateReport} 
          disabled={loading || !dateFrom || !dateTo}
          className="generate-btn"
        >
          {loading ? 'Generating...' : 'Generate Report'}
        </button>
      </div>

      {report && (
        <div className="report-results">
          <h3>Compliance Report Results</h3>
          
          <div className="report-summary">
            <div className="summary-card">
              <h4>Report Period</h4>
              <p>{report.report_period.from} to {report.report_period.to}</p>
            </div>
            
            <div className="summary-card">
              <h4>Total Transactions</h4>
              <p>{report.summary.total_transactions?.toLocaleString()}</p>
            </div>
            
            <div className="summary-card">
              <h4>Transaction Value</h4>
              <p>{formatCurrency(report.summary.total_transaction_value)}</p>
            </div>
          </div>

          {report.report_sections.risk_assessment && (
            <div className="risk-assessment-section">
              <h4>Risk Assessment</h4>
              <div 
                className="risk-score"
                style={{ 
                  backgroundColor: getRiskLevelColor(report.report_sections.risk_assessment.overall_risk_score),
                  color: 'white',
                  padding: '10px',
                  borderRadius: '5px',
                  textAlign: 'center'
                }}
              >
                <strong>Overall Risk Score: {report.report_sections.risk_assessment.overall_risk_score}/100</strong>
                <br />
                <span>Risk Level: {report.report_sections.risk_assessment.risk_level.toUpperCase()}</span>
              </div>

              <div className="risk-factors">
                <h5>Risk Factors</h5>
                {Object.entries(report.report_sections.risk_assessment.risk_factors).map(([key, factor]) => (
                  <div key={key} className="risk-factor">
                    <h6>{key.replace(/_/g, ' ').toUpperCase()}</h6>
                    <p>Risk Level: <span className={`risk-${factor.risk_level}`}>{factor.risk_level}</span></p>
                    {factor.high_amount_percentage && (
                      <p>High Amount Transactions: {factor.high_amount_percentage}%</p>
                    )}
                  </div>
                ))}
              </div>

              <div className="recommendations">
                <h5>Recommendations</h5>
                {report.report_sections.risk_assessment.recommendations.map((rec, index) => (
                  <div key={index} className={`recommendation priority-${rec.priority}`}>
                    <strong>{rec.priority.toUpperCase()} PRIORITY:</strong>
                    <p>{rec.message}</p>
                    <small>Action: {rec.action}</small>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
};
```

### 7.2 Suspicious Activity Monitor

```javascript
// Vue.js component for monitoring suspicious activities
const SuspiciousActivityMonitor = {
  data() {
    return {
      activities: [],
      filters: {
        risk_level: '',
        activity_type: '',
        status: 'pending',
        date_from: '',
        date_to: ''
      },
      loading: false,
      updating: {},
      dashboardMetrics: null
    }
  },
  
  async mounted() {
    await this.loadDashboardMetrics();
    await this.loadActivities();
    
    // Set up real-time updates
    this.setupWebSocketConnection();
  },
  
  methods: {
    async loadDashboardMetrics() {
      try {
        const response = await this.$http.get('/admin/audit/suspicious-activities/dashboard');
        this.dashboardMetrics = response.data.data;
      } catch (error) {
        console.error('Failed to load dashboard metrics:', error);
      }
    },

    async loadActivities() {
      this.loading = true;
      try {
        const params = new URLSearchParams();
        Object.entries(this.filters).forEach(([key, value]) => {
          if (value) params.append(key, value);
        });

        const response = await this.$http.get(`/admin/audit/suspicious-activities?${params}`);
        this.activities = response.data.data;
      } catch (error) {
        this.$toast.error('Failed to load suspicious activities');
      } finally {
        this.loading = false;
      }
    },

    async updateActivityStatus(activityId, status, notes = '') {
      this.updating[activityId] = true;
      try {
        const response = await this.$http.put(
          `/admin/audit/suspicious-activities/${activityId}/status`,
          {
            status,
            notes,
            reviewer_id: this.$auth.user.id,
            resolution_actions: this.getResolutionActions(status)
          }
        );

        // Update the activity in the list
        const index = this.activities.findIndex(a => a.id === activityId);
        if (index !== -1) {
          this.activities[index] = { ...this.activities[index], ...response.data.data };
        }

        this.$toast.success('Activity status updated successfully');
      } catch (error) {
        this.$toast.error('Failed to update activity status');
      } finally {
        this.updating[activityId] = false;
      }
    },

    getResolutionActions(status) {
      const actions = {
        'resolved': ['verified_documentation', 'contacted_customer', 'approved_transaction'],
        'false_positive': ['reviewed_thoroughly', 'confirmed_legitimate'],
        'escalated': ['requires_investigation', 'flagged_for_review']
      };
      return actions[status] || [];
    },

    getRiskLevelBadgeClass(riskLevel) {
      const classes = {
        'low': 'badge-success',
        'medium': 'badge-warning', 
        'high': 'badge-danger',
        'critical': 'badge-dark'
      };
      return `badge ${classes[riskLevel] || 'badge-secondary'}`;
    },

    formatCurrency(amount) {
      return new Intl.NumberFormat('fa-IR', {
        style: 'currency',
        currency: 'IRR',
        minimumFractionDigits: 0
      }).format(amount);
    },

    setupWebSocketConnection() {
      // Listen for real-time suspicious activity alerts
      window.Echo.channel('suspicious-activities')
        .listen('SuspiciousActivityDetected', (e) => {
          this.activities.unshift(e.activity);
          this.$toast.warning(`New suspicious activity detected: ${e.activity.type}`);
        });
    }
  },

  template: `
    <div class="suspicious-activity-monitor">
      <div class="dashboard-metrics" v-if="dashboardMetrics">
        <h3>Suspicious Activity Dashboard</h3>
        
        <div class="metrics-cards">
          <div class="metric-card">
            <h4>Total Alerts</h4>
            <span class="metric-value">{{ dashboardMetrics.current_period.total_alerts }}</span>
          </div>
          
          <div class="metric-card">
            <h4>Pending Review</h4>
            <span class="metric-value critical">{{ dashboardMetrics.current_period.by_status.pending }}</span>
          </div>
          
          <div class="metric-card">
            <h4>Critical Alerts</h4>
            <span class="metric-value danger">{{ dashboardMetrics.current_period.by_risk_level.critical }}</span>
          </div>
          
          <div class="metric-card">
            <h4>Response Time</h4>
            <span class="metric-value">{{ dashboardMetrics.response_times.average_resolution_time }}</span>
          </div>
        </div>
      </div>

      <div class="filters-section">
        <h4>Filter Activities</h4>
        <div class="filter-controls">
          <select v-model="filters.risk_level" @change="loadActivities">
            <option value="">All Risk Levels</option>
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
            <option value="critical">Critical</option>
          </select>
          
          <select v-model="filters.status" @change="loadActivities">
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="under_review">Under Review</option>
            <option value="resolved">Resolved</option>
            <option value="false_positive">False Positive</option>
          </select>
          
          <select v-model="filters.activity_type" @change="loadActivities">
            <option value="">All Types</option>
            <option value="high_value_transaction">High Value Transaction</option>
            <option value="off_hours_activity">Off Hours Activity</option>
            <option value="rapid_sequence">Rapid Sequence</option>
            <option value="admin_high_risk">Admin High Risk</option>
          </select>
        </div>
      </div>

      <div class="activities-list">
        <h4>Suspicious Activities</h4>
        
        <div v-if="loading" class="loading">Loading activities...</div>
        
        <div v-else-if="activities.length === 0" class="no-activities">
          No suspicious activities found for the selected filters.
        </div>
        
        <div v-else class="activity-cards">
          <div 
            v-for="activity in activities" 
            :key="activity.id" 
            class="activity-card"
            :class="'risk-' + activity.risk_level"
          >
            <div class="activity-header">
              <div class="activity-type">
                <strong>{{ activity.type.replace(/_/g, ' ').toUpperCase() }}</strong>
                <span :class="getRiskLevelBadgeClass(activity.risk_level)">
                  {{ activity.risk_level.toUpperCase() }}
                </span>
              </div>
              <div class="activity-time">
                {{ new Date(activity.detected_at).toLocaleString('fa-IR') }}
              </div>
            </div>

            <div class="activity-description">
              <p>{{ activity.description }}</p>
            </div>

            <div class="activity-details" v-if="activity.transaction">
              <h5>Transaction Details</h5>
              <p><strong>Amount:</strong> {{ formatCurrency(activity.transaction.amount) }}</p>
              <p><strong>Type:</strong> {{ activity.transaction.type }}</p>
              <p><strong>User ID:</strong> {{ activity.transaction.user_id }}</p>
              <p><strong>Time:</strong> {{ new Date(activity.transaction.created_at).toLocaleString('fa-IR') }}</p>
            </div>

            <div class="risk-factors" v-if="activity.risk_factors">
              <h5>Risk Factors</h5>
              <div class="risk-tags">
                <span 
                  v-for="factor in activity.risk_factors" 
                  :key="factor"
                  class="risk-tag"
                >
                  {{ factor.replace(/_/g, ' ') }}
                </span>
              </div>
            </div>

            <div class="activity-actions" v-if="activity.status === 'pending'">
              <button 
                @click="updateActivityStatus(activity.id, 'resolved', 'Verified as legitimate')"
                :disabled="updating[activity.id]"
                class="btn btn-success"
              >
                Mark Resolved
              </button>
              
              <button 
                @click="updateActivityStatus(activity.id, 'false_positive', 'Confirmed as false positive')"
                :disabled="updating[activity.id]"
                class="btn btn-warning"
              >
                False Positive
              </button>
              
              <button 
                @click="updateActivityStatus(activity.id, 'escalated', 'Requires further investigation')"
                :disabled="updating[activity.id]"
                class="btn btn-danger"
              >
                Escalate
              </button>
            </div>

            <div v-else class="activity-status">
              <span :class="'status-' + activity.status">
                Status: {{ activity.status.replace(/_/g, ' ').toUpperCase() }}
              </span>
              <p v-if="activity.resolution_notes" class="resolution-notes">
                <small>{{ activity.resolution_notes }}</small>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  `
};
```

### 7.3 Wallet Deposit Component

```javascript
// React component example
const WalletDeposit = ({ walletId, onSuccess }) => {
  const [amount, setAmount] = useState('');
  const [description, setDescription] = useState('');
  const [loading, setLoading] = useState(false);

  const handleDeposit = async () => {
    setLoading(true);
    try {
      const response = await api.post(`/wallets/${walletId}/deposit`, {
        amount: parseInt(amount),
        description,
        metadata: {
          timestamp: new Date().toISOString(),
          admin_action: true
        }
      });
      
      onSuccess(response.data.data);
      setAmount('');
      setDescription('');
    } catch (error) {
      console.error('Deposit failed:', error.response.data);
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleDeposit}>
      <input 
        type="number" 
        value={amount}
        onChange={(e) => setAmount(e.target.value)}
        placeholder="Amount"
        required 
      />
      <input 
        type="text"
        value={description} 
        onChange={(e) => setDescription(e.target.value)}
        placeholder="Description"
      />
      <button type="submit" disabled={loading}>
        {loading ? 'Processing...' : 'Deposit'}
      </button>
    </form>
  );
};
```

### 7.4 Bulk Campaign Allocation

```javascript
// Vue.js component example
const BulkCampaignAllocation = {
  data() {
    return {
      selectedUsers: [],
      campaignId: null,
      triggerType: 'manual',
      reason: '',
      processing: false,
      results: null
    }
  },
  methods: {
    async triggerBulkAllocation() {
      this.processing = true;
      try {
        const response = await this.$http.post(
          `/wallet-campaigns/${this.campaignId}/bulk-trigger-allocation`,
          {
            user_ids: this.selectedUsers,
            trigger_type: this.triggerType,
            reason: this.reason,
            metadata: {
              batch_timestamp: new Date().toISOString(),
              admin_id: this.$auth.user.id
            }
          }
        );
        
        this.results = response.data.data;
        this.$emit('allocation-completed', this.results);
      } catch (error) {
        this.$toast.error('Bulk allocation failed');
        console.error(error.response.data);
      } finally {
        this.processing = false;
      }
    }
  }
};
```

### 7.5 Real-time Balance Updates

```javascript
// WebSocket integration for live updates
const WalletBalance = ({ userId }) => {
  const [balance, setBalance] = useState({ regular: 0, gift: 0 });

  useEffect(() => {
    // Listen for wallet updates
    window.Echo.private(`user.${userId}`)
      .listen('WalletBalanceUpdated', (e) => {
        setBalance({
          regular: e.balance,
          gift: e.gift_balance
        });
      });

    return () => {
      window.Echo.leave(`user.${userId}`);
    };
  }, [userId]);

  return (
    <div className="wallet-balance">
      <div className="regular-balance">
        Balance: {formatCurrency(balance.regular)}
      </div>
      <div className="gift-balance">
        Gift Credit: {formatCurrency(balance.gift)}
      </div>
    </div>
  );
};
```

---

## 8. Best Practices

### 8.1 Performance Optimization
- Use pagination for large datasets
- Implement proper caching for campaign lists
- Debounce user search inputs
- Show loading states during API calls

### 8.2 User Experience
- Provide clear feedback for all operations
- Show transaction confirmations
- Display balance changes immediately
- Handle partial failures gracefully in bulk operations

### 8.3 Error Handling
- Implement retry mechanisms for failed requests
- Show user-friendly error messages
- Log errors for debugging
- Provide fallback UI states

### 8.4 Security
- Validate all inputs on frontend
- Use HTTPS for all API calls
- Implement proper session management
- Store sensitive data securely

---

## 9. Testing Recommendations

### 9.1 Unit Tests
- Test wallet calculation logic
- Validate form inputs
- Test error handling

### 9.2 Integration Tests
- Test API endpoints
- Verify response formats
- Test authentication flows

### 9.3 E2E Tests
- Test complete wallet workflows
- Verify bulk operations
- Test error scenarios

This comprehensive guide should provide frontend developers with all necessary information to implement the wallet system effectively. For additional support or questions, please refer to the backend documentation or contact the development team.
