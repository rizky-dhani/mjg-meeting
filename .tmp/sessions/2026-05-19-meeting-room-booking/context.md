# Task Context: Meeting Room Booking & QR Attendance System

Session ID: 2026-05-19-meeting-room-booking
Created: 2026-05-19T00:00:00Z
Status: in_progress

## Current Request
Implement a complete meeting room booking system with admin approval workflow and QR-code-based attendance check-in, following the plan at `docs/superpowers/plans/2026-05-18-meeting-room-booking-plan.md`.

## Context Files (Standards to Follow)
- `.opencode/context/core/standards/code-quality.md`
- `.opencode/context/core/standards/test-coverage.md`
- `.opencode/context/core/standards/security-patterns.md`
- `.opencode/context/core/workflows/component-planning.md`

## External Docs Fetched
- None needed — all frameworks have local context coverage via skills and local files.

## Components
1. **Database Layer** — 6 migrations (locations, departments, rooms, employees, bookings, attendance)
2. **Models** — 6 Eloquent models + User updates
3. **Auth/RBAC** — spatie/laravel-permissions with 3 roles (Super Admin, Admin, User)
4. **Filament Admin** — 6 resources (Locations, Departments, Employees, Rooms, Bookings, Attendance)
5. **QR Check-in** — Livewire component for attendance via QR URL
6. **Notifications** — Email on booking approve/reject with embedded QR
7. **Factories/Seeders** — Test data factories and demo data seeder
8. **Tests** — Pest feature tests for booking creation, approval, and attendance check-in

## Constraints
- Laravel 13.9, Filament 5.6, Livewire 4.3, TailwindCSS 4, Pest 4
- Must use spatie/laravel-permissions for RBAC
- QR generation via milon/barcode
- Commit after every completed task

## Exit Criteria
- [ ] All 14 tasks implemented and verified
- [ ] All Pest tests passing
- [ ] Database migrations runnable and seedable
- [ ] Filament admin panel accessible with role-based views
- [ ] QR attendance check-in flow working
- [ ] Email notifications sending
