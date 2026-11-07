<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendBackDemotionRequest;
use App\Http\Requests\StoreDemotionRequest;
use App\Http\Requests\UpdateDemotionRequest;
use App\Models\Employee;
use App\Models\EmployeeDemotion;
use App\Models\EmployeeDemotionBatch;
use App\Models\User;
use App\Services\Personnel\ApprovalService;
use App\Services\Personnel\MovementApprovalWorkflowService;
use App\Services\Personnel\PersonnelActionGuard;
use App\Services\Personnel\PersonnelMovementFormDataService;
use App\Services\Personnel\PersonnelMovementSnapshotService;
use DOMDocument;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class DemotionController extends Controller
{
    public function index(
        Request $request,
        PersonnelMovementFormDataService $formDataService,
        MovementApprovalWorkflowService $workflow,
    ): View {
        Gate::authorize('viewAny', EmployeeDemotion::class);
        $perPage = $this->perPage($request);
        $reviewId = null;
        if ($request->query->has('review')) {
            $requestedReviewId = $request->query('review');
            abort_unless(is_string($requestedReviewId) && ctype_digit($requestedReviewId), 404);
            $requestedReviewId = ltrim($requestedReviewId, '0');
            $maximumId = (string) PHP_INT_MAX;
            abort_unless($requestedReviewId !== '' && (strlen($requestedReviewId) < strlen($maximumId)
                || (strlen($requestedReviewId) === strlen($maximumId) && strcmp($requestedReviewId, $maximumId) <= 0)), 404);
            $reviewedDemotion = EmployeeDemotion::query()->findOrFail($requestedReviewId);
            Gate::authorize('view', $reviewedDemotion);
            $reviewId = $reviewedDemotion->getKey();
        }

        $search = $reviewId === null ? trim((string) $request->input('search', '')) : '';
        $batchId = $reviewId === null ? ($request->integer('batch') ?: null) : null;
        $filter = $reviewId === null && in_array($request->input('status'), ['draft', 'waiting', 'returned', 'approved', 'final'], true)
            ? (string) $request->input('status')
            : 'all';
        $demotionQuery = EmployeeDemotion::query()->with(['batch:id,batch_number,name', 'employee', 'approvals.manager']);
        if ($reviewId !== null) {
            $demotionQuery->whereKey($reviewId);
        }

        if ($search !== '') {
            $demotionQuery->where(function (Builder $query) use ($search): void {
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
            $demotionQuery->where('employee_demotion_batch_id', $batchId);
        }

        if ($filter === 'draft') {
            $demotionQuery->where('verification_status', 'Draft');
        } elseif ($filter === 'waiting') {
            $demotionQuery->where('verification_status', 'Pending');
        } elseif ($filter === 'returned') {
            $demotionQuery->where('verification_status', 'Returned');
        } elseif ($filter === 'approved') {
            $demotionQuery->where('verification_status', 'Approved');
        } elseif ($filter === 'final') {
            $demotionQuery->where('verification_status', 'Final');
        }

        return view('pages.demosi', [
            'demotions' => $demotionQuery->latest('tmt')->latest()->paginate($perPage, ['*'], 'page', $reviewId === null ? null : 1)->withQueryString(),
            'reviewId' => $reviewId,
            'perPage' => $perPage,
            'totalDemotions' => EmployeeDemotion::query()->count(),
            'pendingDemotions' => EmployeeDemotion::query()->where('verification_status', 'Pending')->count(),
            'approvedDemotionsThisYear' => EmployeeDemotion::query()
                ->whereIn('verification_status', ['Approved', 'Final'])
                ->whereYear('finalized_at', now()->year)
                ->count(),
            'search' => $search,
            'filter' => $filter,
            'batchId' => $batchId,
            'demotionBatches' => EmployeeDemotionBatch::query()
                ->withCount([
                    'demotions as movements_count',
                    'demotions as draft_movements_count' => fn (Builder $query) => $query->where('verification_status', 'Draft'),
                    'demotions as pending_movements_count' => fn (Builder $query) => $query->where('verification_status', 'Pending'),
                    'demotions as returned_movements_count' => fn (Builder $query) => $query->where('verification_status', 'Returned'),
                    'demotions as completed_movements_count' => fn (Builder $query) => $query->whereIn('verification_status', ['Approved', 'Final']),
                ])
                ->latest()
                ->limit(6)
                ->get(),
            'batchOptions' => EmployeeDemotionBatch::query()->latest()->get(['id', 'batch_number', 'name']),
            'employeeOptions' => $formDataService->employees(),
            ...$formDataService->organization(),
            'approvalConfiguration' => $workflow->configuration(EmployeeDemotion::class),
            'approvalConfigurationComplete' => $workflow->isConfigurationComplete(EmployeeDemotion::class),
        ]);
    }

    public function store(StoreDemotionRequest $request, PersonnelMovementSnapshotService $snapshotService): RedirectResponse
    {
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));
        EmployeeDemotion::query()->create([
            ...$snapshotService->fromEmployee($employee, $request->validated()),
            'verification_status' => 'Draft',
        ]);

        return to_route('demosi')->with('status', 'Data demosi berhasil disimpan sebagai Draft.');
    }

    public function update(
        UpdateDemotionRequest $request,
        EmployeeDemotion $employeeDemotion,
        PersonnelActionGuard $personnelActionGuard,
        PersonnelMovementSnapshotService $snapshotService,
    ): RedirectResponse {
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));
        $personnelActionGuard->update(
            $employeeDemotion,
            $snapshotService->fromEmployee($employee, $request->validated()),
        );

        return to_route('demosi')->with('status', 'Data demosi berhasil diperbarui.');
    }

    public function destroyMany(Request $request, PersonnelActionGuard $personnelActionGuard): RedirectResponse
    {
        $validated = $request->validate([
            'demotion_ids' => ['required', 'array', 'min:1'],
            'demotion_ids.*' => ['integer', 'distinct', 'exists:employee_demotions,id'],
        ]);

        $demotions = EmployeeDemotion::query()->whereIn('id', $validated['demotion_ids'])->get();
        foreach ($demotions as $demotion) {
            Gate::authorize('delete', $demotion);
        }

        $deleted = $personnelActionGuard->deleteMany(
            EmployeeDemotion::query()->whereIn('id', $validated['demotion_ids']),
        );

        return to_route('demosi')->with('status', "Berhasil menghapus {$deleted} data demosi.");
    }

    public function approve(Request $request, EmployeeDemotion $employeeDemotion, ApprovalService $approvalService): RedirectResponse
    {
        $manager = $request->user();
        abort_unless($manager instanceof User, 403);
        Gate::authorize('approve', $employeeDemotion);

        $alreadyApproved = $approvalService->approve($employeeDemotion, $manager);

        return to_route('demosi')->with(
            'status',
            $alreadyApproved
                ? 'Anda sudah memberikan persetujuan untuk demosi ini atau demosi sudah final.'
                : 'Persetujuan demosi berhasil dicatat.',
        );
    }

    public function submit(
        Request $request,
        EmployeeDemotion $employeeDemotion,
        MovementApprovalWorkflowService $workflow,
    ): RedirectResponse {
        Gate::authorize('submit', $employeeDemotion);
        $workflow->submit($employeeDemotion, $request->user());

        return to_route('demosi')->with('status', 'Demosi berhasil diajukan ke approver Tahap 1.');
    }

    public function sendBack(
        SendBackDemotionRequest $request,
        EmployeeDemotion $employeeDemotion,
        ApprovalService $approvalService,
    ): RedirectResponse {
        $manager = $request->user();
        abort_unless($manager instanceof User, 403);

        $alreadyReturned = $approvalService->sendBack(
            $employeeDemotion,
            $manager,
            $request->string('return_note')->toString(),
        );

        return to_route('demosi')->with(
            'status',
            $alreadyReturned
                ? 'Keputusan pengembalian Anda untuk putaran ini sudah tercatat.'
                : 'Demosi dikembalikan untuk diperbaiki.',
        );
    }

    public function print(Request $request, EmployeeDemotion $employeeDemotion): BinaryFileResponse
    {
        Gate::authorize('print', $employeeDemotion);

        $templatePath = storage_path('template/demosi-template.docx');
        abort_unless(file_exists($templatePath), 404, 'Template Word belum tersedia.');

        $temporaryPath = tempnam(sys_get_temp_dir(), 'demosi_docx_');
        if ($temporaryPath === false) {
            abort(500, 'Tidak dapat membuat file dokumen sementara.');
        }

        $zip = new ZipArchive;
        if ($zip->open($templatePath, ZipArchive::RDONLY) !== true) {
            abort(500, 'Template Word tidak dapat dibuka.');
        }

        $outputZip = new ZipArchive;
        $outputZip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            $content = $zip->getFromName($name);
            if ($content === false) {
                continue;
            }

            if ($name === 'word/document.xml') {
                $content = $this->replaceTemplatePlaceholders($content, $employeeDemotion);
            }

            $outputZip->addFromString($name, $content);
        }

        $zip->close();
        $outputZip->close();

        return response()->download(
            $temporaryPath,
            'demosi-'.$employeeDemotion->sap.'-'.now()->format('YmdHis').'.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        )->deleteFileAfterSend();
    }

    private function replaceTemplatePlaceholders(string $xmlContent, EmployeeDemotion $employeeDemotion): string
    {
        $document = new DOMDocument;
        abort_unless($document->loadXML($xmlContent, LIBXML_NONET), 500, 'Isi template Word tidak valid.');

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        foreach ($xpath->query('//w:p') as $paragraph) {
            $textNodes = $xpath->query('.//w:t', $paragraph);
            $paragraphText = '';
            foreach ($textNodes as $textNode) {
                $paragraphText .= $textNode->textContent;
            }

            if (str_contains(strtolower($paragraphText), 'promosi')) {
                $this->replacePlaceholderInTextNodes($textNodes, 'promosi', 'demosi');
            }

            foreach ([
                '[PEGAWAI]' => $employeeDemotion->nama,
                '[SAP]' => $employeeDemotion->sap,
                '[DIREKTORAT LAMA]' => $employeeDemotion->direktorat_lama ?? '',
                '[DEPARTEMEN LAMA]' => $employeeDemotion->departemen_lama,
                '[UNIT LAMA]' => $employeeDemotion->unit_lama ?? '',
                '[SECTION LAMA]' => $employeeDemotion->section_lama ?? '',
                '[JABATAN LAMA]' => $employeeDemotion->jabatan_lama,
                '[DIREKTORAT BARU]' => $employeeDemotion->direktorat_baru ?? '',
                '[DEPARTEMEN BARU]' => $employeeDemotion->departemen_baru,
                '[UNIT BARU]' => $employeeDemotion->unit_baru ?? '',
                '[SECTION BARU]' => $employeeDemotion->section_baru ?? '',
                '[JABATAN BARU]' => $employeeDemotion->jabatan_baru,
                '[TMT]' => $employeeDemotion->tmt?->format('d-m-Y') ?? '',
                '[PG]' => $employeeDemotion->pg,
                '[LEVEL LAMA]' => $employeeDemotion->level_lama,
                '[JG LAMA]' => $employeeDemotion->jg_lama,
                '[LEVEL BARU]' => $employeeDemotion->level_baru,
                '[JG BARU]' => $employeeDemotion->jg_baru,
            ] as $placeholder => $replacement) {
                $this->replacePlaceholderInTextNodes($textNodes, $placeholder, $replacement);
            }
        }

        $this->appendLevelSummary($document, $xpath, $employeeDemotion);

        $updatedXml = $document->saveXML();
        abort_unless($updatedXml !== false, 500, 'Template Word tidak dapat diproses.');

        return $updatedXml;
    }

    private function appendLevelSummary(
        DOMDocument $document,
        DOMXPath $xpath,
        EmployeeDemotion $employeeDemotion,
    ): void {
        $body = $xpath->query('//w:body')->item(0);
        abort_unless($body instanceof DOMNode, 500, 'Struktur isi template Word demosi tidak valid.');
        $sectionProperties = $xpath->query('./w:sectPr', $body)->item(0);

        foreach ([
            'Level Lama: '.$employeeDemotion->level_lama,
            'Level Baru: '.$employeeDemotion->level_baru,
        ] as $line) {
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
            $paragraphText = implode('', array_map(static fn ($node): string => $node->textContent, $nodes));
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
                    .$replacement
                    .substr($startText, $endOffset);
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
