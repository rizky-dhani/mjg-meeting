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
