# Dynamic Approval System — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a generic, reusable Dynamic Approval system and integrate it with the Booking model.

**Architecture:** A self-contained `App\Support\Approvals` module with contracts, traits, concrete flows, and a Filament UI component. Bookings become "approvable" via a PHP enum and the `HasApprovals` trait. A polymorphic `approvals` table stores all decisions.

**Tech Stack:** Laravel 13, Filament 5, PHP 8.3, Spatie Permission v7, Pest 4

---

## File Structure

```
app/Support/Approvals/
├── Approval/
│   ├── SimpleApprovalBy.php          # Concrete approval rule
│   └── SimpleApprovalFlow.php        # Concrete approval flow
├── ApprovalStatus/
│   └── BookingApprovalStatus.php     # App-specific enum
├── Concerns/
│   └── HandlesApprovals.php          # Shared approval mutation logic
├── Contracts/
│   ├── Approvable.php                # Model contract
│   ├── ApprovalBy.php                # Approval rule contract
│   ├── ApprovalFlow.php              # Flow contract
│   ├── Approver.php                  # Marker interface
│   └── HasApprovalStatuses.php       # Enum contract
├── Enums/
│   └── ApprovalState.php             # Flow aggregate state
├── Filament/
│   └── Components/
│       └── ApprovalActions.php       # Infolist entry component
├── Models/
│   └── Approval.php                  # Polymorphic record
└── Traits/
    └── HasApprovals.php              # Eloquent trait

Modified files:
- app/Models/Booking.php
- app/Filament/Resources/Bookings/Tables/BookingsTable.php
- app/Filament/Resources/Bookings/Pages/ViewBooking.php
- app/Filament/Resources/Bookings/Schemas/BookingForm.php

Database migrations:
- Create approvals table
- Remove old booking approval columns

Test files:
- tests/Unit/Support/Approvals/ApprovalStateTest.php
- tests/Unit/Support/Approvals/SimpleApprovalFlowTest.php
- tests/Unit/Support/Approvals/SimpleApprovalByTest.php
- tests/Feature/Bookings/ApprovalLifecycleTest.php
```

---

### Task 1: ApprovalState Enum + HasApprovalStatuses + Approver Contracts

**Files:**
- Create: `app/Support/Approvals/Enums/ApprovalState.php`
- Create: `app/Support/Approvals/Contracts/HasApprovalStatuses.php`
- Create: `app/Support/Approvals/Contracts/Approver.php`
- Test: `tests/Unit/Support/Approvals/ApprovalStateTest.php`

- [ ] **Step 1: Create directories**

```bash
mkdir -p app/Support/Approvals/Enums
mkdir -p app/Support/Approvals/Contracts
mkdir -p tests/Unit/Support/Approvals
```

- [ ] **Step 2: Create ApprovalState enum**

```php
<?php

namespace App\Support\Approvals\Enums;

enum ApprovalState: string
{
    case APPROVED = 'approved';
    case DENIED = 'denied';
    case PENDING = 'pending';
    case OPEN = 'open';
}
```

- [ ] **Step 3: Create HasApprovalStatuses contract**

```php
<?php

namespace App\Support\Approvals\Contracts;

use BackedEnum;

interface HasApprovalStatuses extends BackedEnum
{
    /** @return static[] */
    public static function getApprovedStatuses(): array;

    /** @return static[] */
    public static function getDeniedStatuses(): array;

    /** @return static[] */
    public static function getPendingStatuses(): array;

    public static function getCaseLabel(self $case): string;
}
```

- [ ] **Step 4: Create Approver marker interface**

```php
<?php

namespace App\Support\Approvals\Contracts;

interface Approver
{
    //
}
```

- [ ] **Step 5: Write ApprovalState test**

```php
<?php

use App\Support\Approvals\Enums\ApprovalState;

test('approval state has expected values', function () {
    expect(ApprovalState::APPROVED->value)->toBe('approved');
    expect(ApprovalState::DENIED->value)->toBe('denied');
    expect(ApprovalState::PENDING->value)->toBe('pending');
    expect(ApprovalState::OPEN->value)->toBe('open');
});

test('approval state cases are unique', function () {
    $values = array_map(fn($case) => $case->value, ApprovalState::cases());
    expect($values)->toHaveCount(count(array_unique($values)));
});
```

- [ ] **Step 6: Run test to verify it passes**

```bash
php artisan test tests/Unit/Support/Approvals/ApprovalStateTest.php --filter='approval state'
```
Expected: PASS (2 tests, 2 assertions)

- [ ] **Step 7: Commit**

```bash
git add app/Support/Approvals/Enums/ApprovalState.php
git add app/Support/Approvals/Contracts/HasApprovalStatuses.php
git add app/Support/Approvals/Contracts/Approver.php
git add tests/Unit/Support/Approvals/ApprovalStateTest.php
git commit -m "feat(approvals): add ApprovalState enum, HasApprovalStatuses and Approver contracts"
```

---

### Task 2: Core Contracts (Approvable, ApprovalFlow, ApprovalBy)

**Files:**
- Create: `app/Support/Approvals/Contracts/Approvable.php`
- Create: `app/Support/Approvals/Contracts/ApprovalFlow.php`
- Create: `app/Support/Approvals/Contracts/ApprovalBy.php`

- [ ] **Step 1: Create Approvable contract**

```php
<?php

namespace App\Support\Approvals\Contracts;

use App\Support\Approvals\Enums\ApprovalState;
use App\Support\Approvals\Models\Approval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

interface Approvable
{
    /** @return MorphMany<Approval, $this> */
    public function approvals(): MorphMany;

    /** @return array<string, ApprovalFlow> */
    public function getApprovalFlows(): array;

    public function getApprovalFlow(string $key): ?ApprovalFlow;

    public function approved(?array $categories = null, ?array $keys = null): ApprovalState;

    public function isApproved(?array $categories = null, ?array $keys = null): bool;

    public function isDenied(?array $categories = null, ?array $keys = null): bool;

    public function isPending(?array $categories = null, ?array $keys = null): bool;

    public function isOpen(?array $categories = null, ?array $keys = null): bool;

    /** @return array<string, mixed> */
    public function approvalStatistics(?array $categories = null, ?array $keys = null): array;

    /** @return array<string, ApprovalFlow> */
    public function getFilteredApprovalFlow(?array $categories = null, ?array $keys = null): array;
}
```

- [ ] **Step 2: Create ApprovalFlow contract**

```php
<?php

namespace App\Support\Approvals\Contracts;

use App\Support\Approvals\Enums\ApprovalState;
use Closure;
use Illuminate\Database\Eloquent\Model;

interface ApprovalFlow
{
    public function disabled(bool|Closure $disabled): static;

    public function getCategory(): string;

    /** @return null|class-string<HasApprovalStatuses> */
    public function getStatusEnumClass(): ?string;

    /** @return HasApprovalStatuses[] */
    public function getApprovalStatus(): array;

    public function approved(Model|Approvable $approvable, string $key): ApprovalState;

    public function isDisabled(): bool;

    /** @return array<ApprovalBy> */
    public function getApprovalBys(): array;

    /** @param array<ApprovalBy> $bys */
    public function approvalBys(array $bys): static;
}
```

- [ ] **Step 3: Create ApprovalBy contract**

```php
<?php

namespace App\Support\Approvals\Contracts;

use App\Support\Approvals\Enums\ApprovalState;
use App\Support\Approvals\Models\Approval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface ApprovalBy
{
    public function approved(Model|Approvable $approvable, string $key): ApprovalState;

    /** @return Collection<int, Approval> */
    public function getApprovals(Model|Approvable $approvable, string $key): Collection;

    public function getName(): string;

    public function getLabel(): ?string;

    public function getApprovalFlow(Model|Approvable $approvable, string $key): ?ApprovalFlow;

    public function canApprove(Approver|Model $approver, Approvable $approvable): bool;

    public function canApproveFromPermissions(Approver|Model $approver): bool;

    public function reachAtLeast(Approvable|Model $approvable, string $key): bool;
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Support/Approvals/Contracts/
git commit -m "feat(approvals): add Approvable, ApprovalFlow and ApprovalBy contracts"
```

---

### Task 3: Approvals Database Migration + Approval Model

**Files:**
- Create: `database/migrations/2026_05_21_000001_create_approvals_table.php`
- Create: `app/Support/Approvals/Models/Approval.php`

- [ ] **Step 1: Create migration**

```bash
php artisan make:migration create_approvals_table
```

Wait for the migration file to be created, then replace its content with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->nullableMorphs('approvable');
            $table->string('status');
            $table->string('approval_by');
            $table->morphs('approver');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['approvable_type', 'approvable_id', 'key', 'approval_by'], 'approvals_flow_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate
```
Expected: Created approvals table

- [ ] **Step 3: Create Approval model**

```php
<?php

namespace App\Support\Approvals\Models;

use App\Support\Approvals\Contracts\Approvable;
use App\Support\Approvals\Contracts\ApprovalFlow;
use App\Support\Approvals\Contracts\HasApprovalStatuses;
use Error;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Approval extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'key',
        'approvable_type',
        'approvable_id',
        'approver_id',
        'approver_type',
        'approval_by',
        'status',
    ];

    public function approver(): MorphTo
    {
        return $this->morphTo('approver');
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo('approvable');
    }

    public function getStatusAttribute(string|null $value): HasApprovalStatuses|string|null
    {
        if ($value === null) {
            return null;
        }

        try {
            $flow = $this->getApprovalFlow();

            if ($flow === null) {
                return $value;
            }

            return collect($flow->getApprovalStatus())
                ->firstWhere(fn($unitEnum) => $unitEnum->value === $value) ?? $value;
        } catch (Error|Exception) {
            return $value;
        }
    }

    public function setStatusAttribute(HasApprovalStatuses|string $value): void
    {
        $this->attributes['status'] = $value instanceof HasApprovalStatuses ? $value->value : $value;
    }

    protected function getApprovalFlow(): ?ApprovalFlow
    {
        $approvable = $this->approvable;

        if (! $approvable instanceof Approvable) {
            return null;
        }

        return $approvable->getApprovalFlow($this->key);
    }
}
```

- [ ] **Step 4: Run existing tests to make sure nothing broke**

```bash
php artisan test --filter='approval state'
```
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_05_21_000001_create_approvals_table.php
git add app/Support/Approvals/Models/Approval.php
git commit -m "feat(approvals): add polymorphic approvals table and Approval model"
```

---

### Task 4: HandlesApprovals Concern

**Files:**
- Create: `app/Support/Approvals/Concerns/HandlesApprovals.php`

- [ ] **Step 1: Create HandlesApprovals concern**

```php
<?php

namespace App\Support\Approvals\Concerns;

use App\Support\Approvals\Contracts\ApprovalBy;
use App\Support\Approvals\Contracts\HasApprovalStatuses;
use App\Models\User;
use App\Support\Approvals\Models\Approval;
use Illuminate\Support\Facades\Auth;

trait HandlesApprovals
{
    protected function createApproval(
        HasApprovalStatuses $status,
        ApprovalBy $approvalBy,
        string $key
    ): Approval {
        $record = $this->getRecord();

        return Approval::create([
            'approver_id' => Auth::id(),
            'approver_type' => User::class,
            'approvable_id' => $record->id,
            'approvable_type' => $record::class,
            'status' => $status->value,
            'key' => $key,
            'approval_by' => $approvalBy->getName(),
        ]);
    }

    protected function removeApproval(ApprovalBy $approvalBy, string $key): void
    {
        $this->getBoundApprovals($approvalBy, $key)->each->delete();
    }

    protected function getBoundApprovals(ApprovalBy $approvalBy, string $key)
    {
        $record = $this->getRecord();

        return $record->approvals
            ->where('key', $key)
            ->where('approval_by', $approvalBy->getName())
            ->where('approver_id', Auth::id())
            ->where('approver_type', User::class);
    }

    protected function getCurrentStatus(ApprovalBy $approvalBy, string $key): ?HasApprovalStatuses
    {
        $approval = $this->getBoundApprovals($approvalBy, $key)->first();

        return $approval?->status;
    }

    abstract public function getRecord();
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Support/Approvals/Concerns/HandlesApprovals.php
git commit -m "feat(approvals): add HandlesApprovals concern for approval CRUD"
```

---

### Task 5: HasApprovals Eloquent Trait

**Files:**
- Create: `app/Support/Approvals/Traits/HasApprovals.php`

- [ ] **Step 1: Create HasApprovals trait**

```php
<?php

namespace App\Support\Approvals\Traits;

use App\Support\Approvals\Contracts\ApprovalFlow;
use App\Support\Approvals\Enums\ApprovalState;
use App\Support\Approvals\Models\Approval;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;

/** @property \Illuminate\Support\Collection $approvals */
trait HasApprovals
{
    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable');
    }

    public function getApprovalFlow(string $key): ?ApprovalFlow
    {
        return $this->getApprovalFlows()[$key] ?? null;
    }

    public function approved(?array $categories = null, ?array $keys = null): ApprovalState
    {
        $flows = $this->getFilteredApprovalFlow($categories, $keys);
        $isPending = false;
        $isOpen = false;

        foreach ($flows as $key => $flow) {
            $state = $flow->approved($this, $key);

            if ($state === ApprovalState::PENDING) {
                $isPending = true;
            } elseif ($state === ApprovalState::OPEN) {
                $isOpen = true;
            } elseif ($state === ApprovalState::DENIED) {
                return ApprovalState::DENIED;
            }
        }

        if ($isPending) {
            return ApprovalState::PENDING;
        }

        if ($isOpen) {
            return ApprovalState::OPEN;
        }

        return ApprovalState::APPROVED;
    }

    public function isApproved(?array $categories = null, ?array $keys = null): bool
    {
        return $this->approved($categories, $keys) === ApprovalState::APPROVED;
    }

    public function isDenied(?array $categories = null, ?array $keys = null): bool
    {
        return $this->approved($categories, $keys) === ApprovalState::DENIED;
    }

    public function isPending(?array $categories = null, ?array $keys = null): bool
    {
        return $this->approved($categories, $keys) === ApprovalState::PENDING;
    }

    public function isOpen(?array $categories = null, ?array $keys = null): bool
    {
        return $this->approved($categories, $keys) === ApprovalState::OPEN;
    }

    public function getFilteredApprovalFlow(?array $categories = null, ?array $keys = null): array
    {
        $flows = $this->getApprovalFlows();

        if ($keys !== null) {
            $flows = Arr::only($flows, $keys);
        }

        if ($categories !== null) {
            $flows = Arr::where($flows, fn(ApprovalFlow $flow) => in_array($flow->getCategory(), $categories));
        }

        return $flows;
    }

    public function approvalStatistics(?array $categories = null, ?array $keys = null): array
    {
        $flows = $this->getFilteredApprovalFlow($categories, $keys);
        $statistics = [];

        foreach ($flows as $key => $flow) {
            $byStatistics = [];

            foreach ($flow->getApprovalBys() as $approvalBy) {
                $approvals = $approvalBy->getApprovals($this, $key);
                $states = $approvals->pluck('status')->map(fn($s) => $s instanceof \BackedEnum ? $s->value : $s);

                $byStatistics[$approvalBy->getName()] = [
                    'reached_at_least' => $approvalBy->reachAtLeast($this, $key),
                    'statuses' => $states->values()->toArray(),
                    'count' => $approvals->count(),
                ];
            }

            $statistics[$key] = [
                'category' => $flow->getCategory(),
                'by_statistics' => $byStatistics,
            ];
        }

        return $statistics;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Support/Approvals/Traits/HasApprovals.php
git commit -m "feat(approvals): add HasApprovals Eloquent trait for approvable models"
```

---

### Task 6: SimpleApprovalFlow + SimpleApprovalBy Implementations

**Files:**
- Create: `app/Support/Approvals/Approval/SimpleApprovalFlow.php`
- Create: `app/Support/Approvals/Approval/SimpleApprovalBy.php`
- Test: `tests/Unit/Support/Approvals/SimpleApprovalFlowTest.php`
- Test: `tests/Unit/Support/Approvals/SimpleApprovalByTest.php`

- [ ] **Step 1: Create SimpleApprovalFlow**

```php
<?php

namespace App\Support\Approvals\Approval;

use App\Support\Approvals\Contracts\Approvable;
use App\Support\Approvals\Contracts\ApprovalBy;
use App\Support\Approvals\Contracts\ApprovalFlow;
use App\Support\Approvals\Contracts\HasApprovalStatuses;
use App\Support\Approvals\Enums\ApprovalState;
use Closure;
use Illuminate\Database\Eloquent\Model;

class SimpleApprovalFlow implements ApprovalFlow
{
    protected string $category = 'default';

    /** @var array<ApprovalBy> */
    protected array $approvalBys = [];

    /** @var HasApprovalStatuses[] */
    protected array $approvalStatuses = [];

    /** @var null|class-string<HasApprovalStatuses> */
    protected ?string $statusEnumClass = null;

    protected bool|Closure $disabled = false;

    public static function make(): static
    {
        return new static();
    }

    public function disabled(bool|Closure $disabled = true): static
    {
        $this->disabled = $disabled;

        return $this;
    }

    public function isDisabled(): bool
    {
        return $this->disabled instanceof Closure
            ? (bool) ($this->disabled)()
            : $this->disabled;
    }

    /** @param HasApprovalStatuses[] $statuses */
    public function approvalStatus(array $statuses): static
    {
        $this->approvalStatuses = $statuses;
        $this->statusEnumClass = !empty($statuses) ? $statuses[0]::class : null;

        return $this;
    }

    /** @param array<ApprovalBy> $bys */
    public function approvalBys(array $bys): static
    {
        $this->approvalBys = $bys;

        return $this;
    }

    public function category(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getStatusEnumClass(): ?string
    {
        return $this->statusEnumClass;
    }

    public function getApprovalStatus(): array
    {
        return $this->approvalStatuses;
    }

    public function getApprovalBys(): array
    {
        return $this->approvalBys;
    }

    public function approved(Model|Approvable $approvable, string $key): ApprovalState
    {
        if ($this->isDisabled()) {
            return ApprovalState::APPROVED;
        }

        $isPending = false;
        $isOpen = false;

        foreach ($this->approvalBys as $approvalBy) {
            $state = $approvalBy->approved($approvable, $key);

            if ($state === ApprovalState::PENDING) {
                $isPending = true;
            } elseif ($state === ApprovalState::OPEN) {
                $isOpen = true;
            } elseif ($state === ApprovalState::DENIED) {
                return ApprovalState::DENIED;
            }
        }

        if ($isPending) {
            return ApprovalState::PENDING;
        }

        if ($isOpen) {
            return ApprovalState::OPEN;
        }

        return ApprovalState::APPROVED;
    }
}
```

- [ ] **Step 2: Create SimpleApprovalBy**

```php
<?php

namespace App\Support\Approvals\Approval;

use App\Support\Approvals\Contracts\Approvable;
use App\Support\Approvals\Contracts\ApprovalBy;
use App\Support\Approvals\Contracts\ApprovalFlow;
use App\Support\Approvals\Contracts\Approver;
use App\Support\Approvals\Enums\ApprovalState;
use App\Support\Approvals\Models\Approval;
use Closure;
use Error;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class SimpleApprovalBy implements ApprovalBy
{
    protected string $name;

    protected bool $isAny = false;

    /** @var string[] */
    protected array $roles = [];

    protected ?string $permission = null;

    protected int $atLeast = 1;

    protected ?Closure $canApproveUsing = null;

    protected ?string $label = null;

    final public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function any(bool $any = true): static
    {
        $this->isAny = $any;

        return $this;
    }

    public function role(string $role): static
    {
        $this->roles[] = $role;

        return $this;
    }

    public function orRole(string $role): static
    {
        return $this->role($role);
    }

    public function permission(string $permission): static
    {
        $this->permission = $permission;

        return $this;
    }

    public function atLeast(int $count): static
    {
        $this->atLeast = $count;

        return $this;
    }

    public function canApproveUsing(Closure $callback): static
    {
        $this->canApproveUsing = $callback;

        return $this;
    }

    public function canApprove(Approver|Model $approver, Approvable $approvable): bool
    {
        if ($this->canApproveUsing !== null) {
            return (bool) ($this->canApproveUsing)($approver, $approvable);
        }

        if ($this->isAny) {
            return true;
        }

        return $this->canApproveFromPermissions($approver);
    }

    public function canApproveFromPermissions(Approver|Model $approver): bool
    {
        try {
            if (!empty($this->roles) && $approver instanceof User) {
                foreach ($this->roles as $role) {
                    if ($approver->hasRole($role)) {
                        return true;
                    }
                }
            }

            if ($this->permission !== null && $approver instanceof User) {
                return $approver->hasPermissionTo($this->permission);
            }
        } catch (Error|Exception) {
        }

        return false;
    }

    public function approved(Model|Approvable $approvable, string $key): ApprovalState
    {
        $approvals = $this->getApprovals($approvable, $key);
        $flow = $this->getApprovalFlow($approvable, $key);
        $statusClass = $flow?->getStatusEnumClass();

        if ($statusClass === null) {
            return $this->reachAtLeast($approvable, $key)
                ? ApprovalState::APPROVED
                : ApprovalState::OPEN;
        }

        $deniedValues = collect($statusClass::getDeniedStatuses())->map(fn($s) => $s->value);
        $hasDenied = $approvals->contains(fn(Approval $a) => $deniedValues->contains($a->getRawOriginal('status')));

        if ($hasDenied) {
            return ApprovalState::DENIED;
        }

        $pendingValues = collect($statusClass::getPendingStatuses())->map(fn($s) => $s->value);
        $hasPending = $approvals->contains(fn(Approval $a) => $pendingValues->contains($a->getRawOriginal('status')));

        if ($hasPending) {
            return ApprovalState::PENDING;
        }

        if (! $this->reachAtLeast($approvable, $key)) {
            return ApprovalState::OPEN;
        }

        return ApprovalState::APPROVED;
    }

    public function getApprovals(Model|Approvable $approvable, string $key): Collection
    {
        return $approvable->approvals
            ->where('key', $key)
            ->where('approval_by', $this->name);
    }

    public function getApprovalFlow(Model|Approvable $approvable, string $key): ?ApprovalFlow
    {
        if ($approvable instanceof Approvable) {
            return $approvable->getApprovalFlow($key);
        }

        return null;
    }

    public function reachAtLeast(Approvable|Model $approvable, string $key): bool
    {
        return $this->getApprovals($approvable, $key)->count() >= $this->atLeast;
    }

    public function getAtLeast(): int
    {
        return $this->atLeast;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPermission(): ?string
    {
        return $this->permission;
    }

    public function isAny(): bool
    {
        return $this->isAny;
    }
}
```

- [ ] **Step 3: Write SimpleApprovalFlow test**

```php
<?php

use App\Support\Approvals\Approval\SimpleApprovalBy;
use App\Support\Approvals\Approval\SimpleApprovalFlow;
use App\Support\Approvals\Enums\ApprovalState;

// We need a mock approvable that returns specific approvals
beforeEach(function () {
    $this->flow = SimpleApprovalFlow::make();
});

test('disabled flow always returns approved', function () {
    $flow = SimpleApprovalFlow::make()->disabled();
    $approvable = mockOverload(\App\Support\Approvals\Contracts\Approvable::class);

    expect($flow->approved($approvable, 'test'))->toBe(ApprovalState::APPROVED);
});

test('flow with no approval bys returns approved', function () {
    $approvable = mockOverload(\App\Support\Approvals\Contracts\Approvable::class);

    expect($this->flow->approved($approvable, 'test'))->toBe(ApprovalState::APPROVED);
});

test('flow returns denied when any approvalBy is denied', function () {
    $approvable = Mockery::mock(\App\Support\Approvals\Contracts\Approvable::class);
    $approvable->allows('approvals')->andReturn(collect());

    $deniedBy = Mockery::mock(\App\Support\Approvals\Contracts\ApprovalBy::class);
    $deniedBy->allows('approved')->andReturn(ApprovalState::DENIED);

    $this->flow->approvalBys([$deniedBy]);

    expect($this->flow->approved($approvable, 'test'))->toBe(ApprovalState::DENIED);
});

test('flow returns pending when any approvalBy is pending and none denied', function () {
    $approvable = Mockery::mock(\App\Support\Approvals\Contracts\Approvable::class);

    $pendingBy = Mockery::mock(\App\Support\Approvals\Contracts\ApprovalBy::class);
    $pendingBy->allows('approved')->andReturn(ApprovalState::PENDING);

    $approvedBy = Mockery::mock(\App\Support\Approvals\Contracts\ApprovalBy::class);
    $approvedBy->allows('approved')->andReturn(ApprovalState::APPROVED);

    $this->flow->approvalBys([$pendingBy, $approvedBy]);

    expect($this->flow->approved($approvable, 'test'))->toBe(ApprovalState::PENDING);
});

test('flow returns approved when all approvalBys are approved', function () {
    $approvable = Mockery::mock(\App\Support\Approvals\Contracts\Approvable::class);

    $by1 = Mockery::mock(\App\Support\Approvals\Contracts\ApprovalBy::class);
    $by1->allows('approved')->andReturn(ApprovalState::APPROVED);

    $by2 = Mockery::mock(\App\Support\Approvals\Contracts\ApprovalBy::class);
    $by2->allows('approved')->andReturn(ApprovalState::APPROVED);

    $this->flow->approvalBys([$by1, $by2]);

    expect($this->flow->approved($approvable, 'test'))->toBe(ApprovalState::APPROVED);
});

test('flow returns open when all approvalBys are open', function () {
    $approvable = Mockery::mock(\App\Support\Approvals\Contracts\Approvable::class);

    $by1 = Mockery::mock(\App\Support\Approvals\Contracts\ApprovalBy::class);
    $by1->allows('approved')->andReturn(ApprovalState::OPEN);

    $this->flow->approvalBys([$by1]);

    expect($this->flow->approved($approvable, 'test'))->toBe(ApprovalState::OPEN);
});
```

- [ ] **Step 4: Write SimpleApprovalBy test**

```php
<?php

use App\Support\Approvals\Approval\SimpleApprovalBy;

test('simple approval by has name', function () {
    $by = SimpleApprovalBy::make('management');
    expect($by->getName())->toBe('management');
});

test('simple approval by any allows any approver', function () {
    $by = SimpleApprovalBy::make('anyone')->any();
    $approver = Mockery::mock(\App\Support\Approvals\Contracts\Approver::class);
    $approvable = Mockery::mock(\App\Support\Approvals\Contracts\Approvable::class);

    expect($by->canApprove($approver, $approvable))->toBeTrue();
});

test('simple approval by any without role denies non-authenticated', function () {
    $by = SimpleApprovalBy::make('restricted');
    $approver = Mockery::mock(\App\Support\Approvals\Contracts\Approver::class);
    $approvable = Mockery::mock(\App\Support\Approvals\Contracts\Approvable::class);

    expect($by->canApprove($approver, $approvable))->toBeFalse();
});

test('simple approval by with custom closure', function () {
    $by = SimpleApprovalBy::make('custom')
        ->canApproveUsing(fn($approver, $approvable) => true);
    $approver = Mockery::mock(\App\Support\Approvals\Contracts\Approver::class);
    $approvable = Mockery::mock(\App\Support\Approvals\Contracts\Approvable::class);

    expect($by->canApprove($approver, $approvable))->toBeTrue();
});

test('reachAtLeast returns false when under threshold', function () {
    $approvable = Mockery::mock(\App\Support\Approvals\Contracts\Approvable::class);
    $approvable->allows('approvals')->andReturn(collect([
        (object) ['key' => 'test', 'approval_by' => 'mgmt'],
    ]));

    $by = SimpleApprovalBy::make('mgmt')->atLeast(2);

    expect($by->reachAtLeast($approvable, 'test'))->toBeFalse();
});

test('reachAtLeast returns true when at threshold', function () {
    $approvable = Mockery::mock(\App\Support\Approvals\Contracts\Approvable::class);
    $approvable->allows('approvals')->andReturn(collect([
        (object) ['key' => 'test', 'approval_by' => 'mgmt'],
        (object) ['key' => 'test', 'approval_by' => 'mgmt'],
    ]));

    $by = SimpleApprovalBy::make('mgmt')->atLeast(2);

    expect($by->reachAtLeast($approvable, 'test'))->toBeTrue();
});
```

- [ ] **Step 5: Run tests**

```bash
php artisan test tests/Unit/Support/Approvals/
```
Expected: All 11 unit tests PASS

- [ ] **Step 6: Commit**

```bash
git add app/Support/Approvals/Approval/
git add tests/Unit/Support/Approvals/SimpleApprovalFlowTest.php
git add tests/Unit/Support/Approvals/SimpleApprovalByTest.php
git commit -m "feat(approvals): add SimpleApprovalFlow and SimpleApprovalBy implementations"
```

---

### Task 7: BookingApprovalStatus Enum

**Files:**
- Create: `app/Support/Approvals/ApprovalStatus/BookingApprovalStatus.php`

- [ ] **Step 1: Create BookingApprovalStatus enum**

```php
<?php

namespace App\Support\Approvals\ApprovalStatus;

use App\Support\Approvals\Contracts\HasApprovalStatuses;

enum BookingApprovalStatus: string implements HasApprovalStatuses
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public static function getApprovedStatuses(): array
    {
        return [self::Approved];
    }

    public static function getDeniedStatuses(): array
    {
        return [self::Rejected];
    }

    public static function getPendingStatuses(): array
    {
        return [self::Pending];
    }

    public static function getCaseLabel(self $case): string
    {
        return match ($case) {
            self::Approved => 'Approved',
            self::Pending => 'Pending',
            self::Rejected => 'Rejected',
        };
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Support/Approvals/ApprovalStatus/BookingApprovalStatus.php
git commit -m "feat(approvals): add BookingApprovalStatus enum"
```

---

### Task 8: Booking Model Integration

**Files:**
- Modify: `app/Models/Booking.php`

- [ ] **Step 1: Read the current Booking model**

```bash
# Already read above
```

- [ ] **Step 2: Add Approvable implementation to Booking**

Edit `app/Models/Booking.php`:

- Add imports:
```php
use App\Support\Approvals\Approval\SimpleApprovalBy;
use App\Support\Approvals\Approval\SimpleApprovalFlow;
use App\Support\Approvals\ApprovalStatus\BookingApprovalStatus;
use App\Support\Approvals\Contracts\Approvable;
use App\Support\Approvals\Traits\HasApprovals;
```

- Change class declaration:
```php
class Booking extends Model implements Approvable
```

- Add `use HasApprovals;` inside the class

- Remove old `status`, `approved_by`, `approved_at`, `qr_token`, `qr_code` from `$fillable`

- Update `$fillable`:
```php
protected $fillable = [
    'room_id',
    'user_id',
    'title',
    'description',
    'starts_at',
    'ends_at',
];
```

- Remove old scopes/casts (remove `approved_by`, `approved_at`, `qr_token`, `qr_code` from casts)

- Add approval flow method:
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

- Remove `approver()` BelongsTo relation (no longer used)

- Keep `attendance()` HasMany, `room()`, `user()` relations

- Keep `isExpired()` and `isQrExpired()` stubs (will be refactored later — for now keep them simple)

The final Booking model should look like:

```php
<?php

namespace App\Models;

use App\Support\Approvals\Approval\SimpleApprovalBy;
use App\Support\Approvals\Approval\SimpleApprovalFlow;
use App\Support\Approvals\ApprovalStatus\BookingApprovalStatus;
use App\Support\Approvals\Contracts\Approvable;
use App\Support\Approvals\Traits\HasApprovals;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model implements Approvable
{
    /** @use HasFactory<\Database\Factories\BookingFactory> */
    use HasFactory, HasApprovals;

    protected $fillable = [
        'room_id',
        'user_id',
        'title',
        'description',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

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

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function isExpired(): bool
    {
        return $this->ends_at->endOfDay()->isPast();
    }

    public function scopePending($query)
    {
        return $query->whereHas('approvals', function ($q) {
            $q->where('key', 'booking_approval');
        });
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
```

- [ ] **Step 3: Make sure the tests reference the right things**

Run the full unit test suite:
```bash
php artisan test tests/Unit/Support/Approvals/
```
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Models/Booking.php
git commit -m "feat(approvals): integrate Booking model with dynamic approval system"
```

---

### Task 9: Filament ApprovalActions Component

**Files:**
- Create: `app/Support/Approvals/Filament/Components/ApprovalActions.php`

- [ ] **Step 1: Create ApprovalActions component**

```php
<?php

namespace App\Support\Approvals\Filament\Components;

use App\Support\Approvals\Approval\SimpleApprovalBy;
use App\Support\Approvals\Concerns\HandlesApprovals;
use App\Support\Approvals\Contracts\Approvable;
use App\Support\Approvals\Contracts\ApprovalBy;
use App\Support\Approvals\Contracts\ApprovalFlow;
use App\Support\Approvals\Contracts\HasApprovalStatuses;
use App\Support\Approvals\Enums\ApprovalState;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ApprovalActions extends Component
{
    use HandlesApprovals;

    protected string $approvalKey;

    protected string $view = 'filament::components.section';

    final public function __construct(string $approvalKey)
    {
        $this->approvalKey = $approvalKey;
    }

    public static function make(string $approvalKey): static
    {
        $static = app(static::class, ['approvalKey' => $approvalKey]);
        $static->setUp();

        return $static;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema(function () {
            $record = $this->getRecord();

            if (! $record instanceof Approvable) {
                return [];
            }

            $flow = $record->getApprovalFlow($this->approvalKey);

            if ($flow === null || $flow->isDisabled()) {
                return [];
            }

            return $this->buildFlowComponents($flow);
        });
    }

    protected function buildFlowComponents(ApprovalFlow $flow): array
    {
        $components = [];

        foreach ($flow->getApprovalBys() as $approvalBy) {
            $components[] = $this->buildApprovalByGroup($approvalBy);
        }

        // Overall state summary
        $components[] = $this->buildStateSummary($flow);

        return $components;
    }

    protected function buildApprovalByGroup(ApprovalBy $approvalBy): Group
    {
        $record = $this->getRecord();
        $actions = [];
        $currentStatus = $this->getCurrentApprovalStatus($approvalBy);
        $canApprove = $approvalBy->canApprove(Auth::user(), $record);

        foreach ($approvalBy->getApprovalFlow($record, $this->approvalKey)->getApprovalStatus() as $status) {
            $actions[] = $this->buildStatusAction($status, $approvalBy, $currentStatus, $canApprove);
        }

        $label = $approvalBy->getLabel() ?? $approvalBy->getName();

        return Group::make()
            ->schema([
                TextEntry::make("_{$approvalBy->getName()}_title")
                    ->state(ucfirst($label))
                    ->label('')
                    ->weight('bold')
                    ->size('sm'),
                ...$actions,
            ]);
    }

    protected function buildStatusAction(
        HasApprovalStatuses $status,
        ApprovalBy $approvalBy,
        ?HasApprovalStatuses $currentStatus,
        bool $canApprove
    ): Action {
        $isActive = $currentStatus !== null && $currentStatus->value === $status->value;
        $isApprovedStatus = in_array($status, $status::getApprovedStatuses());
        $isDeniedStatus = in_array($status, $status::getDeniedStatuses());

        $color = match (true) {
            $isActive && $isApprovedStatus => 'success',
            $isActive && $isDeniedStatus => 'danger',
            $isActive => 'warning',
            $isApprovedStatus => 'success',
            $isDeniedStatus => 'danger',
            default => 'gray',
        };

        $icon = match (true) {
            $isActive && $isApprovedStatus => 'heroicon-o-check-circle',
            $isActive && $isDeniedStatus => 'heroicon-o-x-circle',
            $isActive => 'heroicon-o-clock',
            $isApprovedStatus => 'heroicon-o-hand-thumb-up',
            $isDeniedStatus => 'heroicon-o-hand-thumb-down',
            default => 'heroicon-o-ellipsis-horizontal-circle',
        };

        $labelText = $status::getCaseLabel($status);

        return Action::make("{$approvalBy->getName()}-{$status->value}")
            ->label($labelText)
            ->icon($icon)
            ->color($color)
            ->visible($canApprove)
            ->disabled($isActive)
            ->requiresConfirmation()
            ->action(function () use ($status, $approvalBy) {
                $this->changeApproval($status, $approvalBy);
            });
    }

    protected function buildStateSummary(ApprovalFlow $flow): TextEntry
    {
        $record = $this->getRecord();
        $state = $flow->approved($record, $this->approvalKey);

        $stateLabel = match ($state) {
            ApprovalState::APPROVED => 'Approved',
            ApprovalState::DENIED => 'Denied',
            ApprovalState::PENDING => 'Pending Approval',
            ApprovalState::OPEN => 'Awaiting Action',
        };

        $stateColor = match ($state) {
            ApprovalState::APPROVED => 'success',
            ApprovalState::DENIED => 'danger',
            ApprovalState::PENDING => 'warning',
            ApprovalState::OPEN => 'gray',
        };

        return TextEntry::make('_approval_state')
            ->label('Overall Status')
            ->state($stateLabel)
            ->badge()
            ->color($stateColor);
    }

    protected function getCurrentApprovalStatus(ApprovalBy $approvalBy): ?HasApprovalStatuses
    {
        return $this->getCurrentStatus($approvalBy, $this->approvalKey);
    }

    protected function changeApproval(HasApprovalStatuses $status, ApprovalBy $approvalBy): void
    {
        $this->createApproval($status, $approvalBy, $this->approvalKey);
        $this->getRecord()?->refresh();

        Notification::make()
            ->title("Status changed to {$status::getCaseLabel($status)}")
            ->success()
            ->send();
    }

    public function getRecord(bool $withContainerRecord = true): ?Model
    {
        return parent::getRecord($withContainerRecord);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Support/Approvals/Filament/Components/ApprovalActions.php
git commit -m "feat(approvals): add Filament ApprovalActions infolist component"
```

---

### Task 10: Update Filament Resources

**Files:**
- Modify: `app/Filament/Resources/Bookings/Pages/ViewBooking.php`
- Modify: `app/Filament/Resources/Bookings/Tables/BookingsTable.php`
- Modify: `app/Filament/Resources/Bookings/Schemas/BookingForm.php`

- [ ] **Step 1: Update ViewBooking page**

Edit `app/Filament/Resources/Bookings/Pages/ViewBooking.php`:

Add import:
```php
use App\Support\Approvals\ApprovalStatus\BookingApprovalStatus;
use App\Support\Approvals\Filament\Components\ApprovalActions;
```

Remove old `status` TextEntry from the Meeting Details section.

Replace the `ImageEntry` QR section with a dynamic section that only appears when fully approved. Keep the section but make it check `$record->isApproved()`.

Add an `Approval` section after Meeting Details:

```php
Section::make('Approval')
    ->visible(fn(\App\Models\Booking $record): bool => true)
    ->components([
        ApprovalActions::make('booking_approval'),
    ]),
```

Keep the overall infolist structure clean. The final `infolist` method should look like:

```php
public function infolist(Schema $schema): Schema
{
    return $schema
        ->components([
            Section::make('Meeting Details')
                ->components([
                    TextEntry::make('title')
                        ->weight(FontWeight::Bold)
                        ->size('lg'),
                    TextEntry::make('description')
                        ->markdown()
                        ->columnSpanFull(),
                    Group::make()
                        ->columns(2)
                        ->components([
                            TextEntry::make('room.name')
                                ->label('Room'),
                            TextEntry::make('room.location.name')
                                ->label('Location'),
                            TextEntry::make('starts_at')
                                ->dateTime('M d, Y H:i'),
                            TextEntry::make('ends_at')
                                ->dateTime('M d, Y H:i'),
                            TextEntry::make('user.name')
                                ->label('Booked by'),
                        ]),
                ]),
            Section::make('Approval')
                ->components([
                    ApprovalActions::make('booking_approval'),
                ]),
            Section::make('QR Code')
                ->visible(fn(Booking $record): bool => $record->isApproved())
                ->components([
                    ImageEntry::make('qr_code')
                        ->label('Scan to check in')
                        ->size(200)
                        ->simpleLightbox()
                        ->url(fn(Booking $record): string =>
                            'data:image/png;base64,' . base64_encode(
                                \Milon\Barcode\Facades\DNS2DFacade::getBarcodePNG($record->qr_code, 'QRCODE', 8, 8)
                            )
                        )
                        ->extraImgAttributes(['class' => 'mx-auto']),
                ]),
        ]);
}
```

- [ ] **Step 2: Update BookingsTable**

Edit `app/Filament/Resources/Bookings/Tables/BookingsTable.php`:

The current `approve`/`reject` table actions use static `status` field. Replace them:

- Change the `status` column to dynamically compute from the approval system. Since Filament table columns use the model attributes, we need to compute the state differently.

Replace the `status` TextColumn with a computed one:

```php
TextColumn::make('approval_state')
    ->label('Status')
    ->badge()
    ->getStateUsing(fn(Booking $record): string => match ($record->approved()) {
        \App\Support\Approvals\Enums\ApprovalState::APPROVED => 'approved',
        \App\Support\Approvals\Enums\ApprovalState::DENIED => 'rejected',
        \App\Support\Approvals\Enums\ApprovalState::PENDING => 'pending',
        \App\Support\Approvals\Enums\ApprovalState::OPEN => 'open',
    })
    ->color(fn(string $state): string => match ($state) {
        'approved' => 'success',
        'pending' => 'warning',
        'rejected' => 'danger',
        'open' => 'gray',
        default => 'gray',
    })
    ->sortable(false),
```

Replace the `approve` and `reject` record actions with new ones that use the approval system:

```php
Action::make('approve')
    ->label('Approve')
    ->icon('heroicon-o-check-circle')
    ->color('success')
    ->visible(fn(Booking $record): bool =>
        $record->isPending() && (auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Super Admin')))
    ->requiresConfirmation()
    ->action(function (Booking $record) {
        $flow = $record->getApprovalFlow('booking_approval');
        $managementBy = collect($flow->getApprovalBys())
            ->first(fn($by) => $by->getName() === 'management');

        if ($managementBy) {
            \App\Support\Approvals\Models\Approval::create([
                'approver_id' => auth()->id(),
                'approver_type' => \App\Models\User::class,
                'approvable_id' => $record->id,
                'approvable_type' => Booking::class,
                'status' => \App\Support\Approvals\ApprovalStatus\BookingApprovalStatus::Approved->value,
                'key' => 'booking_approval',
                'approval_by' => 'management',
            ]);
        }

        // If the requester hasn't submitted yet, auto-submit
        $requesterApproval = $record->approvals
            ->where('key', 'booking_approval')
            ->where('approval_by', 'requester')
            ->first();

        if (! $requesterApproval) {
            \App\Support\Approvals\Models\Approval::create([
                'approver_id' => $record->user_id,
                'approver_type' => \App\Models\User::class,
                'approvable_id' => $record->id,
                'approvable_type' => Booking::class,
                'status' => \App\Support\Approvals\ApprovalStatus\BookingApprovalStatus::Pending->value,
                'key' => 'booking_approval',
                'approval_by' => 'requester',
            ]);
        }

        $record->refresh();

        // If fully approved, generate QR code and notification
        if ($record->isApproved()) {
            $qrToken = (string) \Illuminate\Support\Str::uuid();
            $qrCodeUrl = url('/attendance/' . $qrToken);

            $record->update([
                'qr_token' => $qrToken,
                'qr_code' => $qrCodeUrl,
            ]);

            // Auto-check-in the booker
            $record->attendance()->create([
                'user_id' => $record->user_id,
                'checked_in_at' => now(),
            ]);

            $record->user->notify(new \App\Notifications\BookingApproved($record));
        }

        \Filament\Notifications\Notification::make()
            ->title('Booking approved successfully')
            ->success()
            ->send();
    }),
Action::make('reject')
    ->label('Reject')
    ->icon('heroicon-o-x-circle')
    ->color('danger')
    ->visible(fn(Booking $record): bool =>
        $record->isPending() && (auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Super Admin')))
    ->requiresConfirmation()
    ->form([
        \Filament\Forms\Components\Textarea::make('reason')
            ->label('Reason for rejection')
            ->required(),
    ])
    ->action(function (Booking $record, array $data) {
        \App\Support\Approvals\Models\Approval::create([
            'approver_id' => auth()->id(),
            'approver_type' => \App\Models\User::class,
            'approvable_id' => $record->id,
            'approvable_type' => Booking::class,
            'status' => \App\Support\Approvals\ApprovalStatus\BookingApprovalStatus::Rejected->value,
            'key' => 'booking_approval',
            'approval_by' => 'management',
        ]);

        $record->refresh();

        $record->user->notify(new \App\Notifications\BookingRejected($record, $data['reason'] ?? null));

        \Filament\Notifications\Notification::make()
            ->title('Booking rejected')
            ->warning()
            ->send();
    }),
```

Also remove the `scopeQuery` method or update it:

```php
public static function scopeQuery(Builder $query): Builder
{
    $user = auth()->user();

    if ($user->hasRole('Admin') || $user->hasRole('Super Admin')) {
        return $query;
    }

    return $query->where('user_id', $user->id);
}
```

Keep the `scopeQuery` as-is since it controls visibility, not approval status.

Remove the old `approveBooking` and `rejectBooking` static methods (they're now inline in the actions).

- [ ] **Step 2b: Remove old `approveBooking`/`rejectBooking` methods from BookingsTable**

Delete the static methods `approveBooking` and `rejectBooking` from `BookingsTable.php`.

- [ ] **Step 3: Update BookingForm**

Edit `app/Filament/Resources/Bookings/Schemas/BookingForm.php`:

No changes needed to the form itself — the `status`, `approved_by`, `approved_at` fields were already not in the form. Keep as-is.

- [ ] **Step 4: Run tests**

```bash
php artisan test
```
Expected: Existing tests pass (may need to update factory/fixtures)

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/Bookings/Pages/ViewBooking.php
git add app/Filament/Resources/Bookings/Tables/BookingsTable.php
git add app/Filament/Resources/Bookings/Schemas/BookingForm.php
git commit -m "feat(approvals): update Filament resources to use dynamic approval system"
```

---

### Task 11: Migration to Remove Old Booking Columns

**Files:**
- Create: `database/migrations/2026_05_21_000002_remove_old_approval_columns_from_bookings.php`

- [ ] **Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['status', 'approved_by', 'approved_at', 'qr_token', 'qr_code']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('ends_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->after('status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->string('qr_token')->nullable()->unique()->after('approved_at');
            $table->text('qr_code')->nullable()->after('qr_token');
        });
    }
};
```

- [ ] **Step 2: Add qr_token and qr_code back to the Booking model's fillable**

Since QR codes still need to be stored (generated on full approval), add them back to the Booking model:

Edit `app/Models/Booking.php` — keep `qr_token` and `qr_code` in `$fillable`:

```php
protected $fillable = [
    'room_id',
    'user_id',
    'title',
    'description',
    'starts_at',
    'ends_at',
    'qr_token',
    'qr_code',
];
```

- [ ] **Step 3: Run migration**

```bash
php artisan migrate
```
Expected: `status`, `approved_by`, `approved_at`, `qr_token`, `qr_code` columns removed from bookings table

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_05_21_000002_remove_old_approval_columns_from_bookings.php
git add app/Models/Booking.php
git commit -m "feat(approvals): remove old approval columns from bookings table"
```

---

### Task 12: Feature Tests for Booking Approval Lifecycle

**Files:**
- Create: `tests/Feature/Bookings/ApprovalLifecycleTest.php`

- [ ] **Step 1: Write feature tests**

```php
<?php

use App\Models\Booking;
use App\Models\User;
use App\Models\Room;
use App\Support\Approvals\ApprovalStatus\BookingApprovalStatus;
use App\Support\Approvals\Enums\ApprovalState;
use App\Support\Approvals\Models\Approval;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->user = User::factory()->create();

    $this->room = Room::factory()->create();

    $this->booking = Booking::factory()->create([
        'room_id' => $this->room->id,
        'user_id' => $this->user->id,
    ]);
});

test('new booking is in open state', function () {
    expect($this->booking->approved())->toBe(ApprovalState::OPEN);
    expect($this->booking->isOpen())->toBeTrue();
    expect($this->booking->isApproved())->toBeFalse();
    expect($this->booking->isPending())->toBeFalse();
});

test('requester can submit approval as pending', function () {
    $this->actingAs($this->user);

    Approval::create([
        'approver_id' => $this->user->id,
        'approver_type' => User::class,
        'approvable_id' => $this->booking->id,
        'approvable_type' => Booking::class,
        'status' => BookingApprovalStatus::Pending->value,
        'key' => 'booking_approval',
        'approval_by' => 'requester',
    ]);

    $this->booking->refresh();

    expect($this->booking->isPending())->toBeTrue();
    expect($this->booking->isApproved())->toBeFalse();
});

test('admin can fully approve a booking', function () {
    // Requester submits
    Approval::create([
        'approver_id' => $this->user->id,
        'approver_type' => User::class,
        'approvable_id' => $this->booking->id,
        'approvable_type' => Booking::class,
        'status' => BookingApprovalStatus::Pending->value,
        'key' => 'booking_approval',
        'approval_by' => 'requester',
    ]);

    // Admin approves
    $this->actingAs($this->admin);
    Approval::create([
        'approver_id' => $this->admin->id,
        'approver_type' => User::class,
        'approvable_id' => $this->booking->id,
        'approvable_type' => Booking::class,
        'status' => BookingApprovalStatus::Approved->value,
        'key' => 'booking_approval',
        'approval_by' => 'management',
    ]);

    $this->booking->refresh();

    expect($this->booking->isApproved())->toBeTrue();
});

test('admin can reject a booking', function () {
    // Requester submits
    Approval::create([
        'approver_id' => $this->user->id,
        'approver_type' => User::class,
        'approvable_id' => $this->booking->id,
        'approvable_type' => Booking::class,
        'status' => BookingApprovalStatus::Pending->value,
        'key' => 'booking_approval',
        'approval_by' => 'requester',
    ]);

    // Admin rejects
    $this->actingAs($this->admin);
    Approval::create([
        'approver_id' => $this->admin->id,
        'approver_type' => User::class,
        'approvable_id' => $this->booking->id,
        'approvable_type' => Booking::class,
        'status' => BookingApprovalStatus::Rejected->value,
        'key' => 'booking_approval',
        'approval_by' => 'management',
    ]);

    $this->booking->refresh();

    expect($this->booking->isDenied())->toBeTrue();
});

test('approval flow shows correct statistics', function () {
    Approval::create([
        'approver_id' => $this->user->id,
        'approver_type' => User::class,
        'approvable_id' => $this->booking->id,
        'approvable_type' => Booking::class,
        'status' => BookingApprovalStatus::Approved->value,
        'key' => 'booking_approval',
        'approval_by' => 'requester',
    ]);

    $stats = $this->booking->approvalStatistics();

    expect($stats)->toHaveKey('booking_approval');
    expect($stats['booking_approval']['by_statistics']['requester']['count'])->toBe(1);
});

test('non-admin user cannot approve via management', function () {
    $this->actingAs($this->user);
    $flow = $this->booking->getApprovalFlow('booking_approval');
    $managementBy = collect($flow->getApprovalBys())
        ->first(fn($by) => $by->getName() === 'management');

    expect($managementBy->canApprove($this->user, $this->booking))->toBeFalse();
});

test('admin can approve via management', function () {
    $this->actingAs($this->admin);
    $flow = $this->booking->getApprovalFlow('booking_approval');
    $managementBy = collect($flow->getApprovalBys())
        ->first(fn($by) => $by->getName() === 'management');

    expect($managementBy->canApprove($this->admin, $this->booking))->toBeTrue();
});
```

- [ ] **Step 2: Run feature tests**

```bash
php artisan test tests/Feature/Bookings/ApprovalLifecycleTest.php
```
Expected: All tests PASS

- [ ] **Step 3: Run full test suite**

```bash
php artisan test
```
Expected: All tests PASS

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Bookings/ApprovalLifecycleTest.php
git commit -m "test(approvals): add feature tests for booking approval lifecycle"
```
