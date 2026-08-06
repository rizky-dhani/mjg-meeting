<?php

namespace App\Imports;

use App\Models\Division;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class DivisionImport implements ToCollection, WithHeadingRow, WithValidation
{
    public function collection(Collection $rows): void
    {
        $divisions = $rows->map(fn ($row) => [
            'division_id' => $row['division_id'] ?? \Illuminate\Support\Str::uuid(),
            'name' => $row['name'],
            'initial' => $row['initial'] ?? '',
        ])->toArray();

        Division::upsert($divisions, ['division_id', 'initial'], ['name']);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'initial' => ['required', 'string', 'max:50'],
        ];
    }
}
