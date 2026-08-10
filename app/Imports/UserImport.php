<?php

namespace App\Imports;

use App\Models\Designation;
use App\Models\Division;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Imports users from the user_import_template.xlsx layout:
 * Nama | NIK | Initial Name | Division | Designation.
 *
 * - Rows without a NIK or name are skipped.
 * - Emails are derived from the NIK: {nik}@medquest.co.id.
 * - Division/designation are looked up by name and created when missing.
 * - Defaults (hashed "Medquest.1", is_active = 1) apply only to newly
 *   created users; re-imports refresh profile fields without resetting
 *   passwords or reactivating deactivated accounts.
 */
class UserImport implements ToCollection, WithHeadingRow
{
    public const DEFAULT_PASSWORD = 'Medquest.1';

    public const EMAIL_DOMAIN = 'medquest.co.id';

    /** @var array<string, int> trimmed division name => divisions.id */
    private array $divisions = [];

    /** @var array<string, string> trimmed designation name => designations.designation_id */
    private array $designations = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $nik = trim((string) ($row['nik'] ?? ''));
            $name = trim((string) ($row['nama'] ?? ''));

            if ($nik === '' || $name === '') {
                continue;
            }

            $attributes = [
                'name' => $name,
                'email' => strtolower($nik).'@'.self::EMAIL_DOMAIN,
                'initial' => trim((string) ($row['initial_name'] ?? '')) ?: null,
                'division_id' => $this->resolveDivision((string) ($row['division'] ?? '')),
                'designation_id' => $this->resolveDesignation((string) ($row['designation'] ?? '')),
            ];

            $user = User::where('employee_code', $nik)->first();

            if ($user) {
                $user->update($attributes);
            } else {
                User::create($attributes + [
                    'employee_code' => $nik,
                    'password' => self::DEFAULT_PASSWORD,
                    'is_active' => true,
                ]);
            }
        }
    }

    public function resolveDivision(string $name): ?int
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        if (! isset($this->divisions[$name])) {
            $division = Division::firstOrCreate(
                ['name' => $name],
                [
                    'division_id' => (string) Str::uuid(),
                    'initial' => '',
                ]
            );

            $this->divisions[$name] = $division->id;
        }

        return $this->divisions[$name];
    }

    private function resolveDesignation(string $name): ?string
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        if (! isset($this->designations[$name])) {
            $designation = Designation::firstOrCreate(
                ['name' => $name],
                ['designation_id' => (string) Str::uuid()]
            );

            $this->designations[$name] = $designation->designation_id;
        }

        return $this->designations[$name];
    }
}
