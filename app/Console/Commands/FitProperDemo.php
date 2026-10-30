<?php

namespace App\Console\Commands;

use App\Models\FitProperActivity;
use App\Models\User;
use App\Services\FitProperCalculationService;
use App\Services\FitProperService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FitProperDemo extends Command
{
    protected $signature = 'fit-proper:demo {--admin= : Email admin/PIC yang sudah ada}';

    protected $description = 'Buat tiga kegiatan Fit & Proper fiktif secara idempoten (local/testing saja)';

    public function handle(FitProperService $service): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Command demo hanya boleh dijalankan pada local/testing.');

            return self::FAILURE;
        }
        $admins = User::query()->whereIn('role', ['Super Admin', 'Admin', 'Manager']);
        $admin = $this->option('admin') ? $admins->where('email', $this->option('admin'))->first() : $admins->orderBy('id')->first();
        if (! $admin) {
            $this->error('Diperlukan akun admin yang sudah ada. Gunakan --admin=email. Tidak ada akun atau master pegawai fiktif yang dibuat.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($admin, $service) {
            User::whereKey($admin->id)->lockForUpdate()->firstOrFail();
            foreach (['Draft', 'Berjalan', 'Selesai'] as $status) {
                $key = 'fit-proper-demo-v1-'.strtolower($status);
                if (FitProperActivity::where('demo_key', $key)->exists()) {
                    continue;
                }
                $activityRecord = $service->create(['name' => 'SIMULASI FIKTIF — Fit & Proper '.$status,
                    'target_position' => 'Kepala Unit Fiktif', 'department' => 'Unit Simulasi',
                    'scheduled_at' => today()->toDateString(), 'description' => 'Data Simulasi. Seluruh identitas dan nilai adalah fiktif.',
                    'is_simulation' => true, 'demo_key' => $key], $admin);
                $service->assignPics($activityRecord, [$admin->id], $admin);
                $participants = [];
                for ($i = 1; $i <= 5; $i++) {
                    $participants[] = $service->participant($activityRecord, [
                        'name' => 'Kandidat Fiktif '.['Alfa', 'Beta', 'Gama', 'Delta', 'Epsilon'][$i - 1],
                        'sap' => null, 'birth_date' => '1990-01-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                        'department' => 'Unit Fiktif', 'current_position' => 'Staf Simulasi', 'target_position' => 'Kepala Unit Fiktif',
                        'current_jg' => 12, 'target_jg' => 10, 'education' => 'S1 Fiktif',
                        'education_background' => 'Jurusan Simulasi', 'experience' => 'Pengalaman fiktif '.$i.' tahun',
                        'training' => 'Pelatihan simulasi', 'achievement' => 'Prestasi contoh',
                        'kpi_year_1' => $activityRecord->standard['max_kpi'] * (1 - $i * 0.1),
                        'kpi_year_2' => $status === 'Draft' && $i === 5 ? null : $activityRecord->standard['max_kpi'] * (1 - $i * 0.1),
                        'assessment' => $status === 'Draft' && $i === 5 ? null : $activityRecord->standard['max_assessment'] * (1 - $i * 0.12),
                        'assessment_notes' => 'Narasi asesmen fiktif untuk demonstrasi.',
                    ], null, $admin);
                }
                $examiners = [];
                for ($j = 1; $j <= 3; $j++) {
                    $examiners[] = $service->examiner($activityRecord, ['name' => 'Penguji Fiktif '.$j], $admin);
                }
                if ($status !== 'Draft') {
                    $service->transition($activityRecord, 'Berjalan', $admin);
                    foreach ($examiners as $j => $examinerRecord) {
                        $scores = [];
                        foreach ($participants as $i => $participantRecord) {
                            $value = max(1, 4.8 - $i * 0.9 - $j * 0.1);
                            $scores[$participantRecord->id] = array_fill_keys(array_keys(FitProperCalculationService::INDICATORS), $value) +
                                ['notes' => 'Catatan fiktif', 'recommendation' => 'Rekomendasi simulasi'];
                        }
                        if ($status === 'Berjalan' && $j === 2) {
                            $scores[$participants[4]->id]['conflict'] = null;
                            $service->saveScores($examinerRecord, ['scores' => $scores], false, $admin);
                        } else {
                            $service->saveScores($examinerRecord, ['scores' => $scores], true, $admin);
                        }
                    }
                    if ($status === 'Selesai') {
                        $service->transition($activityRecord, 'Selesai', $admin);
                    }
                }
            }
        });
        $this->info('Simulasi tersedia: Draft, Berjalan, Selesai; masing-masing 5 peserta dan 3 penguji. Data lama tidak diubah.');

        return self::SUCCESS;
    }
}
