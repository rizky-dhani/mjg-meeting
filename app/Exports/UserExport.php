<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exports users in the user_import_template.xlsx layout so the file can be
 * re-imported with UserImport.
 */
class UserExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function collection(): Collection
    {
        return User::with(['division', 'designation'])->get();
    }

    public function headings(): array
    {
        return ['Nama', 'NIK', 'Initial Name', 'Division', 'Designation'];
    }

    public function map($user): array
    {
        return [
            $user->name,
            $user->employee_code,
            $user->initial,
            $user->division?->name,
            $user->designation?->name,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
