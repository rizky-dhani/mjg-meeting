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
        foreach ($rows as $row) {
            Division::firstOrCreate(
                ['name' => $row['name']],
                [
                    'division_id' => \Illuminate\Support\Str::uuid(),
                    'initial' => $row['initial'] ?? '',
                ]
            );
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'initial' => ['required', 'string', 'max:50'],
        ];
    }
}
