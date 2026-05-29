# Merge Employee Model into User

## Problem

The `User` and `Employee` models represent the same entity — a person who accesses the system. Every user is a department staff member with employee-specific data (employee number, department, position, initials, phone). The current separation adds:

- **Admin UX friction**: Must create a User, then separately create an Employee record and link them.
- **Query complexity**: Every department-related query goes through `User → Employee → Department`, requiring an unnecessary join.
- **Conceptual disconnect**: The system has no concept of a "non-employee user" — the split is artificial.

## Solution

Merge the `employees` table into `users`. Drop the `Employee` model entirely. All employee fields become direct columns on `users`.

### Schema Changes

Add to `users` table:

| Column            | Type         | Constraints               |
| ----------------- | ------------ | ------------------------- |
| `employee_number` | `string(50)` | `unique`, `required`      |
| `department_id`   | `foreignId`  | `constrained`, `required` |
| `position`        | `string`     | `required`                |
| `initials`        | `string(10)` | `required`                |
| `phone`           | `string(50)` | `nullable`                |

Drop `employees` table (including its FK on `user_id` and FK to `departments`).

### Migration

One new migration: `merge_employee_fields_into_users_table.php`

### Model Changes

**`User` model** — add to fillable:
- `employee_number`, `department_id`, `position`, `initials`, `phone`

Add relationship:
- `department(): BelongsTo` → `Department`

Remove:
- `employee(): HasOne` relationship (no longer needed)

**`Department` model** — rename relationship:
- `employees(): HasMany` → `users(): HasMany` (points to `User` instead of `Employee`)

**Delete**:
- `app/Models/Employee.php`

### Filament Changes

**Delete entire resource**:
- `app/Filament/Resources/Employees/` (resource, form, table, all 3 pages)

**Add employee fields to User management** — create a new section in the User form/resource for these fields, visible only to Admin/Super Admin roles.

### Other Files

**`BookingsTable.php`** — the line we added earlier changes from:
```php
$departmentUserIds = User::where('department_id', $employee->department_id)->pluck('id');
```
to:
```php
$departmentUserIds = User::where('department_id', $user->department_id)->pluck('id');
```

And the Employee import + employee null check are removed.

**`EmployeePolicy.php`** — deleted. Permissions merge into whatever User authorization exists.

**`EmployeeFactory.php`** — either deleted (if not needed) or converted to a User factory state.

### Files Touched

| Action           | Files                                                                          |
| ---------------- | ------------------------------------------------------------------------------ |
| **New migration**  | `database/migrations/XXXX_XX_XX_XXXXXX_merge_employee_fields_into_users_table.php` |
| **Modify**         | `app/Models/User.php`                                                          |
| **Modify**         | `app/Models/Department.php`                                                    |
| **Modify**         | `app/Filament/Resources/Bookings/Tables/BookingsTable.php`                     |
| **Modify**         | `app/Filament/Resources/Employees/EmployeeResource.php` (→ UserResource or inline) |
| **Delete**         | `app/Models/Employee.php`                                                      |
| **Delete**         | `app/Filament/Resources/Employees/` (entire directory)                         |
| **Delete**         | `app/Policies/EmployeePolicy.php`                                              |
| **Delete**         | `database/factories/EmployeeFactory.php`                                       |

### Edge Cases

- **Existing data**: No production data, so no data migration needed.
- **Admin roles**: Super Admin and Admin roles also have departments and employee numbers — they're department staff too.
- **Auth**: The `users` table now has more columns but the auth guard (`web`, Eloquent) is unaffected.

## Rejected Alternative: Keep Separate

Keeping `Employee` as a separate model but adding accessors on `User` was considered. This would avoid structural change but wouldn't solve the admin UX friction or the join overhead. Since the app is in development and every user is an employee, the merge is cleaner.
