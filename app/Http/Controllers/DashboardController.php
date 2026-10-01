<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'stats' => [
                ['label' => 'Fit & Proper', 'value' => 128, 'description' => 'Kandidat dalam proses penilaian', 'icon' => 'clipboard', 'tone' => 'amber'],
                ['label' => 'Mutasi', 'value' => 46, 'description' => 'Pengajuan mutasi tahun ini', 'icon' => 'arrows', 'tone' => 'sky'],
                ['label' => 'Promosi', 'value' => 32, 'description' => 'Promosi yang sedang berjalan', 'icon' => 'chart', 'tone' => 'emerald'],
                ['label' => 'Laporan', 'value' => 18, 'description' => 'Laporan tersedia untuk ditinjau', 'icon' => 'document', 'tone' => 'violet'],
            ],
        ]);
    }
}