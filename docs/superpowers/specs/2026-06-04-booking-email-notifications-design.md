# Booking Email Notifications Design

**Date:** 2026-06-04
**Status:** Draft
**Author:** Development Agent
**Session:** The user asked to create email templates for Booking creation and approval, sent to the creator and current eligible approver.

## Use Case Overview

When a meeting room booking is created and goes through a multi-step approval process, the system sends targeted email notifications to keep both the booking creator and the approvers informed at each stage.

### Characters in the Flow

- **Booker** — The person who created the booking (`Booking.booker_id`).
- **Eligible Approver** — The first user who can act on the current approval step, determined by role + scope matching.
- **User (Representee)** — The person the booking is for (`Booking.user_id`). In the current system, this is usually the same as the booker but can differ (e.g., admin books for someone else).

> **Duplicate prevention**: When `booker_id === user_id`, notifications are sent only once to avoid duplicate emails.

### Approval Flow Example

A company has a 3-step approval chain:
1. **Step 1**: `Head` role (scope: department) — Department Head
2. **Step 2**: `Director` role (scope: all)
3. **Step 3**: `VP` role (scope: all)

### Email Sequence (Scenario)

1. **Rina** (booker, also the user/attendee) creates a booking → She gets **BookingCreated**. **Budi** (eligible Head) gets **ApprovalRequested**.
2. **Budi** approves Step 1 → **Rina** gets **BookingStepApproved**. **Sari** (eligible Director) gets **ApprovalRequested**.
3. **Sari** approves Step 2 → **Rina** gets **BookingStepApproved**. **Dimas** (eligible VP) gets **ApprovalRequested**.
4. **Dimas** approves Step 3 (final) → **Rina** gets **BookingApproved** (existing) once (since she is both booker and user, the duplicate prevention prevents double-sending).
5. If anyone rejects → **Rina** gets **BookingRejected** (existing) once, with rejection reason.

---

## Design

### Architecture

Three new notification classes, all using `Illuminate\Notifications\Messages\MailMessage` (same pattern as existing `BookingApproved` and `BookingRejected`). No database changes, no new config, no Blade templates.

```
app/Notifications/
├── BookingCreated.php       (NEW)  → sent to booker on creation
├── ApprovalRequested.php    (NEW)  → sent to eligible approver
├── BookingStepApproved.php  (NEW)  → sent to booker on step progress
├── BookingApproved.php      (EXISTING) → sent to user + booker on full approval
└── BookingRejected.php      (EXISTING) → sent to user + booker on rejection
```

### Helper: Get First Eligible Approver

A utility method on `ApprovalFlowStep` to find the first `User` who can approve a given step. Currently `ViewBooking` has a private `getEligibleApproverName()` that returns just a name string. This will be extracted into a public method on `ApprovalFlowStep` that returns the full `User` model.

```php
// On ApprovalFlowStep model
public function getFirstEligibleUser(Booking $booking): ?User
{
    $query = User::role($this->role->name);

    if ($this->scope === 'department' && $this->department_id !== null) {
        $query->where('department_id', $this->department_id);
    }

    if ($this->scope === 'requester') {
        $requesterDeptId = $booking->user?->department_id;
        if ($requesterDeptId === null) {
            return null;
        }
        $query->where('department_id', $requesterDeptId);
    }

    return $query->first();
}
```

---

### Notification 1: `BookingCreated`

**Trigger:** `CreateBooking::afterCreate()`

**Sent to:** `$booking->booker`

**Subject:** `"Booking Created: {title}"`

**Body:**
```
Hello {booker_name},

Your booking has been successfully created.

Title: {title}
Room: {room_name} ({location})
Date: {date_formatted}
Time: {start} - {end}
Description: {description}

Your booking is awaiting approval from {approver_name} ({approver_role}).

[View Booking] → /dashboard/bookings/{id}
```

---

### Notification 2: `ApprovalRequested`

**Trigger:**
- After booking creation (for the first step's eligible approver)
- After each intermediate step approval (for the next step's eligible approver)

**Sent to:** The first eligible User for the current actionable step

**Subject:** `"Approval Requested: {title}"`

**Body:**
```
Hello {approver_name},

{booker_name} has requested a meeting room and needs your approval.

Title: {title}
Room: {room_name} ({location})
Date: {date_formatted}
Time: {start} - {end}

Step: {step_number} of {total_steps}
Your role: {approver_role}

Please review the booking details and approve or reject this request.

[Review Booking] → /dashboard/bookings/{id}
```

---

### Notification 3: `BookingStepApproved`

**Trigger:** `processApproval()` when a step is approved but it is NOT the final step.

**Sent to:** `$booking->booker`

**Subject:** `"Booking Update: {title} — {approver_name} has given approval"`

**Body:**
```
Hello {booker_name},

{approver_name} from the {approver_role} team has reviewed and approved your booking for "{title}".

Here's a summary of your booking:
  Room: {room_name} ({location})
  Date: {date_formatted}
  Time: {start} - {end}

Your booking still needs approval from {next_name} ({next_role}) before it's fully confirmed. You'll receive another update once the next review is complete.

[View Booking] → /dashboard/bookings/{id}
```

---

### Modified Behavior: Existing Notifications

#### Full Approval

`processApproval()` already sends `BookingApproved` to `$record->user` when the booking is fully approved. This will be extended to also send to `$record->booker` (if they are different from `$record->user`).

#### Rejection

The Filament reject actions in both `BookingsTable` and `ViewBooking` already send `BookingRejected` to `$record->user`. This will be extended to also send to `$record->booker` (if they are different from `$record->user`).

---

### Files Modified

| File                                                                                     | Change                                                                                          |
| ---------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| `app/Models/ApprovalFlowStep.php`                                                        | Add `getFirstEligibleUser(Booking)` public method                                                |
| `app/Filament/Resources/Bookings/Pages/CreateBooking.php`                                | Add `afterCreate()` → send `BookingCreated` to booker + `ApprovalRequested` to eligible approver |
| `app/Filament/Resources/Bookings/Tables/BookingsTable.php`                               | `processApproval()`: when approved & not final → `BookingStepApproved` to booker + `ApprovalRequested` to next approver. When fully approved → `BookingApproved` also to booker. Reject action → `BookingRejected` also to booker |
| `app/Filament/Resources/Bookings/Pages/ViewBooking.php`                                  | Reject action → `BookingRejected` also to booker (when booker !== user)                           |

### Files Created

| File                                                   | Description                                |
| ------------------------------------------------------ | ------------------------------------------ |
| `app/Notifications/BookingCreated.php`                 | Notification for booker on creation        |
| `app/Notifications/ApprovalRequested.php`              | Notification for eligible approver         |
| `app/Notifications/BookingStepApproved.php`            | Notification for booker on step progress   |

---

### Non-Functional Considerations

- **No queuing**: Notifications are sent synchronously (consistent with existing `BookingApproved`/`BookingRejected`). No `ShouldQueue` interface added.
- **No new mail configs**: Uses existing `config/mail.php` settings (default `from` address).
- **No localization**: All strings use English (consistent with existing codebase).
- **Error handling**: Notification failures are non-blocking — if email sending fails, the booking creation/approval still succeeds. Laravel's `notify()` method catches exceptions by default but logs them.
