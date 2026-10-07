<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StorePersonnelActionRequest;
use App\Http\Requests\UpdatePersonnelActionRequest;
use App\Imports\DemotionImport;
use App\Models\EmployeeDemotion;
use App\Models\User;
use App\Services\Personnel\ApprovalService;
use App\Services\Personnel\PersonnelActionGuard;
use DOMDocument;
use DOMNodeList;
use DOMXPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class DemotionController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));
        $demotionQuery = EmployeeDemotion::query();

        if ($search !== '') {
            $demotionQuery->where(function (Builder $query) use ($search): void {
                foreach ([
                    'sap', 'nama', 'departemen_lama', 'jabatan_lama', 'departemen_baru', 'jabatan_baru',
                    'pg', 'band_lama', 'jg_lama', 'band_baru', 'jg_baru',
                ] as $column) {
                    $query->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        return view('pages.demosi', [
            'demotions' => $demotionQuery->with('approvals')->latest('tmt')->latest()->limit(100)->get(),
            'totalDemotions' => EmployeeDemotion::query()->count(),
            'pendingDemotions' => EmployeeDemotion::query()->where('verification_status', 'Pending')->count(),
            'approvedDemotionsThisYear' => EmployeeDemotion::query()
                ->where('verification_status', 'Final')
                ->whereYear('finalized_at', now()->year)
                ->count(),
            'search' => $search,
        ]);
    }

    public function upload(Request $request, DemotionImport $demotionImport): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120'],
        ]);

        $created = $demotionImport->import($validated['file']);

        return to_route('demosi')->with('status', "Import berhasil: {$created} data demosi disimpan.");
    }

    public function store(StorePersonnelActionRequest $request): RedirectResponse
    {
        EmployeeDemotion::create($request->validated());

        return to_route('demosi')->with('status', 'Data demosi berhasil ditambahkan.');
    }

    public function update(
        UpdatePersonnelActionRequest $request,
        EmployeeDemotion $employeeDemotion,
        PersonnelActionGuard $personnelActionGuard,
    ): RedirectResponse {
        $personnelActionGuard->update($employeeDemotion, $request->validated());

        return to_route('demosi')->with('status', 'Data demosi berhasil diperbarui.');
    }

    public function destroyMany(Request $request, PersonnelActionGuard $personnelActionGuard): RedirectResponse
    {
        abort_unless($request->user()?->roleEnum() === UserRole::SUPER_ADMIN, 403);

        $validated = $request->validate([
            'demotion_ids' => ['required', 'array', 'min:1'],
            'demotion_ids.*' => ['integer', 'distinct', 'exists:employee_demotions,id'],
        ]);

        $deleted = $personnelActionGuard->deleteMany(
            EmployeeDemotion::query()->whereIn('id', $validated['demotion_ids']),
        );

        return to_route('demosi')->with('status', "Berhasil menghapus {$deleted} data demosi.");
    }

    public function approve(Request $request, EmployeeDemotion $employeeDemotion, ApprovalService $approvalService): RedirectResponse
    {
        $manager = $request->user();
        abort_unless($manager instanceof User && in_array($manager->roleEnum(), [UserRole::ADMIN, UserRole::MANAGER], true), 403);

        $alreadyApproved = $approvalService->approve($employeeDemotion, $manager);

        return to_route('demosi')->with(
            'status',
            $alreadyApproved
                ? 'Anda sudah memberikan persetujuan untuk demosi ini atau demosi sudah final.'
                : 'Persetujuan demosi berhasil dicatat.',
        );
    }

    public function print(Request $request, EmployeeDemotion $employeeDemotion): BinaryFileResponse
    {
        abort_unless($request->user()?->roleEnum() === UserRole::SUPER_ADMIN, 403);
        abort_unless($employeeDemotion->verification_status === 'Final', 404, 'Data demosi belum final sehingga belum dapat dicetak.');

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
                '[JABATAN BARU]' => $employeeDemotion->jabatan_baru,
            ] as $placeholder => $replacement) {
                $this->replacePlaceholderInTextNodes($textNodes, $placeholder, $replacement);
            }
        }

        $updatedXml = $document->saveXML();
        abort_unless($updatedXml !== false, 500, 'Template Word tidak dapat diproses.');

        return $updatedXml;
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
