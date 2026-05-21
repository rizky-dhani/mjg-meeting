# Dynamic Approval System — Design Specification

> **Inspired by:** [ffhs/filament-package_ffhs_approvals](https://github.com/ffhs/filament-package_ffhs_approvals)
> **Version:** 1.0
> **Status:** Draft

## Overview

Build a generic, reusable Dynamic Approval system as a self-contained module
within the application (`App\Support\Approvals`). The system enables any
Eloquent model to be "approvable" with multiple configurable approval flows,
each supporting role-based, permission-based, user-based, and custom Closure
approval rules with threshold (`atLeast N`) requirements.

The first integration target is the **Booking** model, replacing its current
static `status`, `approved_by`, and `approved_at` fields with the dynamic
polymorphic approval system.

## Architecture

### Namespace Layout

```
app/Support/Approvals/
├── Approval/
│   ├── SimpleApprovalBy.php       # Concrete approval rule
│   └── SimpleApprovalFlow.php     # Concrete approval flow
├── ApprovalStatus/
│   └── BookingApprovalStatus.php  # App-specific status enum
├── Concerns/
│   └── HandlesApprovals.php       # Shared approval logic trait
├── Contracts/
│   ├── Approvable.php             # Model contract
│   ├── ApprovalBy.php             # Approval rule contract
│   ├── ApprovalFlow.php           # Flow contract
│   ├── Approver.php               # Marker interface for users
│   └── HasApprovalStatuses.php    # Enum contract
├── Enums/
│   └── ApprovalState.php          # Flow aggregate state enum
├── Filament/
│   └── Components/
│       └── ApprovalActions.php    # Infolist entry component
├── Models/
│   └── Approval.php               # Polymorphic approval record
└── Traits/
    └── HasApprovals.php           # Eloquent trait for models
```

### Core Concepts

#### 1. Approval Status Enum (`HasApprovalStatuses`)

A PHP 8.3 `string` backed enum that classifies each status value.

**Required interface:**

```php
interface HasApprovalStatuses extends BackedEnum
{
    /** @return static[] — statuses considered "approved" */
    public static function getApprovedStatuses(): array;

    /** @return static[] — statuses considered "denied" */
    public static function getDeniedStatuses(): array;

    /** @return static[] — statuses considered "pending" */
    public static function getPendingStatuses(): array;

    /** Human-readable label for a status case */
    public static function getCaseLabel(self $case): string;
}
```

**Example — BookingApprovalStatus:**

```php
enum BookingApprovalStatus: string implements HasApprovalStatuses
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public static function getApprovedStatuses(): array { return [self::Approved]; }
    public static function getDeniedStatuses(): array   { return [self::Rejected]; }
    public static function getPendingStatuses(): array  { return [self::Pending]; }
    public static function getCaseLabel(self $case): string { /* ... */ }
}
```

#### 2. Approval Flow (`ApprovalFlow`)

A named workflow within a model. Each flow has:
- A **category/name** (e.g., `"booking_approval"`)
- A set of **approval statuses** (from the enum)
- An ordered list of **approval-by rules**
- An optional **disabled** state

The aggregate state of a flow is computed by evaluating all its approval-by
rules. The possible aggregate states come from `ApprovalState`:

```php
enum ApprovalState: string
{
    case APPROVED = 'approved';   // All rules met their thresholds
    case DENIED   = 'denied';     // Any rule has a denied decision
    case PENDING  = 'pending';    // Some rules have pending decisions
    case OPEN     = 'open';       // No decisions recorded yet
}
```

**Flow state computation logic:**
1. For each `ApprovalBy` rule, check its approved/denied/pending status
2. If **any** rule → DENIED → **overall = DENIED**
3. If **any** rule → PENDING → **overall = PENDING**
4. If **any** rule → OPEN → **overall = OPEN**
5. Otherwise → **overall = APPROVED**

#### 3. Approval By Rule (`ApprovalBy`)

Defines **who** can approve for a given step in a flow.

**Capabilities:**
- `any()` — anyone (any authenticated user) can approve
- `role('name')` — user must have the Spatie role
- `orRole('name')` — alternative role (chained)
- `permission('name')` — user must have the Spatie permission
- `canApproveUsing(fn($approver, $approvable) => bool)` — custom Closure logic
- `atLeast(N)` — requires N distinct approvals in this group to be "reached"
- `label('text')` — display label for the UI group

**Approval-by decision logic:**
1. If `canApproveUsing` Closure is set, evaluate it
2. If `any()` is set, return true
3. Check roles/permissions via Spatie
4. For aggregate status: check `atLeast(N)` threshold against stored approvals

#### 4. Approval Record (`Approval` Model)

A single decision stored in the polymorphic `approvals` table:

| Column         | Type      | Purpose                                  |
|----------------|-----------|------------------------------------------|
| id             | bigint    | Primary key                              |
| key            | string    | Flow key (e.g., `booking_approval`)      |
| approvable_type| string    | Morphs: model class                      |
| approvable_id  | bigint    | Morphs: model ID                         |
| status         | string    | Enum value string (e.g., `approved`)     |
| approval_by    | string    | ApprovalBy rule name (e.g., `management`)|
| approver_type  | string    | Morphs: who approved                     |
| approver_id    | bigint    | Morphs: who approved                     |
| created_at     | timestamp |                                         |
| updated_at     | timestamp |                                         |
| deleted_at     | timestamp | Soft deletes for revocation              |

**Key indexes:**
- Composite: `(approvable_type, approvable_id, key, approval_by)`
- `(approver_type, approver_id)` for user's approval history

#### 5. HasApprovals Trait

Added to any Eloquent model (e.g., `Booking`) to provide:

```php
// Relationship
$this->approvals()        // MorphMany<Approval>

// Flow configuration (implemented by model)
$this->getApprovalFlows() // array<string, ApprovalFlow>
$this->getApprovalFlow($key)

// State queries
$this->approved()         // ApprovalState (aggregate across all flows)
$this->isApproved()       // bool
$this->isDenied()         // bool
$this->isPending()        // bool
$this->isOpen()           // bool

// Filtered queries
$this->getFilteredApprovalFlow($categories, $keys)
$this->approvalStatistics($categories, $keys)
```

### Database Migration

Single migration creating the `approvals` table:

```php
Schema::create('approvals', function (Blueprint $table) {
    $table->id();
    $table->string('key');
    $table->nullableMorphs('approvable');
    $table->string('status');
    $table->string('approval_by');
    $table->morphs('approver');
    $table->timestamps();
    $table->softDeletes();

    // Composite index for efficient flow queries
    $table->index(['approvable_type', 'approvable_id', 'key', 'approval_by']);
});
```

## Booking Integration

### Changes to Booking Model

1. Remove `status`, `approved_by`, `approved_at`, `qr_token`, `qr_code` columns
2. Implement `Approvable` contract
3. Use `HasApprovals` trait
4. Define `getApprovalFlows()` with the approval flow configuration
5. QR code generation moves to an observer/listener that fires when the booking
   transitions to fully approved

### Booking Flow Definition

```php
public function getApprovalFlows(): array
{
    return [
        'booking_approval' => SimpleApprovalFlow::make()
            ->approvalStatus(BookingApprovalStatus::cases())
            ->approvalBys([
                SimpleApprovalBy::make('requester')
                    ->any()
                    ->atLeast(1),
                SimpleApprovalBy::make('management')
                    ->role('Admin')
                    ->orRole('Super Admin')
                    ->atLeast(1),
            ]),
    ];
}
```

### Filament Integration

- **ListBookings table:** Query scoped to show relevant bookings. Status is
  computed dynamically via `Booking::approved()` → returns `ApprovalState` enum.
  The status column uses the enum's badge mapping.
- **ViewBooking page:** Replace the simple status TextEntry with the new
  `ApprovalActions` component that renders approval buttons per approval-by
  group.
- **Approval buttons:** Each button is visible only if the current user
  `canApprove()` for that rule. Disabled if already at that status. Supports
  confirmation dialogs.

### Observer / Event on Full Approval

When a booking reaches `APPROVED` aggregate state, trigger:
1. Generate QR token + QR code
2. Auto check-in the booker to attendance
3. Send `BookingApproved` notification

This can be done via a model observer on the `Approval` model or via the
`SimpleApprovalFlow` after state computation.

## Filament UI Component: ApprovalActions

A custom Filament Infolist entry component that renders approval controls.

**Layout per flow:**
```
┌─ Approval: booking_approval ───────────────────┐
│                                                 │
│  ── Requester ──                                 │
│  [✓ Pending]  [Approve]  [Reject]               │
│                                                 │
│  ── Management ──                                │
│  [✓ Approved]  [Approve]  [Reject]              │
│                                                 │
│  Overall: ● Partially Approved (1/2)              │
└─────────────────────────────────────────────────┘
```

Each status button (`Pending`/`Approve`/`Reject`) is an `Action` that:
- Calls `ApprovalSingleStateAction::changeApproval()`
- Creates/updates/deletes an `Approval` record
- Refreshes the record to show updated state
- Sends a Filament Notification
- Requires confirmation (configurable per status)

## Testing Strategy

- Unit tests for `SimpleApprovalFlow::approved()` state computation
- Unit tests for `SimpleApprovalBy::canApprove()` with roles, permissions, any
- Unit tests for `SimpleApprovalBy::approved()` with atLeast threshold
- Feature tests for the full Booking approval lifecycle:
  - User creates booking → requester auto-pending → flow not fully approved
  - Admin approves → flow transitions to APPROVED → QR code generated
  - Admin rejects → flow transitions to DENIED
  - Multiple approvers with atLeast(2) threshold
- Filament action tests for button visibility based on user roles

## Out of Scope

- Multi-step sequential approvals (stage 1 must finish before stage 2 starts)
  — all approval-by rules are evaluated in parallel
- Email notification workflows for pending approvals
- Approval dashboard widget showing all pending approvals across models
- Rejection reason tracking (can be added later via an extra column)
- Approval deadline/timeout logic
