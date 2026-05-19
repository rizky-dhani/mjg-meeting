# Meeting Room Booking & QR Attendance System

**Date:** 2026-05-18
**Status:** Approved Design

## Overview

A meeting room booking system with after-meeting attendance tracking via QR codes. Built on Laravel 13.9 + Filament 5.6.3 + Livewire 4.3.

**Users:** Admins (manage rooms/bookings/attendance) + Registered users (request bookings, check in via QR)
**Rooms:** 6 rooms across multiple locations, capacity 10-20+
**Tech:** Laravel 13, Filament 5, Livewire 4, TailwindCSS 4, Pest 4

---

## Roles & Permissions (spatie/laravel-permissions)

| Role         | Description                                |
| ------------ | ------------------------------------------ |
| Super Admin  | Bypasses all permissions via `Gate::before` |
| Admin        | Manages rooms, approvals, attendance reports |
| User         | Requests bookings, views QR, checks in     |

---

## Data Model

```
locations ──1:N──> rooms ──1:N──> bookings ──1:N──> attendance
                                                    |
departments ──1:N──> employees ──1:1──> users ──────┘
```

### locations
| Field       | Type            | Notes                 |
| ----------- | --------------- | --------------------- |
| id          | bigIncrements   |                       |
| name        | string(255)     | e.g. "Head Office"    |
| address     | text (nullable) |                       |
| description | text (nullable) |                       |
| timestamps  |                 |                       |

### departments
| Field       | Type            | Notes                 |
| ----------- | --------------- | --------------------- |
| id          | bigIncrements   |                       |
| name        | string(255)     |                       |
| code        | string(50)      | Unique, e.g. "IT"     |
| description | text (nullable) |                       |
| timestamps  |                 |                       |

### employees
| Field           | Type              | Notes                         |
| --------------- | ----------------- | ----------------------------- |
| id              | bigIncrements     |                               |
| user_id         | FK → users        | Unique (one employee per user) |
| employee_number | string(50)        | Unique                        |
| department_id   | FK → departments  |                               |
| position        | string(255)       | e.g. "Software Engineer"      |
| initials        | string(10)        | e.g. "JD"                     |
| phone           | string(50)        | Nullable                      |
| timestamps      |                   |                               |

### rooms
| Field       | Type              | Notes                        |
| ----------- | ----------------- | ---------------------------- |
| id          | bigIncrements     |                              |
| location_id | FK → locations    |                              |
| name        | string(255)       | e.g. "Meeting Room A"        |
| capacity    | integer           | e.g. 15                      |
| description | text (nullable)   | Amenities, floor, etc.       |
| timestamps  |                   |                              |

### bookings
| Field       | Type              | Notes                                    |
| ----------- | ----------------- | ---------------------------------------- |
| id          | bigIncrements     |                                          |
| room_id     | FK → rooms        |                                          |
| user_id     | FK → users        | Who booked it                            |
| title       | string(255)       | Meeting title                            |
| description | text (nullable)   | Meeting agenda/notes                     |
| starts_at   | datetime          |                                          |
| ends_at     | datetime          |                                          |
| status      | enum/string       | `pending`, `approved`, `rejected`        |
| approved_by | FK → users (nullable) | Admin who approved                   |
| approved_at | datetime (nullable)   |                                     |
| qr_token    | string(64)        | Unique UUID, generated on approval       |
| qr_code     | string(255)       | Full attendance URL, stored on approval  |
| timestamps  |                   |                                          |

### attendance
| Field         | Type              | Notes                              |
| ------------- | ----------------- | ---------------------------------- |
| id            | bigIncrements     |                                    |
| booking_id    | FK → bookings     |                                    |
| user_id       | FK → users        |                                    |
| checked_in_at | timestamp         |                                    |
| timestamps    |                   | Unique constraint on (booking_id, user_id) |

---

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                   Filament Panel                         │
│                                                          │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌────────┐  │
│  │  Rooms   │  │ Bookings │  │Attendance│  │Employees│  │
│  │ Resource │  │ Resource │  │ Resource │  │ Resource│  │
│  └──────────┘  └──────────┘  └──────────┘  └────────┘  │
│       │             │              │              │      │
│       └─── CRUD ────┴── Approval ─┴─── View ─────┘      │
│                          │                               │
│                    On Approve:                           │
│                    - Generate qr_token (UUID)            │
│                    - Build qr_code URL                   │
│                    - Auto-check-in booker                │
│                    - Send email with QR                  │
└──────────────────────────┬──────────────────────────────┘
                           │ QR URL: /attendance/{qr_token}
                           ▼
┌─────────────────────────────────────────────────────────┐
│              Livewire Attendance Component               │
│                                                          │
│  Route: /attendance/{qr_token}  [auth, web]              │
│                                                          │
│  - Requires login (redirect to login if guest)           │
│  - Validates QR is not expired (end of meeting day)      │
│  - Shows meeting details (title, room, location, time)   │
│  - "Mark Attendance" button → creates attendance record  │
│  - Duplicate check-in prevention                        │
│  - Already checked-in message if re-scanning            │
└─────────────────────────────────────────────────────────┘
```

---

## User Flows

### Flow 1: Booking (User)
1. Login → Filament panel → Bookings → Create
2. Select room (shows location) → set date/time range → title → description
3. System validates no time conflict for room
4. Booking created with status `pending`
5. Admin notified (Filament database notification)

### Flow 2: Approving (Admin)
1. Admin sees pending bookings in table
2. Opens booking → Reviews request
3. Clicks **Approve** or **Reject**
4. On approve:
   - Generates UUID `qr_token`
   - Builds full `qr_code` URL: `https://{host}/attendance/{qr_token}`
   - Auto-creates attendance record for the booker (checked_in_at = now)
   - Sends email to booker with QR code embedded
   - Booking detail page now shows QR

### Flow 3: Attendance (User via QR)
1. User scans QR (from email or printed) → opens URL
2. URL requires auth — redirected to login if not authenticated
3. Check QR validity: current time <= 23:59:59 of meeting date
4. If valid: shows meeting details + "Mark Attendance" button
5. Click → attendance recorded → success message
6. If already checked in: "You've already checked in for this meeting"

### Flow 4: Attendance Overview (Admin)
1. Admin opens Booking → sees "Attendance" relation manager
2. Lists all checked-in users with timestamps

### Cancellation (User)
- User can cancel only `pending` bookings
- Admin can cancel/reject any booking at any time

---

## Filament Resources

### Rooms Resource (Admin only)
- **List table:** Name, Location, Capacity
- **Form:** Location (select), Name, Capacity, Description
- **Permission:** `Admin` role

### Bookings Resource (All authenticated users)
- **List table:** Title, Room (→Location), User, Date/Time, Status badge, Actions
- **Scoping:** Users see only their own bookings; Admins see all
- **Create form:** Room (select with location context), Date/Time pickers, Title, Description
- **View page:** Meeting detail + QR display (if approved) + Attendance relation manager
- **Actions (Admin):** Approve (with confirmation modal), Reject (with reason textarea)
- **Permissions:** `User` can create/view own; `Admin` can view all and approve/reject
- **Validation:** Double-booking prevention (no overlapping time for same room)

### Attendance Resource (Admin only, read-only)
- **List table:** Booking, User, Check-in time
- **Filters:** By booking, by user, by date range

### Employees Resource (Admin only) — Full CRUD
### Departments Resource (Admin only) — Full CRUD
### Locations Resource (Admin only) — Full CRUD

---

## QR Code Generation

- **Package:** `milon/barcode` (for QR code generation)
- **QR content:** The `qr_code` URL string stored in the booking record
- **Display:** Rendered in the Booking view page (Filament), embedded in email notifications
- **Validity:** QR URL expires at 23:59:59 on the day of the meeting
- **Size/format:** PNG suitable for printing (A4), and inline SVG for web display

---

## Notifications

| Event             | Channel         | Recipient  | Content                        |
| ----------------- | --------------- | ---------- | ------------------------------ |
| Booking submitted | Database (Filament) | Admins  | New booking request details    |
| Booking approved  | Email           | Booker     | QR code image + meeting info   |
| Booking rejected  | Email           | Booker     | Reason for rejection (optional) |

---

## Edge Cases & Constraints

1. **Double-booking prevention:** Validate no overlapping approved bookings for the same room before approving
2. **QR expiration:** Check `now() <= end of meeting day` on attendance page; show "QR expired" if past
3. **Already checked in:** Unique constraint on `(booking_id, user_id)` catches this at DB level
4. **Booking edit:** Admins can edit bookings post-approval (e.g., reschedule). If time changes, QR validity period updates accordingly
5. **Self-attendance (booker):** Auto-checked-in when booking is approved — no QR scan needed
6. **Printing QR:** Booking detail page includes a print-friendly button/styling

---

## Testing Strategy (Pest 4)

- **Model tests:** Factory definitions, relationships, scopes
- **Feature tests:** Booking creation, approval flow, QR generation, attendance check-in, expiration check
- **Filament tests:** Resource CRUD operations, action approval/rejection
- **Permission tests:** Role-based access for each resource and action

---

## Packages to Install

| Package                        | Purpose                    |
| ------------------------------ | -------------------------- |
| `milon/barcode`                | QR code generation         |
| `spatie/laravel-permissions`   | Role-based access control  |

---

## Implementation Order

1. Install packages & run migrations
2. Create models & relationships
3. Set up spatie/laravel-permissions (roles, seeder, Gate::before)
4. Create Filament resources (Locations → Departments → Employees → Rooms → Bookings → Attendance)
5. Implement QR generation on booking approval (action)
6. Build Livewire attendance component
7. Set up email notifications with QR
8. Write tests
