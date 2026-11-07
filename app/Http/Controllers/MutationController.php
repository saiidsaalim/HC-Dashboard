<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendBackMutationRequest;
use App\Http\Requests\StoreMutationRequest;
use App\Http\Requests\UpdateMutationRequest;
use App\Models\Employee;
use App\Models\EmployeeMutation;
use App\Models\EmployeeMutationBatch;
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

class MutationController extends Controller
{
    public function index(
        Request $request,
        PersonnelMovementFormDataService $formDataService,
        MovementApprovalWorkflowService $workflow,
    ): View {
        Gate::authorize('viewAny', EmployeeMutation::class);
        $perPage = $this->perPage($request);
        $reviewId = null;
        if ($request->query->has('review')) {
            $requestedReviewId = $request->query('review');
            abort_unless(is_string($requestedReviewId) && ctype_digit($requestedReviewId), 404);
            $requestedReviewId = ltrim($requestedReviewId, '0');
            $maximumId = (string) PHP_INT_MAX;
            abort_unless($requestedReviewId !== '' && (strlen($requestedReviewId) < strlen($maximumId)
                || (strlen($requestedReviewId) === strlen($maximumId) && strcmp($requestedReviewId, $maximumId) <= 0)), 404);
            $reviewedMutation = EmployeeMutation::query()->findOrFail($requestedReviewId);
            Gate::authorize('view', $reviewedMutation);
            $reviewId = $reviewedMutation->getKey();
        }

        $search = $reviewId === null ? trim((string) $request->input('search', '')) : '';
        $filter = $reviewId === null && in_array($request->input('status'), ['draft', 'waiting', 'returned', 'approved', 'final'], true)
            ? (string) $request->input('status')
            : 'all';
        $batchId = $reviewId === null ? ($request->integer('batch') ?: null) : null;
        $mutationQuery = EmployeeMutation::query()->with(['batch:id,batch_number,name', 'employee', 'approvals.manager']);
        if ($reviewId !== null) {
            $mutationQuery->whereKey($reviewId);
        }

        if ($search !== '') {
            $mutationQuery->where(function (Builder $query) use ($search): void {
                foreach ([
                    'sap', 'nama', 'departemen_lama', 'jabatan_lama', 'departemen_baru',
                    'jabatan_baru', 'pg', 'level_lama', 'jg_lama', 'level_baru', 'jg_baru',
                ] as $column) {
                    $query->orWhere($column, 'like', "%{$search}%");
                }

                $query->orWhereHas('batch', function (Builder $batchQuery) use ($search): void {
                    $batchQuery->where('batch_number', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            });
        }

        if ($batchId !== null) {
            $mutationQuery->where('employee_mutation_batch_id', $batchId);
        }

        if ($filter === 'draft') {
            $mutationQuery->where('verification_status', 'Draft');
        } elseif ($filter === 'waiting') {
            $mutationQuery->where('verification_status', 'Pending');
        } elseif ($filter === 'returned') {
            $mutationQuery->where('verification_status', 'Returned');
        } elseif ($filter === 'approved') {
            $mutationQuery->where('verification_status', 'Approved');
        } elseif ($filter === 'final') {
            $mutationQuery->where('verification_status', 'Final');
        }

        return view('pages.mutasi', [
            'mutations' => $mutationQuery->latest('tmt')->latest()->paginate($perPage, ['*'], 'page', $reviewId === null ? null : 1)->withQueryString(),
            'reviewId' => $reviewId,
            'perPage' => $perPage,
            'totalMutations' => EmployeeMutation::query()->count(),
            'pendingMutations' => EmployeeMutation::query()->where('verification_status', 'Pending')->count(),
            'approvedMutationsThisYear' => EmployeeMutation::query()
                ->whereIn('verification_status', ['Approved', 'Final'])
                ->whereYear('finalized_at', now()->year)
                ->count(),
            'search' => $search,
            'filter' => $filter,
            'batchId' => $batchId,
            'mutationBatches' => EmployeeMutationBatch::query()
                ->withCount([
                    'mutations',
                    'mutations as draft_mutations_count' => fn (Builder $query) => $query->where('verification_status', 'Draft'),
                    'mutations as pending_mutations_count' => fn (Builder $query) => $query->where('verification_status', 'Pending'),
                    'mutations as returned_mutations_count' => fn (Builder $query) => $query->where('verification_status', 'Returned'),
                    'mutations as completed_mutations_count' => fn (Builder $query) => $query->whereIn('verification_status', ['Approved', 'Final']),
                ])
                ->latest()
                ->limit(6)
                ->get(),
            'batchOptions' => EmployeeMutationBatch::query()->latest()->get(['id', 'batch_number', 'name']),
            'employeeOptions' => $formDataService->employees(),
            ...$formDataService->organization(),
            'approvalConfiguration' => $workflow->configuration(EmployeeMutation::class),
            'approvalConfigurationComplete' => $workflow->isConfigurationComplete(EmployeeMutation::class),
        ]);
    }

    public function store(StoreMutationRequest $request, PersonnelMovementSnapshotService $snapshotService): RedirectResponse
    {
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));
        EmployeeMutation::query()->create([
            ...$snapshotService->fromEmployee($employee, $request->validated()),
            'verification_status' => 'Draft',
        ]);

        return to_route('mutasi')->with('status', 'Data mutasi berhasil disimpan sebagai Draft.');
    }

    public function update(
        UpdateMutationRequest $request,
        EmployeeMutation $employeeMutation,
        PersonnelActionGuard $personnelActionGuard,
        PersonnelMovementSnapshotService $snapshotService,
    ): RedirectResponse {
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));
        $personnelActionGuard->update(
            $employeeMutation,
            $snapshotService->fromEmployee($employee, $request->validated()),
        );

        return to_route('mutasi')->with('status', 'Data mutasi berhasil diperbarui.');
    }

    public function destroyMany(Request $request, PersonnelActionGuard $personnelActionGuard): RedirectResponse
    {
        $validated = $request->validate([
            'mutation_ids' => ['required', 'array', 'min:1'],
            'mutation_ids.*' => ['integer', 'distinct', 'exists:employee_mutations,id'],
        ]);

        $mutations = EmployeeMutation::query()->whereIn('id', $validated['mutation_ids'])->get();
        foreach ($mutations as $mutation) {
            Gate::authorize('delete', $mutation);
        }

        $deleted = $personnelActionGuard->deleteMany(
            EmployeeMutation::query()->whereIn('id', $validated['mutation_ids']),
        );

        return to_route('mutasi')->with('status', "Berhasil menghapus {$deleted} data mutasi.");
    }

    public function approve(Request $request, EmployeeMutation $employeeMutation, ApprovalService $approvalService): RedirectResponse
    {
        $manager = $request->user();
        abort_unless($manager instanceof User, 403);
        Gate::authorize('approve', $employeeMutation);

        $alreadyApproved = $approvalService->approve($employeeMutation, $manager);

        return to_route('mutasi')->with(
            'status',
            $alreadyApproved
                ? 'Anda sudah memberikan persetujuan untuk mutasi ini atau mutasi sudah final.'
                : 'Persetujuan berhasil dicatat.',
        );
    }

    public function submit(
        Request $request,
        EmployeeMutation $employeeMutation,
        MovementApprovalWorkflowService $workflow,
    ): RedirectResponse {
        Gate::authorize('submit', $employeeMutation);
        $workflow->submit($employeeMutation, $request->user());

        return to_route('mutasi')->with('status', 'Mutasi berhasil diajukan ke approver Tahap 1.');
    }

    public function sendBack(
        SendBackMutationRequest $request,
        EmployeeMutation $employeeMutation,
        ApprovalService $approvalService,
    ): RedirectResponse {
        $manager = $request->user();
        abort_unless($manager instanceof User, 403);

        $alreadyReturned = $approvalService->sendBack(
            $employeeMutation,
            $manager,
            $request->string('return_note')->toString(),
        );

        return to_route('mutasi')->with(
            'status',
            $alreadyReturned
                ? 'Keputusan pengembalian Anda untuk putaran ini sudah tercatat.'
                : 'Mutasi dikembalikan untuk diperbaiki.',
        );
    }

    public function print(Request $request, EmployeeMutation $employeeMutation): BinaryFileResponse
    {
        Gate::authorize('print', $employeeMutation);

        $templatePath = storage_path('template/mutasi-template.docx');
        abort_unless(file_exists($templatePath), 404, 'Template Word mutasi belum tersedia.');

        $temporaryPath = tempnam(sys_get_temp_dir(), 'mutasi_docx_');
        if ($temporaryPath === false) {
            abort(500, 'Tidak dapat membuat file dokumen sementara.');
        }

        $zip = new ZipArchive;
        $zipStatus = $zip->open($templatePath, ZipArchive::RDONLY);
        if ($zipStatus !== true) {
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

            if (str_ends_with($name, '.xml')) {
                $content = $this->replaceTemplatePlaceholders(
                    $content,
                    $employeeMutation,
                    $name === 'word/document.xml',
                );
            }

            $outputZip->addFromString($name, $content);
        }

        $zip->close();
        $outputZip->close();

        return response()->download(
            $temporaryPath,
            'mutasi-'.$employeeMutation->sap.'-'.now()->format('YmdHis').'.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        )->deleteFileAfterSend();
    }

    private function replaceTemplatePlaceholders(
        string $xmlContent,
        EmployeeMutation $employeeMutation,
        bool $appendLevelSummary,
    ): string {
        $document = new DOMDocument;
        abort_unless($document->loadXML($xmlContent, LIBXML_NONET), 500, 'Isi template Word mutasi tidak valid.');
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $replacements = [
            '[NAMA]' => $employeeMutation->nama,
            '[SAP]' => $employeeMutation->sap,
            '[JABATAN_BARU]' => $employeeMutation->jabatan_baru,
            '[JABATAN BARU]' => $employeeMutation->jabatan_baru,
            '[DIREKTORAT LAMA]' => (string) $employeeMutation->direktorat_lama,
            '[DEPARTEMEN LAMA]' => $employeeMutation->departemen_lama,
            '[UNIT LAMA]' => (string) $employeeMutation->unit_lama,
            '[SECTION LAMA]' => (string) $employeeMutation->section_lama,
            '[JABATAN LAMA]' => $employeeMutation->jabatan_lama,
            '[DIREKTORAT BARU]' => (string) $employeeMutation->direktorat_baru,
            '[DEPARTEMEN BARU]' => $employeeMutation->departemen_baru,
            '[UNIT BARU]' => (string) $employeeMutation->unit_baru,
            '[SECTION BARU]' => (string) $employeeMutation->section_baru,
            '[TMT]' => $employeeMutation->tmt?->format('d-m-Y') ?? '',
            '[PG]' => (string) $employeeMutation->pg,
            '[LEVEL LAMA]' => (string) $employeeMutation->level_lama,
            '[LEVEL BARU]' => (string) $employeeMutation->level_baru,
            '[JG LAMA]' => (string) $employeeMutation->jg_lama,
            '[JG BARU]' => (string) $employeeMutation->jg_baru,
            '{{ NAMA }}' => $employeeMutation->nama,
            '{{ SAP }}' => $employeeMutation->sap,
            '{{ JABATAN_BARU }}' => $employeeMutation->jabatan_baru,
        ];

        foreach ($xpath->query('//w:p') as $paragraph) {
            $textNodes = $xpath->query('.//w:t', $paragraph);
            foreach ($replacements as $placeholder => $replacement) {
                $this->replacePlaceholderInTextNodes($textNodes, $placeholder, $replacement);
            }
        }

        if ($appendLevelSummary) {
            $this->appendLevelSummary($document, $xpath, $employeeMutation);
        }

        $updatedXml = $document->saveXML();
        abort_unless($updatedXml !== false, 500, 'Template Word mutasi tidak dapat diproses.');

        return $updatedXml;
    }

    private function appendLevelSummary(
        DOMDocument $document,
        DOMXPath $xpath,
        EmployeeMutation $employeeMutation,
    ): void {
        $body = $xpath->query('//w:body')->item(0);
        abort_unless($body instanceof DOMNode, 500, 'Struktur isi template Word mutasi tidak valid.');
        $sectionProperties = $xpath->query('./w:sectPr', $body)->item(0);

        foreach ([
            'Level Lama: '.$employeeMutation->level_lama,
            'Level Baru: '.$employeeMutation->level_baru,
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

        $paragraphText = implode('', array_map(static fn (DOMNode $node): string => $node->textContent, $nodes));
        $positions = [];
        $offset = 0;
        while (($position = strpos($paragraphText, $placeholder, $offset)) !== false) {
            $positions[] = $position;
            $offset = $position + strlen($placeholder);
        }

        foreach (array_reverse($positions) as $position) {
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
            $endText = $nodes[$endNodeIndex]->textContent;
            $nodes[$startNodeIndex]->textContent = substr($startText, 0, $startOffset)
                .$replacement
                .($startNodeIndex === $endNodeIndex ? substr($startText, $endOffset) : '');

            for ($index = $startNodeIndex + 1; $index <= $endNodeIndex; $index++) {
                $nodes[$index]->textContent = $index === $endNodeIndex ? substr($endText, $endOffset) : '';
            }
        }
    }
}
