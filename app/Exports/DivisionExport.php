<?php

namespace App\Exports;

use App\Models\Division;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DivisionExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function collection()
    {
        return Division::withCount('users')->get();
    }

    public function headings(): array
    {
        return ['Division ID', 'Name', 'Initial', 'Users Count', 'Created At'];
    }

    public function map($division): array
    {
        return [
            $division->division_id,
            $division->name,
            $division->initial,
            $division->users_count,
            $division->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
