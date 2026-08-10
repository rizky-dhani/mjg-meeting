<?php

use App\Exports\UserExport;
use App\Imports\UserImport;
use App\Models\Designation;
use App\Models\Division;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

function templateRows(): array
{
    $sheet = (new Xlsx)->load(base_path('user_import_template.xlsx'))->getActiveSheet();

    return array_slice($sheet->toArray(), 1);
}

it('imports the template with hashed default password, derived email, and auto-created divisions/designations', function () {
    $rows = templateRows();
    $valid = array_values(array_filter(
        $rows,
        fn ($r) => trim((string) ($r[1] ?? '')) !== '' && trim((string) ($r[0] ?? '')) !== ''
    ));

    $expectedNikCount = count(array_unique(array_map(fn ($r) => trim((string) $r[1]), $valid)));
    $expectedDivisions = count(array_unique(array_map(fn ($r) => trim((string) ($r[3] ?? '')), $valid)));
    $expectedDesignations = count(array_unique(array_map(fn ($r) => trim((string) ($r[4] ?? '')), $valid)));

    Excel::import(new UserImport, base_path('user_import_template.xlsx'));

    expect(User::count())->toBe($expectedNikCount);
    expect(Division::count())->toBe($expectedDivisions);
    expect(Designation::count())->toBe($expectedDesignations);

    $users = User::with(['division', 'designation'])->get();

    expect($users->pluck('employee_code')->unique()->count())->toBe($users->count());
    expect($users->pluck('email')->unique()->count())->toBe($users->count());

    foreach ($users as $user) {
        expect(Hash::check(UserImport::DEFAULT_PASSWORD, $user->password))->toBeTrue()
            ->and($user->is_active)->toBeTrue()
            ->and($user->email)->toBe(strtolower($user->employee_code).'@'.UserImport::EMAIL_DOMAIN);
    }

    $withDivision = $users->filter(fn (User $u) => $u->division !== null);
    $withDesignation = $users->filter(fn (User $u) => $u->designation !== null);

    expect($withDivision)->not->toBeEmpty()
        ->and($withDivision->pluck('division.name')->unique()->count())->toBe($expectedDivisions);
    expect($withDesignation)->not->toBeEmpty()
        ->and($withDesignation->pluck('designation.name')->unique()->count())->toBe($expectedDesignations);
});

it('is idempotent on re-import and does not reset passwords or reactivate users', function () {
    Excel::import(new UserImport, base_path('user_import_template.xlsx'));

    $count = User::count();
    $password = User::first()->password;

    User::first()->forceFill(['is_active' => false])->save();

    Excel::import(new UserImport, base_path('user_import_template.xlsx'));

    expect(User::count())->toBe($count)
        ->and(User::first()->password)->toBe($password)
        ->and(User::first()->is_active)->toBeFalse();
});

it('exports users in the template layout', function () {
    $division = Division::create([
        'division_id' => (string) Str::uuid(),
        'name' => 'Board Of Director',
        'initial' => '',
    ]);
    $designation = Designation::create([
        'designation_id' => (string) Str::uuid(),
        'name' => 'Commissioner',
    ]);
    $user = User::create([
        'name' => 'Susiana',
        'email' => '0002@medquest.co.id',
        'employee_code' => '0002',
        'initial' => 'SNA',
        'division_id' => $division->id,
        'designation_id' => $designation->designation_id,
        'password' => 'Medquest.1',
        'is_active' => true,
    ]);

    $export = new UserExport;

    expect($export->headings())->toBe(['Nama', 'NIK', 'Initial Name', 'Division', 'Designation'])
        ->and($export->collection())->toHaveCount(1)
        ->and($export->map($user))->toBe([
            'Susiana',
            '0002',
            'SNA',
            'Board Of Director',
            'Commissioner',
        ]);
});
