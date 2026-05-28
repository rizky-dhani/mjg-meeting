# Approval Flow Management — Design Specification

## Overview

Add database-driven ApprovalFlow management to the Dynamic Approval system. Replace hardcoded `getApprovalFlows()` in models with DB-backed flow definitions. Add Filament resources for managing approval records and flow configurations.

## Tables

### approval_flows

| Column       | Type    | Purpose                                              |
| ------------ | ------- | ---------------------------------------------------- |
| id           | bigint  | PK                                                   |
| key          | string  | unique flow identifier (e.g., `booking_approval`)      |
| label        | string  | display name (e.g., "Booking Approval")              |
| model_type   | string  | target model class (e.g., `App\Models\Booking`)       |
| status_enum  | string  | enum class for status categorization                 |
| is_disabled  | boolean | whether flow is active                               |
| approval_bys | json    | array of rule configs                                |
| timestamps   |         |                                                      |

### approval_bys JSON Structure

```json
[
  {
    "name": "requester",
    "label": "Requester",
    "is_any": true,
    "roles": [],
    "permission": null,
    "at_least": 1
  },
  {
    "name": "management",
    "label": "Management",
    "is_any": false,
    "roles": ["Admin", "Super Admin"],
    "permission": null,
    "at_least": 1
  }
]
```

### Existing approvals Table (unchanged)

Polymorphic approval records — no changes needed.

## Models

### App\Models\ApprovalFlow

Eloquent model mapped to `approval_flows` table. Has a `build()` method that reads the `approval_bys` JSON and constructs a `SimpleApprovalFlow` with `SimpleApprovalBy` rules.

### App\Support\Approvals\Models\Approval (existing)

No changes.

## Trait Changes

### HasApprovals trait

Replace the abstract `getApprovalFlows()` method with a concrete implementation that loads from DB:

```php
public function getApprovalFlows(): array
{
    return \App\Models\ApprovalFlow::where('model_type', static::class)
        ->get()
        ->map(fn($flow) => $flow->build())
        ->keyBy(fn($flow) => $flow->getKey())
        ->all();
}
```

The `getApprovalFlow(string $key)` method (already in the trait) stays the same — it looks up by key in the flows array.

## Model Changes

### Booking model

Remove hardcoded `getApprovalFlows()` method — the trait now loads from DB.

## Filament Resources

Both under **System Management** navigation group.

### ApprovalFlows Resource

| Page   | Purpose                                         |
| ------ | ----------------------------------------------- |
| List   | Table: key, label, model_type, is_disabled badge |
| Create | Form: key, label, model_type, status_enum, is_disabled, repeatable approval_bys |
| Edit   | Same as Create                                   |

**approval_bys repeatable fields:**
- name (text, required)
- label (text)
- is_any (toggle)
- roles (tags/checkboxes — Spatie roles)
- permission (select — Spatie permissions)
- at_least (number, default 1)

### Approvals Resource

| Page   | Purpose                                                  |
| ------ | -------------------------------------------------------- |
| List   | Table: key, approvable, status badge, approval_by, approver, dates |
| Create | Form: key select, approvable morph selects, status select, approval_by, approver morph selects |
| Edit   | Same as Create                                           |

## Migration Steps

1. Create `approval_flows` table
2. Seed default flows: migrate the Booking hardcoded flow into the DB
3. Update Booking model and HasApprovals trait

## Out of Scope

- Reordering approval-bys within a flow (sequential stages)
- Email notifications for pending approvals
- Dashboard widget for pending approvals
