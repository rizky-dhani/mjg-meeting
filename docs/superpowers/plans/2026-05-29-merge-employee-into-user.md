# Merge Employee into User Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Merge the `employees` table into `users` and delete the `Employee` model, simplifying the data model and admin UX.

**Architecture:** Add employee fields (`employee_number`, `department_id`, `position`, `initials`, `phone`) directly to `users` table. Update the existing `UserResource` form/table to include these fields. Remove the separate `EmployeeResource` entirely. Update `Department` relationship from `employees()` → `users()`.

**Tech Stack:** Laravel 13, Filament, MySQL

---

### Task 1: Create migration

**Files:**
- Create: `database/migrations/2026_05_29_000001_merge_employee_fields_into_users_table.php`

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_number', 50)->unique()->after('email');
            $table->foreignId('department_id')->constrained()->after('employee_number');
            $table->string('position')->after('department_id');
            $table->string('initials', 10)->after('position');
            $table->string('phone', 50)->nullable()->after('initials');
        });

        Schema::dropIfExists('employees');
    }

    public function down(): void
    {
        // Re-create employees table
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('employee_number', 50)->unique();
            $table->foreignId('department_id')->constrained();
            $table->string('position');
            $table->string('initials', 10);
            $table->string('phone', 50)->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['employee_number', 'position', 'initials', 'phone']);
        });
    }
};
```

- [ ] **Step 2: Run the migration to verify**

Run: `php artisan migrate`
Expected: Output shows the new migration running successfully.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_05_29_000001_merge_employee_fields_into_users_table.php
git commit -m "feat: add employee fields to users table, drop employees table"
```

---

### Task 2: Update User model

**Files:**
- Modify: `app/Models/User.php`

- [ ] **Step 1: Update User model fields and relationships**

Apply these changes:
- Add `employee_number`, `department_id`, `position`, `initials`, `phone` to the `#[Fillable]` attribute
- Add `department(): BelongsTo` relationship
- Remove `employee(): HasOne` relationship
- Remove `use Illuminate\Database\Eloquent\Relations\HasOne;` import if no longer needed (check if `attendance()` uses it)

```php
<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'employee_number', 'department_id', 'position', 'initials', 'phone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
```

- [ ] **Step 2: Verify syntax**

Run: `php -l app/Models/User.php`
Expected: No syntax errors detected.

- [ ] **Step 3: Commit**

```bash
git add app/Models/User.php
git commit -m "feat: update User model with employee fields and department relationship"
```

---

### Task 3: Update Department model

**Files:**
- Modify: `app/Models/Department.php`

- [ ] **Step 1: Rename employees() to users() relationship**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    /** @use HasFactory<\Database\Factories\DepartmentFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }
}
```

- [ ] **Step 2: Verify syntax**

Run: `php -l app/Models/Department.php`
Expected: No syntax errors detected.

- [ ] **Step 3: Commit**

```bash
git add app/Models/Department.php
git commit -m "feat: rename Department::employees() to users()"
```

---

### Task 4: Delete Employee model, Factory, and Policy

**Files:**
- Delete: `app/Models/Employee.php`
- Delete: `database/factories/EmployeeFactory.php`
- Delete: `app/Policies/EmployeePolicy.php`

- [ ] **Step 1: Remove the files**

Run: `rm app/Models/Employee.php database/factories/EmployeeFactory.php app/Policies/EmployeePolicy.php`

- [ ] **Step 2: Verify files are gone**

Run: `ls app/Models/Employee.php 2>&1 || echo "File removed"`
Expected: `ls: cannot access 'app/Models/Employee.php': No such file or directory`

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "feat: remove Employee model, factory, and policy"
```

---

### Task 5: Add employee fields to User form and table

**Files:**
- Modify: `app/Filament/Resources/Users/Schemas/UserForm.php`
- Modify: `app/Filament/Resources/Users/Tables/UsersTable.php`

- [ ] **Step 1: Update UserForm to include employee fields**

Add a new section "Employee Details" with the employee_number, department_id, position, initials, and phone fields:

```php
<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->required()
                                    ->email()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                            ]),
                    ]),
                Section::make('Employee Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('employee_number')
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true),
                                Select::make('department_id')
                                    ->relationship('department', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                TextInput::make('position')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('initials')
                                    ->required()
                                    ->maxLength(10),
                                TextInput::make('phone')
                                    ->maxLength(50)
                                    ->tel(),
                            ]),
                    ]),
                Section::make('Roles & Permissions')
                    ->schema([
                        Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                    ]),
            ]);
    }
}
```

- [ ] **Step 2: Update UsersTable to show employee columns**

Add employee_number, department.name, position columns (initials and phone are less critical for listing, keep them toggleable):

```php
<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee_number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('department.name')
                    ->label(__('Department'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('position')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('roles.name')
                    ->label(__('Role'))
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
```

- [ ] **Step 3: Verify syntax**

Run: `php -l app/Filament/Resources/Users/Schemas/UserForm.php && php -l app/Filament/Resources/Users/Tables/UsersTable.php`
Expected: No syntax errors detected.

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Resources/Users/Schemas/UserForm.php app/Filament/Resources/Users/Tables/UsersTable.php
git commit -m "feat: add employee fields to User form and table"
```

---

### Task 6: Delete Employee Filament Resource and fix navigation references

**Files:**
- Delete: `app/Filament/Resources/Employees/` (entire directory)
- Modify: `app/Filament/Resources/Departments/DepartmentResource.php`
- Modify: `app/Filament/Resources/Departments/Tables/DepartmentsTable.php`
- Modify: `app/Filament/Resources/Positions/PositionResource.php`

- [ ] **Step 1: Remove the Employee resource directory**

Run: `rm -rf app/Filament/Resources/Employees/`

- [ ] **Step 2: Update DepartmentResource — remove `navigationParentItem`**

Remove line 23:
```php
    protected static ?string $navigationParentItem = 'Employees';
```

New content for lines 17-30:
```php
class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static string | UnitEnum | null $navigationGroup = 'System Management';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $recordTitleAttribute = 'name';
```

- [ ] **Step 3: Update DepartmentsTable — change `employees_count` to `users_count`**

Change:
```php
                TextColumn::make('employees_count')
                    ->counts('employees')
                    ->label('Employees'),
```

To:
```php
                TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Users'),
```

- [ ] **Step 4: Update PositionResource — remove `navigationParentItem`**

Remove line 25:
```php
    protected static ?string $navigationParentItem = 'Employees';
```

New content for lines 19-28:
```php
class PositionResource extends Resource
{
    protected static ?string $model = Position::class;

    protected static string | UnitEnum | null $navigationGroup = 'System Management';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $recordTitleAttribute = 'name';
```

- [ ] **Step 5: Verify syntax**

Run: `php -l app/Filament/Resources/Departments/DepartmentResource.php && php -l app/Filament/Resources/Departments/Tables/DepartmentsTable.php && php -l app/Filament/Resources/Positions/PositionResource.php`
Expected: No syntax errors detected.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: delete Employee resource, fix nav references for Departments and Positions"
```

---

### Task 7: Update BookingsTable department scoping

**Files:**
- Modify: `app/Filament/Resources/Bookings/Tables/BookingsTable.php`

- [ ] **Step 1: Simplify the scopeQuery to use direct department_id on users table**

Since `department_id` is now directly on the `users` table (and thus on the `User` model), we no longer need the `Employee` model. The `scopeQuery` changes to:

```php
    public static function scopeQuery(Builder $query): Builder
    {
        $user = auth()->user();

        $query->with('approvals');

        if ($user->hasRole('Admin') || $user->hasRole('Super Admin')) {
            return $query;
        }

        $departmentUserIds = User::where('department_id', $user->department_id)
            ->pluck('id');

        return $query->whereIn('user_id', $departmentUserIds);
    }
```

Also remove the `use App\Models\Employee;` import (line 7).

- [ ] **Step 2: Verify syntax**

Run: `php -l app/Filament/Resources/Bookings/Tables/BookingsTable.php`
Expected: No syntax errors detected.

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Resources/Bookings/Tables/BookingsTable.php
git commit -m "fix: simplify department scoping after Employee merge"
```
