# Research Documentation Index

Comprehensive research on Laravel 12 best practices for JeduShop API development.

**Date**: June 2, 2026 | **Coverage**: Laravel 12.x, Spatie Data v4, Pest 4, State Machines

---

## 📚 Documents

### 1. **RESEARCH_SUMMARY.md** ⭐ START HERE
Quick reference guide (3 min read)
- Key takeaways for each topic
- Example workflow
- Related files in JeduShop

### 2. **RESEARCH_BEST_PRACTICES.md** 📖 DETAILED GUIDE
Comprehensive research with code examples (30 min read)
- Section 1: Spatie Data Package (nested objects, validation, Scribe docs, collections)
- Section 2: Job Dispatching (conditional dispatch, context passing, error handling)
- Section 3: State Machines (validating transitions, business logic, preventing invalid changes)
- Section 4: PEST Testing (API endpoints, job dispatch, policies, datasets)
- Section 5: JeduShop Architecture patterns

### 3. **IMPLEMENTATION_CHECKLISTS.md** ✅ TASK-FOCUSED
Step-by-step checklists for common tasks (15 min reference)
- Create API endpoint
- Implement conditional job dispatch
- Implement state machine
- Test job dispatches
- Test API endpoints with auth
- Use datasets in PEST
- Pass context to jobs
- Create nested Data with Scribe
- Pre-commit checklist
- Common mistakes to avoid

### 4. **RESEARCH_INDEX.md** (this file)
Navigation guide

---

## 🎯 Quick Navigation by Task

### I need to...

#### **Build API Endpoints**
1. Read: RESEARCH_SUMMARY.md → Spatie Data section
2. Code: Follow IMPLEMENTATION_CHECKLISTS.md → "Create API Endpoint"
3. Reference: RESEARCH_BEST_PRACTICES.md → Section 1 for full examples

#### **Implement Job Dispatch**
1. Read: RESEARCH_SUMMARY.md → Job Dispatching section
2. Code: Follow IMPLEMENTATION_CHECKLISTS.md → "Implement Conditional Job Dispatch"
3. Reference: RESEARCH_BEST_PRACTICES.md → Section 2 for error handling details

#### **Handle Status Transitions**
1. Read: RESEARCH_SUMMARY.md → State Machine section
2. Code: Follow IMPLEMENTATION_CHECKLISTS.md → "Implement State Machine"
3. Reference: RESEARCH_BEST_PRACTICES.md → Section 3 for transition logic

#### **Write Tests**
1. Read: RESEARCH_SUMMARY.md → PEST Testing section
2. Code: Follow IMPLEMENTATION_CHECKLISTS.md → "Test API Endpoints" or "Test Job Dispatches"
3. Reference: RESEARCH_BEST_PRACTICES.md → Section 4 for comprehensive examples

#### **Use Datasets in Tests**
1. Read: IMPLEMENTATION_CHECKLISTS.md → "Use Datasets in PEST"
2. Reference: RESEARCH_BEST_PRACTICES.md → Section 4.4 for examples

#### **Pass Context to Jobs**
1. Read: IMPLEMENTATION_CHECKLISTS.md → "Context Passing to Jobs"
2. Reference: RESEARCH_BEST_PRACTICES.md → Section 2.2 for full explanation

---

## 📋 Topic Index

### Data Classes & Validation
- **Overview**: RESEARCH_SUMMARY.md → Spatie Data
- **Nested Objects**: RESEARCH_BEST_PRACTICES.md → 1.1
- **Validation Rules**: RESEARCH_BEST_PRACTICES.md → 1.2
- **Scribe Integration**: RESEARCH_BEST_PRACTICES.md → 1.3
- **Collections**: RESEARCH_BEST_PRACTICES.md → 1.4
- **Checklist**: IMPLEMENTATION_CHECKLISTS.md → Create API Endpoint

### Job Dispatching
- **Overview**: RESEARCH_SUMMARY.md → Job Dispatching
- **Conditional Dispatch**: RESEARCH_BEST_PRACTICES.md → 2.1
- **Context Passing**: RESEARCH_BEST_PRACTICES.md → 2.2
- **Error Handling**: RESEARCH_BEST_PRACTICES.md → 2.3
- **Checklists**: IMPLEMENTATION_CHECKLISTS.md → Sections on job dispatch + context

### State Machines
- **Overview**: RESEARCH_SUMMARY.md → State Machines
- **Validating Transitions**: RESEARCH_BEST_PRACTICES.md → 3.1
- **Business Logic**: RESEARCH_BEST_PRACTICES.md → 3.2
- **Preventing Invalid**: RESEARCH_BEST_PRACTICES.md → 3.3
- **Checklist**: IMPLEMENTATION_CHECKLISTS.md → Implement State Machine

### PEST Testing
- **Overview**: RESEARCH_SUMMARY.md → PEST Testing
- **API Testing**: RESEARCH_BEST_PRACTICES.md → 4.1
- **Job Testing**: RESEARCH_BEST_PRACTICES.md → 4.2
- **Policy Testing**: RESEARCH_BEST_PRACTICES.md → 4.3
- **Datasets**: RESEARCH_BEST_PRACTICES.md → 4.4
- **Checklists**: IMPLEMENTATION_CHECKLISTS.md → Multiple test sections

### Architecture
- **Overview**: RESEARCH_BEST_PRACTICES.md → Section 5
- **Controllers**: Controllers (thin, delegate)
- **Actions**: Business logic encapsulation
- **Data**: Request/response DTOs
- **Jobs**: Async work
- **Enums**: Status states

---

## 🔗 Real Examples from JeduShop

### Data Classes
- **Location**: `app/Data/Admin/Payment/`
- **Example Files**:
  - `PaymentCreateData.php` - Request with nested BankTransferPaymentData
  - `PaymentUpdateData.php` - Update DTO
  - `BankTransferPaymentData.php` - Nested object

### Jobs
- **Location**: `app/Jobs/Provisioning/`
- **Example Files**:
  - `ProvisionBbbEnrollmentJob.php` - Full example with backoff, failed()
  - `UpdateProductPricingJob.php` - Conditional dispatch example
  - `ProvisionMoodleEnrollmentJob.php` - Multiple provider example

### Job Tests
- **Location**: `tests/Integration/Jobs/Provisioning/`
- **Example Files**:
  - `ProvisionBbbEnrollmentJobTest.php` - Comprehensive job testing with datasets

### Enums
- **Location**: `app/Enums/`
- **Example Files**:
  - `EnrollmentStatusEnum.php` - State machine example with grouping
  - `PaymentStatusEnum.php` - Simple enum
  - `PaymentMethodEnum.php` - Method enum

### Auth Trait
- **Location**: `tests/Support/Traits/AuthTestTrait.php`
- **Used in**: All feature tests

### Controllers
- **Location**: `app/Http/Controllers/Api/Admin/`
- **Example**: `Review/ApproveReviewController.php` - Thin controller pattern

---

## 📊 Reading Time Guide

| Document | Time | Best For |
|----------|------|----------|
| RESEARCH_SUMMARY.md | 3 min | Quick overview before coding |
| RESEARCH_BEST_PRACTICES.md | 30 min | Deep understanding of patterns |
| IMPLEMENTATION_CHECKLISTS.md | 5-10 min | During implementation (as reference) |
| Specific section in BEST_PRACTICES | 5 min | Lookup while coding |

**Recommended Flow**:
1. Start with SUMMARY (3 min) to understand scope
2. Review relevant CHECKLISTS (5 min) for your task
3. Reference BEST_PRACTICES (5-10 min) for specific patterns
4. Code with CHECKLISTS as checklist

---

## 🔑 Key Concepts

### Single Source of Truth
- **Data Class**: Validates input + Documents API params
- **Action**: Encapsulates business logic
- **Enum**: Defines valid states

### Explicit Over Implicit
- Nested field validation in rules() AND bodyParameters()
- Transition validation in canTransition() before execute()
- Guard clauses for early return (not errors)

### Testing Pyramid
- Unit tests for business logic (Actions, Services)
- Feature tests for API endpoints (with Auth, Bus::fake)
- Integration tests for workflows (provisioning jobs)

### Context Preservation
- Request metadata automatically passed to jobs
- No explicit parameter passing needed
- Dehydrate/hydrate callbacks for transformation

---

## ⚠️ Critical Reminders

1. **Scribe Documentation**: ALWAYS add `@codeCoverageIgnore` to `bodyParameters()`
2. **Nested Fields**: MUST explicitly define each `.*.` sub-field in bodyParameters()
3. **Job Testing**: ALWAYS use `Bus::fake()` in beforeEach() to prevent actual execution
4. **State Transitions**: ALWAYS validate canTransition() before executing
5. **Data Classes**: Use ONLY Data DTOs for APIs (no Form Requests)

---

## 📞 When in Doubt

- **"How do I...?"** → Check IMPLEMENTATION_CHECKLISTS.md
- **"Why do we do it this way?"** → Check RESEARCH_BEST_PRACTICES.md
- **"What's the key point?"** → Check RESEARCH_SUMMARY.md
- **"Show me real code"** → Reference JeduShop examples (see section above)

---

**Last Updated**: June 2, 2026
**Versions**: Laravel 12.x, Spatie Data v4, Pest 4.x, PHP 8.4
**Status**: Research Complete ✅

