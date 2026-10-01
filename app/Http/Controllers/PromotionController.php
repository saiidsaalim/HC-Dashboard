<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StorePersonnelActionRequest;
use App\Http\Requests\UpdatePersonnelActionRequest;
use App\Imports\PromotionImport;
use App\Models\EmployeePromotion;
use App\Models\User;
use App\Services\Personnel\ApprovalService;
use DOMDocument;
use DOMNodeList;
use DOMXPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class PromotionController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));
        $promotionQuery = EmployeePromotion::query();

        if ($search !== '') {
            $promotionQuery->where(function (Builder $query) use ($search): void {
                foreach ([
                    'sap', 'nama', 'departemen_lama', 'jabatan_lama', 'departemen_baru', 'jabatan_baru',
                    'pg', 'band_lama', 'jg_lama', 'band_baru', 'jg_baru',
                ] as $column) {
                    $query->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        return view('pages.promosi', [
            'promotions' => $promotionQuery->with('approvals')->latest('tmt')->latest()->limit(100)->get(),
            'totalPromotions' => EmployeePromotion::query()->count(),
            'pendingPromotions' => EmployeePromotion::query()->where('verification_status', 'Pending')->count(),
            'approvedThisYear' => EmployeePromotion::query()
                ->where('verification_status', 'Final')
                ->whereYear('finalized_at', now()->year)
                ->count(),
            'search' => $search,
        ]);
    }

    public function upload(Request $request, PromotionImport $promotionImport): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120'],
        ]);

        $created = $promotionImport->import($validated['file']);

        return to_route('promosi')->with('status', "Import berhasil: {$created} data promosi disimpan.");
    }

    public function store(StorePersonnelActionRequest $request): RedirectResponse
    {
        EmployeePromotion::create($request->validated());

        return to_route('promosi')->with('status', 'Data promosi berhasil ditambahkan.');
    }

    public function update(UpdatePersonnelActionRequest $request, EmployeePromotion $employeePromotion): RedirectResponse
    {
        $employeePromotion->update($request->validated());

        return to_route('promosi')->with('status', 'Data promosi berhasil diperbarui.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->roleEnum() === UserRole::SUPER_ADMIN, 403);

        $validated = $request->validate([
            'promotion_ids' => ['required', 'array', 'min:1'],
            'promotion_ids.*' => ['integer', 'distinct', 'exists:employee_promotions,id'],
        ]);

        $deleted = EmployeePromotion::query()->whereIn('id', $validated['promotion_ids'])->delete();

        return to_route('promosi')->with('status', "Berhasil menghapus {$deleted} data promosi.");
    }

    public function approve(Request $request, EmployeePromotion $employeePromotion, ApprovalService $approvalService): RedirectResponse
    {
        $manager = $request->user();
        abort_unless($manager instanceof User && in_array($manager->roleEnum(), [UserRole::ADMIN, UserRole::MANAGER], true), 403);

        $alreadyApproved = $approvalService->approve($employeePromotion, $manager);

        return to_route('promosi')->with(
            'status',
            $alreadyApproved
                ? 'Anda sudah memberikan persetujuan untuk promosi ini atau promosi sudah final.'
                : 'Persetujuan promosi berhasil dicatat.',
        );
    }

    public function print(Request $request, EmployeePromotion $employeePromotion): BinaryFileResponse
    {
        abort_unless($request->user()?->roleEnum() === UserRole::SUPER_ADMIN, 403);
        abort_unless($employeePromotion->verification_status === 'Final', 404, 'Data promosi belum final sehingga belum dapat dicetak.');

        $templatePath = storage_path('template/promosi-template.docx');
        abort_unless(file_exists($templatePath), 404, 'Template Word promosi belum tersedia.');

        $temporaryPath = tempnam(sys_get_temp_dir(), 'promosi_docx_');
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
                $content = $this->replaceTemplatePlaceholders($content, $employeePromotion);
            }

            $outputZip->addFromString($name, $content);
        }

        $zip->close();
        $outputZip->close();

        return response()->download(
            $temporaryPath,
            'promosi-'.$employeePromotion->sap.'-'.now()->format('YmdHis').'.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        )->deleteFileAfterSend();
    }

    private function replaceTemplatePlaceholders(string $xmlContent, EmployeePromotion $employeePromotion): string
    {
        $document = new DOMDocument;
        abort_unless($document->loadXML($xmlContent, LIBXML_NONET), 500, 'Isi template Word promosi tidak valid.');

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $replacements = [
            '[PEGAWAI]' => $employeePromotion->nama,
            '[SAP]' => $employeePromotion->sap,
            '[JABATAN BARU]' => $employeePromotion->jabatan_baru,
        ];

        foreach ($xpath->query('//w:p') as $paragraph) {
            $textNodes = $xpath->query('.//w:t', $paragraph);

            foreach ($replacements as $placeholder => $replacement) {
                $this->replacePlaceholderInTextNodes($textNodes, $placeholder, $replacement);
            }
        }

        $updatedXml = $document->saveXML();
        abort_unless($updatedXml !== false, 500, 'Template Word promosi tidak dapat diproses.');

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
