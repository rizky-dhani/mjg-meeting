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
