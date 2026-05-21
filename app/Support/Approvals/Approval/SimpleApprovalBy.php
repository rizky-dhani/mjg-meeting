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
