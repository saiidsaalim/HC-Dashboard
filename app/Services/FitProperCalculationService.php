<?php

namespace App\Services;

use App\Models\FitProperActivity;

class FitProperCalculationService
{
    public const INDICATORS = [
        'adaptability' => 'Adaptability', 'authenticity' => 'Authenticity',
        'contributing' => 'Contributing to Society', 'learning' => 'Learning Agility',
        'diversity' => 'Valuing Diversity', 'job_mastery' => 'Penguasaan pekerjaan',
        'improvement' => 'Rencana improvement', 'anti_bribery' => 'Anti Suap',
        'anti_gratuity' => 'Anti Gratifikasi', 'conflict' => 'Benturan Kepentingan',
    ];

    /**
     * @param  array<string, float|int|string|null>  $values
     * @return array{core: float, eligibility: float, average: float, guidance: string}|null
     */
    public function examinerAverage(array $values): ?array
    {
        foreach (array_keys(self::INDICATORS) as $key) {
            if (! isset($values[$key]) || ! is_numeric($values[$key]) || $values[$key] < 0 || $values[$key] > 5) {
                return null;
            }
        }
        $core = array_sum(array_map(fn ($key) => (float) $values[$key], array_slice(array_keys(self::INDICATORS), 0, 5))) / 5;
        $eligibility = ($values['anti_bribery'] + $values['anti_gratuity'] + $values['conflict']) / 3;
        $average = ($core + $values['job_mastery'] + $values['improvement'] + $eligibility) / 4;

        return ['core' => $core, 'eligibility' => $eligibility, 'average' => $average,
            'guidance' => $average < 2.7 ? 'Tidak Disarankan' : ($average <= 3 ? 'Disarankan Dengan Pertimbangan' : 'Disarankan')];
    }

    /**
     * @return array{complete: bool, zero_max: bool, max_raw: float|int, standard: array<string, float|int>, rows: list<array<string, mixed>>, precision: int, normalization: string}
     */
    public function recap(FitProperActivity $activity): array
    {
        $activity->load(['participants.scores', 'examiners']);
        $rows = [];
        $complete = $activity->participants->isNotEmpty() && $activity->examiners->isNotEmpty();
        foreach ($activity->participants as $participant) {
            $details = [];
            $averages = [];
            foreach ($activity->examiners as $examiner) {
                $score = $participant->scores->firstWhere('examiner_id', $examiner->id);
                $calculation = $score ? $this->examinerAverage($score->indicators) : null;
                $submitted = $examiner->submitted_at !== null && $calculation !== null;
                $details[] = ['examiner_id' => $examiner->id, 'examiner_name' => $examiner->name, 'user_id' => $examiner->user_id,
                    'submitted' => $submitted, 'indicators' => $score?->indicators,
                    'recommendation' => $score?->recommendation, 'notes' => $score?->notes, 'calculation' => $calculation];
                if ($submitted) {
                    $averages[] = $calculation['average'];
                }
            }
            $participantAverage = count($averages) === $activity->examiners->count() && count($averages) > 0 ? array_sum($averages) / count($averages) : null;
            $participantComplete = $participantAverage !== null && $participant->kpi() !== null && $participant->assessment !== null;
            $complete = $complete && $participantComplete;
            $rows[] = ['id' => $participant->id, 'name' => $participant->name, 'sap' => $participant->sap, 'internal_identity' => $participant->internal_identity,
                'birth_date' => $participant->birth_date?->toDateString(), 'profile' => $participant->profile,
                'kpi_year_1' => $participant->kpi_year_1, 'kpi_year_2' => $participant->kpi_year_2,
                'kpi' => $participant->kpi(), 'assessment' => $participant->assessment, 'raw' => $participantAverage,
                'submitted_count' => count($averages), 'details' => $details, 'complete' => $participantComplete];
        }
        $maximumAverage = max(array_merge([0], array_column($rows, 'raw')));
        $complete = $complete && $maximumAverage > 0;
        $standard = $activity->standard;
        foreach ($rows as &$row) {
            $row['fp'] = $maximumAverage > 0 && $row['raw'] !== null ? $row['raw'] / $maximumAverage : null;
            $row['contribution_kpi'] = $row['kpi'] === null ? null : $row['kpi'] / $standard['max_kpi'] * $standard['weight_kpi'] * 100;
            $row['contribution_assessment'] = $row['assessment'] === null ? null : $row['assessment'] / $standard['max_assessment'] * $standard['weight_assessment'] * 100;
            $row['contribution_fp'] = $row['fp'] === null ? null : $row['fp'] * $standard['weight_fp'] * 100;
            $row['total'] = $row['complete'] && $row['fp'] !== null ? $row['contribution_kpi'] + $row['contribution_assessment'] + $row['contribution_fp'] : null;
            $row['passed'] = $complete ? round($row['total'], 6) >= $standard['passing_grade'] : null;
            $row['rank_fp'] = null;
            $row['rank_total'] = null;
        }
        unset($row);
        if ($complete) {
            foreach (['fp' => 'rank_fp', 'total' => 'rank_total'] as $metric => $rankKey) {
                foreach ($rows as &$row) {
                    $row[$rankKey] = 1 + count(array_filter($rows, fn ($other) => round($other[$metric], 6) > round($row[$metric], 6)));
                }
                unset($row);
            }
            usort($rows, fn ($a, $b) => $a['rank_total'] <=> $b['rank_total']);
        }

        return ['complete' => $complete, 'zero_max' => $maximumAverage <= 0, 'max_raw' => $maximumAverage, 'standard' => $standard, 'rows' => $rows,
            'precision' => 6, 'normalization' => 'Nilai FP dinormalisasi terhadap rata-rata peserta tertinggi dalam kegiatan. Hasil sementara dapat berubah.'];
    }
}
