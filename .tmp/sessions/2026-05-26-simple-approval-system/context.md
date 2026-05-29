# Task Context: Simple Dynamic Approval System

Session ID: 2026-05-26-simple-approval-system
Created: 2026-05-26T00:00:00Z
Status: in_progress

## Current Request
Replace the existing complex approval system (`app/Support/Approvals/`) with a simple DB-driven Approval Flow management system. Create Approval Flows with role-based sequential Steps. Add `model_type` binding for clarity. Build a Filament resource under "System Management" nav group.

## Context Files (Standards to Follow)
- /home/da1shiq/.opencode/context/core/standards/code-quality.md
- /home/da1shiq/.opencode/context/core/standards/test-coverage.md

## Reference Files (Source Material to Look At)
- app/Support/Approvals/Contracts/ (all contracts)
- app/Support/Approvals/Approval/SimpleApprovalFlow.php
- app/Support/Approvals/Approval/SimpleApprovalBy.php
- app/Support/Approvals/Enums/ApprovalState.php
- app/Support/Approvals/ApprovalStatus/BookingApprovalStatus.php
- app/Support/Approvals/Traits/HasApprovals.php
- app/Support/Approvals/Concerns/HandlesApprovals.php
- app/Support/Approvals/Models/Approval.php
- app/Support/Approvals/Filament/Components/ApprovalActions.php
- app/Models/Booking.php
- app/Models/User.php
- app/Filament/Resources/Bookings/BookingResource.php
- app/Filament/Resources/Bookings/Pages/ViewBooking.php
- app/Filament/Resources/Bookings/Tables/BookingsTable.php
- app/Filament/Resources/Bookings/Schemas/BookingForm.php
- app/Providers/Filament/DashboardPanelProvider.php
- app/Filament/Resources/Roles/RoleResource.php
- database/migrations/2026_05_21_083431_create_approvals_table.php
- docs/superpowers/specs/2026-05-22-approval-flow-management-design.md
- app/Notifications/BookingApproved.php
- app/Notifications/BookingRejected.php
- tests/

## External Docs Fetched
Filament v5 navigation configuration docs (navigation groups, icons, resource setup)

## Components
1. **Migrations**: approval_flows + approval_flow_steps tables
2. **Models**: ApprovalFlow (with steps relationship), ApprovalFlowStep
3. **Filament Resource**: ApprovalFlows with List/Create/Edit pages, step repeater
4. **Core Logic**: Simple approval evaluation (how flows interact with the approvals table)
5. **Booking Update**: Remove old system, add new approval methods
6. **Filament Booking Update**: Update BookingsTable, ViewBooking for new system
7. **Cleanup**: Remove old Support/Approvals code
8. **Seeding**: Default Booking Approval flow

## Constraints
- Steps are sequential (order matters), future steps hidden until current is approved
- model_type binds flow to specific model class
- Under "System Management" navigation group
- Uses existing `approvals` table for storing approval records
- Roles are Spatie roles
- Keep Approval model at App\Support\Approvals\Models\Approval (polymorphic record)
- Follow existing Filament patterns (Resource/Pages/Tables/Schemas structure)
- Remove old code but keep the `approvals` migration and Approval model

## Exit Criteria
- [ ] Migrations run successfully
- [ ] ApprovalFlows Filament resource works (CRUD with steps)
- [ ] Booking model uses new system
- [ ] Booking Filament pages work with new system
- [ ] Old Support/Approvals code removed (except Approval model + migration)
- [ ] Default flow seeded
- [ ] Tests pass
