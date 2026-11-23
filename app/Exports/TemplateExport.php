<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['tanggal', 'tipe', 'akun', 'akun_tujuan', 'kategori', 'nama', 'qty', 'satuan', 'jumlah', 'keterangan'];
    }

    public function array(): array
    {
        return [
            ['02/11/2026', 'pengeluaran', 'Cash', '', 'Dapur', 'Lele', 2, 'kg', '46 K', 'contoh pengeluaran'],
            ['03/11/2026', 'pemasukan', 'BCA', '', 'Gaji', 'Gaji November', '', '', '5.000.000', ''],
            ['04/11/2026', 'pindah', 'BCA', 'Cash', 'Pindah', 'Tarik tunai', '', '', '500 K', ''],
        ];
    }
}
