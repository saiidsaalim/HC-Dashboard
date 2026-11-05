<?php

namespace App\Http\Controllers;

use App\Enums\PromotionApprovalStatus;
use App\Enums\PromotionStatus;
use App\Http\Requests\SendBackPromotionRequest;
use App\Http\Requests\StorePromotionRequest;
use App\Http\Requests\UpdatePromotionRequest;
use App\Models\Employee;
use App\Models\EmployeePromotion;
use App\Models\EmployeePromotionBatch;
use App\Models\PromotionApproval;
use App\Services\Personnel\PersonnelActionGuard;
use App\Services\Personnel\PersonnelMovementFormDataService;
use App\Services\Personnel\PersonnelMovementSnapshotService;
use App\Services\Personnel\PromotionApprovalWorkflowService;
use DOMDocument;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class PromotionController extends Controller
{
    /** @var array<int, string> */
    public function index(
        Request $request,
        PromotionApprovalWorkflowService $workflow,
        PersonnelMovementFormDataService $formDataService,
    ): View {
        Gate::authorize('viewAny', EmployeePromotion::class);

        $search = trim((string) $request->input('search', ''));
        $batchId = $request->integer('batch') ?: null;
        $filter = (string) $request->input('status', 'all');
        if (! in_array($filter, ['all', 'draft', 'waiting', 'returned', 'approved', 'final', 'mine'], true)) {
            $filter = 'all';
        }

        $promotionQuery = EmployeePromotion::query()
            ->with(['batch:id,batch_number,name', 'approvals.manager', 'employee', 'submitter']);

        if ($search !== '') {
            $promotionQuery->where(function (Builder $query) use ($search): void {
                foreach ([
                    'sap', 'nama', 'departemen_lama', 'jabatan_lama', 'departemen_baru', 'jabatan_baru',
                    'pg', 'level_lama', 'jg_lama', 'level_baru', 'jg_baru',
                ] as $column) {
                    $query->orWhere($column, 'like', "%{$search}%");
                }

                $query->orWhereHas('batch', fn (Builder $batchQuery) => $batchQuery
                    ->where('batch_number', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%"));
            });
        }

        if ($batchId !== null) {
            $promotionQuery->where('employee_promotion_batch_id', $batchId);
        }

        match ($filter) {
            'draft' => $promotionQuery->whereIn('verification_status', [
                PromotionStatus::Draft->value,
                PromotionStatus::LegacyPending->value,
            ]),
            'waiting' => $promotionQuery->where('verification_status', PromotionStatus::WaitingApproval->value),
            'returned' => $promotionQuery->where('verification_status', PromotionStatus::Returned->value),
            'approved' => $promotionQuery->where('verification_status', PromotionStatus::Approved->value),
            'final' => $promotionQuery->where('verification_status', PromotionStatus::Final->value),
            'mine' => $promotionQuery
                ->where('verification_status', PromotionStatus::WaitingApproval->value)
                ->whereHas('approvals', fn (Builder $query) => $query
                    ->where('manager_id', $request->user()->id)
                    ->where('status', PromotionApprovalStatus::Pending->value)
                    ->whereColumn('promotion_approvals.approval_round', 'employee_promotions.approval_round')
                    ->whereColumn('promotion_approvals.step_order', 'employee_promotions.current_approval_step')),
            default => null,
        };

        $canManage = $request->user()->can('create', EmployeePromotion::class);

        return view('pages.promosi', [
            'promotions' => $promotionQuery->latest('tmt')->latest()->paginate(15)->withQueryString(),
            'totalPromotions' => EmployeePromotion::query()->count(),
            'pendingPromotions' => EmployeePromotion::query()->whereIn('verification_status', [
                PromotionStatus::WaitingApproval->value,
                PromotionStatus::LegacyPending->value,
            ])->count(),
            'approvedThisYear' => EmployeePromotion::query()
                ->where('verification_status', PromotionStatus::Final->value)
                ->whereYear('finalized_at', now()->year)
                ->count(),
            'search' => $search,
            'filter' => $filter,
            'batchId' => $batchId,
            'promotionBatches' => EmployeePromotionBatch::query()
                ->withCount([
                    'promotions as movements_count',
                    'promotions as draft_movements_count' => fn (Builder $query) => $query->where('verification_status', PromotionStatus::Draft->value),
                    'promotions as pending_movements_count' => fn (Builder $query) => $query->where('verification_status', PromotionStatus::WaitingApproval->value),
                    'promotions as returned_movements_count' => fn (Builder $query) => $query->where('verification_status', PromotionStatus::Returned->value),
                    'promotions as completed_movements_count' => fn (Builder $query) => $query->whereIn('verification_status', [PromotionStatus::Approved->value, PromotionStatus::Final->value]),
                ])
                ->latest()
                ->limit(6)
                ->get(),
            'batchOptions' => EmployeePromotionBatch::query()->latest()->get(['id', 'batch_number', 'name']),
            'employeeOptions' => $canManage ? $formDataService->employees() : collect(),
            ...($canManage ? $formDataService->organization() : []),
            'approvalConfiguration' => $workflow->configuration(),
            'approvalConfigurationComplete' => $workflow->isConfigurationComplete(),
            'approvalSteps' => range(1, PromotionApprovalWorkflowService::REQUIRED_STEPS),
            'requiredApprovalSteps' => PromotionApprovalWorkflowService::REQUIRED_STEPS,
        ]);
    }

    public function store(
        StorePromotionRequest $request,
        PersonnelMovementSnapshotService $snapshotService,
    ): RedirectResponse {
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));
        EmployeePromotion::query()->create([
            ...$snapshotService->fromEmployee($employee, $request->validated()),
            'verification_status' => PromotionStatus::Draft->value,
            'approval_round' => 0,
            'current_approval_step' => null,
            'finalized_at' => null,
        ]);

        return to_route('promosi')->with('status', 'Data promosi berhasil disimpan sebagai Draft.');
    }

    public function update(
        UpdatePromotionRequest $request,
        EmployeePromotion $employeePromotion,
        PersonnelActionGuard $personnelActionGuard,
        PersonnelMovementSnapshotService $snapshotService,
    ): RedirectResponse {
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));

        $personnelActionGuard->update(
            $employeePromotion,
            $snapshotService->fromEmployee($employee, $request->validated()),
        );

        return to_route('promosi')->with('status', 'Data promosi berhasil diperbarui.');
    }

    public function destroyMany(Request $request, PersonnelActionGuard $personnelActionGuard): RedirectResponse
    {
        Gate::authorize('promotions.delete');

        $validated = $request->validate([
            'promotion_ids' => ['required', 'array', 'min:1'],
            'promotion_ids.*' => ['integer', 'distinct', 'exists:employee_promotions,id'],
        ]);

        $promotions = EmployeePromotion::query()->whereIn('id', $validated['promotion_ids'])->get();
        foreach ($promotions as $promotion) {
            Gate::authorize('delete', $promotion);
        }

        $deleted = $personnelActionGuard->deleteMany(
            EmployeePromotion::query()->whereIn('id', $validated['promotion_ids']),
        );

        return to_route('promosi')->with('status', "Berhasil menghapus {$deleted} data promosi.");
    }

    public function submit(
        Request $request,
        EmployeePromotion $employeePromotion,
        PromotionApprovalWorkflowService $workflow,
    ): RedirectResponse {
        Gate::authorize('submit', $employeePromotion);
        $workflow->submit($employeePromotion, $request->user());

        return to_route('promosi')->with('status', 'Promosi berhasil diajukan ke approver Tahap 1.');
    }

    public function approve(
        Request $request,
        EmployeePromotion $employeePromotion,
        PromotionApprovalWorkflowService $workflow,
    ): RedirectResponse {
        Gate::authorize('approve', $employeePromotion);
        $alreadyDecided = $workflow->approve($employeePromotion, $request->user());

        return to_route('promosi')->with(
            'status',
            $alreadyDecided ? 'Keputusan Anda untuk putaran ini sudah tercatat.' : 'Persetujuan Promosi berhasil dicatat.',
        );
    }

    public function sendBack(
        SendBackPromotionRequest $request,
        EmployeePromotion $employeePromotion,
        PromotionApprovalWorkflowService $workflow,
    ): RedirectResponse {
        $alreadyDecided = $workflow->sendBack(
            $employeePromotion,
            $request->user(),
            $request->string('return_note')->toString(),
        );

        return to_route('promosi')->with(
            'status',
            $alreadyDecided
                ? 'Keputusan pengembalian Anda untuk putaran ini sudah tercatat.'
                : 'Promosi dikembalikan kepada Super Admin untuk diperbaiki.',
        );
    }

    public function print(Request $request, EmployeePromotion $employeePromotion): BinaryFileResponse
    {
        Gate::authorize('print', $employeePromotion);
        $employeePromotion->load([
            'approvals' => fn (HasMany $query): HasMany => $query
                ->where('approval_round', $employeePromotion->approval_round)
                ->where('status', PromotionApprovalStatus::Approved->value)
                ->orderBy('step_order'),
        ]);

        $templatePath = storage_path('template/promosi-template.docx');
        abort_unless(file_exists($templatePath), 404, 'Template Word promosi belum tersedia.');

        $temporaryPath = tempnam(sys_get_temp_dir(), 'promosi_docx_');
        abort_unless($temporaryPath !== false, 500, 'Tidak dapat membuat file dokumen sementara.');

        $zip = new ZipArchive;
        abort_unless($zip->open($templatePath, ZipArchive::RDONLY) === true, 500, 'Template Word tidak dapat dibuka.');

        $outputZip = new ZipArchive;
        abort_unless(
            $outputZip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true,
            500,
            'Dokumen Promosi tidak dapat dibuat.',
        );

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            $content = $zip->getFromName($name);

            if ($content === false) {
                continue;
            }

            if (str_ends_with($name, '.xml')) {
                $content = $this->replaceTemplatePlaceholders(
                    $content,
                    $employeePromotion,
                    $name === 'word/document.xml',
                );
            }

            $outputZip->addFromString($name, $content);
        }

        $zip->close();
        $outputZip->close();

        $filename = Str::slug(
            'promosi-'.$employeePromotion->sap.'-'.$employeePromotion->nama.'-'.$employeePromotion->tmt->format('Y-m-d'),
        ).'.docx';

        return response()->download(
            $temporaryPath,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        )->deleteFileAfterSend();
    }

    private function replaceTemplatePlaceholders(
        string $xmlContent,
        EmployeePromotion $promotion,
        bool $appendSummary,
    ): string {
        $document = new DOMDocument;
        abort_unless($document->loadXML($xmlContent, LIBXML_NONET), 500, 'Isi template Word promosi tidak valid.');

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $approvals = $promotion->approvals->keyBy('step_order');
        $replacements = [
            '[PEGAWAI]' => $promotion->nama,
            '[SAP]' => $promotion->sap,
            '[DIREKTORAT LAMA]' => $promotion->direktorat_lama ?? '',
            '[DEPARTEMEN LAMA]' => $promotion->departemen_lama,
            '[UNIT LAMA]' => $promotion->unit_lama ?? '',
            '[SECTION LAMA]' => $promotion->section_lama ?? '',
            '[JABATAN LAMA]' => $promotion->jabatan_lama,
            '[DIREKTORAT BARU]' => $promotion->direktorat_baru ?? '',
            '[DEPARTEMEN BARU]' => $promotion->departemen_baru,
            '[UNIT BARU]' => $promotion->unit_baru ?? '',
            '[SECTION BARU]' => $promotion->section_baru ?? '',
            '[JABATAN BARU]' => $promotion->jabatan_baru,
            '[TMT]' => $promotion->tmt->format('d-m-Y'),
            '[PG]' => $promotion->pg,
            '[LEVEL LAMA]' => $promotion->level_lama,
            '[JG LAMA]' => $promotion->jg_lama,
            '[LEVEL BARU]' => $promotion->level_baru,
            '[JG BARU]' => $promotion->jg_baru,
            '[STATUS]' => PromotionStatus::Final->value,
            '[TANGGAL FINAL]' => $promotion->finalized_at?->format('d-m-Y') ?? '',
        ];

        foreach (range(1, PromotionApprovalWorkflowService::REQUIRED_STEPS) as $step) {
            $approval = $approvals->get($step);
            $replacements["[APPROVER {$step}]"] = $approval?->approver_name_snapshot ?? '';
            $replacements["[NOMOR APPROVER {$step}]"] = $approval?->approver_number_snapshot ?? '';
            $replacements["[ROLE APPROVER {$step}]"] = $approval?->approver_role_snapshot ?? '';
            $replacements["[TANGGAL APPROVAL {$step}]"] = $approval?->approved_at?->format('d-m-Y') ?? '';
        }

        foreach ($xpath->query('//w:p') as $paragraph) {
            $textNodes = $xpath->query('.//w:t', $paragraph);

            foreach ($replacements as $placeholder => $replacement) {
                $this->replacePlaceholderInTextNodes($textNodes, $placeholder, $replacement);
            }
        }

        if ($appendSummary) {
            $this->appendPromotionSummary($document, $xpath, $promotion, $approvals);
        }

        $updatedXml = $document->saveXML();
        abort_unless($updatedXml !== false, 500, 'Template Word promosi tidak dapat diproses.');

        return $updatedXml;
    }

    /** @param Collection<int, PromotionApproval> $approvals */
    private function appendPromotionSummary(
        DOMDocument $document,
        DOMXPath $xpath,
        EmployeePromotion $promotion,
        Collection $approvals,
    ): void {
        $body = $xpath->query('//w:body')->item(0);
        abort_unless($body instanceof DOMNode, 500, 'Struktur isi template Word promosi tidak valid.');
        $sectionProperties = $xpath->query('./w:sectPr', $body)->item(0);
        $lines = [
            'RINGKASAN PROMOSI',
            'Nama Pegawai: '.$promotion->nama,
            'SAP: '.$promotion->sap,
            'Department Lama: '.$promotion->departemen_lama,
            'Jabatan Saat Ini: '.$promotion->jabatan_lama,
            'Department Baru: '.$promotion->departemen_baru,
            'Jabatan Usulan: '.$promotion->jabatan_baru,
            'TMT: '.$promotion->tmt->format('d-m-Y'),
            'PG: '.$promotion->pg,
            'Level Lama: '.$promotion->level_lama,
            'JG Saat Ini: '.$promotion->jg_lama,
            'Level Baru: '.$promotion->level_baru,
            'JG Usulan: '.$promotion->jg_baru,
            'Status: '.PromotionStatus::Final->value,
            'Tanggal Final: '.($promotion->finalized_at?->format('d-m-Y H:i') ?? '-'),
            'PERSETUJUAN PUTARAN FINAL',
        ];

        foreach ($approvals->sortKeys() as $step => $approval) {
            $lines[] = sprintf(
                'Tahap %d: %s | %s | %s | %s',
                $step,
                $approval?->approver_name_snapshot ?? 'Tidak tercatat',
                $approval?->approver_number_snapshot ?? 'Nomor tidak tercatat',
                $approval?->approver_role_snapshot ?? 'Role tidak tercatat',
                $approval?->approved_at?->format('d-m-Y H:i') ?? 'Waktu tidak tercatat',
            );
        }

        foreach ($lines as $line) {
            $paragraph = $document->createElementNS(
                'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
                'w:p',
            );
            $run = $document->createElementNS(
                'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
                'w:r',
            );
            $text = $document->createElementNS(
                'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
                'w:t',
            );
            $text->setAttribute('xml:space', 'preserve');
            $text->textContent = $line;
            $run->appendChild($text);
            $paragraph->appendChild($run);

            if ($sectionProperties instanceof DOMNode) {
                $body->insertBefore($paragraph, $sectionProperties);
            } else {
                $body->appendChild($paragraph);
            }
        }
    }

    private function replacePlaceholderInTextNodes(DOMNodeList $textNodes, string $placeholder, string $replacement): void
    {
        $nodes = [];
        foreach ($textNodes as $textNode) {
            $nodes[] = $textNode;
        }

        $searchFrom = 0;
        while (true) {
            $paragraphText = implode('', array_map(static fn (DOMNode $node): string => $node->textContent, $nodes));
            $position = strpos($paragraphText, $placeholder, $searchFrom);
            if ($position === false) {
                return;
            }

            $endPosition = $position + strlen($placeholder);
            $cursor = 0;
            $startNodeIndex = null;
            $endNodeIndex = null;
            $startOffset = 0;
            $endOffset = 0;

            foreach ($nodes as $index => $node) {
                $nodeLength = strlen($node->textContent);
                if ($startNodeIndex === null && $position < $cursor + $nodeLength) {
                    $startNodeIndex = $index;
                    $startOffset = $position - $cursor;
                }

                if ($endPosition > $cursor && $endPosition <= $cursor + $nodeLength) {
                    $endNodeIndex = $index;
                    $endOffset = $endPosition - $cursor;
                    break;
                }

                $cursor += $nodeLength;
            }

            if ($startNodeIndex === null || $endNodeIndex === null) {
                return;
            }

            $startText = $nodes[$startNodeIndex]->textContent;
            if ($startNodeIndex === $endNodeIndex) {
                $nodes[$startNodeIndex]->textContent = substr($startText, 0, $startOffset)
                    .$replacement.substr($startText, $endOffset);
            } else {
                $nodes[$startNodeIndex]->textContent = substr($startText, 0, $startOffset).$replacement;
                for ($index = $startNodeIndex + 1; $index < $endNodeIndex; $index++) {
                    $nodes[$index]->textContent = '';
                }
                $nodes[$endNodeIndex]->textContent = substr($nodes[$endNodeIndex]->textContent, $endOffset);
            }

            $searchFrom = $position + max(strlen($replacement), 1);
        }
    }
}
