<?php

namespace App\Http\Controllers;

use App\Models\FitProperParticipant;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'stats' => [
                ['label' => 'Fit & Proper', 'value' => Schema::hasTable('fit_proper_participants') ? FitProperParticipant::whereHas('activity', fn ($query) => $query->where('is_simulation', false)->where('status', 'Berjalan'))->count() : 0, 'description' => 'Kandidat aktif, tanpa data simulasi', 'icon' => 'clipboard', 'tone' => 'amber'],
                ['label' => 'Mutasi', 'value' => 46, 'description' => 'Pengajuan mutasi tahun ini', 'icon' => 'arrows', 'tone' => 'sky'],
                ['label' => 'Promosi', 'value' => 32, 'description' => 'Promosi yang sedang berjalan', 'icon' => 'chart', 'tone' => 'emerald'],
                ['label' => 'Laporan', 'value' => 18, 'description' => 'Laporan tersedia untuk ditinjau', 'icon' => 'document', 'tone' => 'violet'],
            ],
        ]);
    }
}
