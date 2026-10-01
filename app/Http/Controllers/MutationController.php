<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StorePersonnelActionRequest;
use App\Http\Requests\UpdatePersonnelActionRequest;
use App\Imports\MutationImport;
use App\Models\EmployeeMutation;
use App\Models\User;
use App\Services\Personnel\ApprovalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class MutationController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));
        $mutationQuery = EmployeeMutation::query();

        if ($search !== '') {
            $mutationQuery->where(function (Builder $query) use ($search): void {
                foreach ([
                    'sap', 'nama', 'departemen_lama', 'jabatan_lama', 'departemen_baru',
                    'jabatan_baru', 'pg', 'band_lama', 'jg_lama', 'band_baru', 'jg_baru',
                ] as $column) {
                    $query->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        return view('pages.mutasi', [
            'mutations' => $mutationQuery->with('approvals')->latest('tmt')->latest()->limit(100)->get(),
            'totalMutations' => EmployeeMutation::query()->count(),
            'pendingMutations' => EmployeeMutation::query()->where('verification_status', 'Pending')->count(),
            'approvedMutationsThisYear' => EmployeeMutation::query()
                ->where('verification_status', 'Final')
                ->whereYear('finalized_at', now()->year)
                ->count(),
            'search' => $search,
        ]);
    }

    public function upload(Request $request, MutationImport $mutationImport): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120'],
        ]);

        $created = $mutationImport->import($validated['file']);

        return to_route('mutasi')->with('status', "Import berhasil: {$created} data mutasi disimpan.");
    }

    public function store(StorePersonnelActionRequest $request): RedirectResponse
    {
        EmployeeMutation::create($request->validated());

        return to_route('mutasi')->with('status', 'Data mutasi berhasil ditambahkan.');
    }

    public function update(UpdatePersonnelActionRequest $request, EmployeeMutation $employeeMutation): RedirectResponse
    {
        $employeeMutation->update($request->validated());

        return to_route('mutasi')->with('status', 'Data mutasi berhasil diperbarui.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->roleEnum() === UserRole::SUPER_ADMIN, 403);

        $validated = $request->validate([
            'mutation_ids' => ['required', 'array', 'min:1'],
            'mutation_ids.*' => ['integer', 'distinct', 'exists:employee_mutations,id'],
        ]);

        $deleted = EmployeeMutation::query()
            ->whereIn('id', $validated['mutation_ids'])
            ->delete();

        return to_route('mutasi')->with('status', "Berhasil menghapus {$deleted} data mutasi.");
    }

    public function approve(Request $request, EmployeeMutation $employeeMutation, ApprovalService $approvalService): RedirectResponse
    {
        $manager = $request->user();
        abort_unless($manager instanceof User && in_array($manager->roleEnum(), [UserRole::ADMIN, UserRole::MANAGER], true), 403);

        $alreadyApproved = $approvalService->approve($employeeMutation, $manager);

        return to_route('mutasi')->with(
            'status',
            $alreadyApproved
                ? 'Anda sudah memberikan persetujuan untuk mutasi ini atau mutasi sudah final.'
                : 'Persetujuan berhasil dicatat.',
        );
    }

    public function print(Request $request, EmployeeMutation $employeeMutation): BinaryFileResponse
    {
        abort_unless($request->user()?->roleEnum() === UserRole::SUPER_ADMIN, 403);
        abort_unless($employeeMutation->verification_status === 'Final', 404, 'Data mutasi belum final sehingga belum dapat dicetak.');

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
                $content = $this->replaceTemplatePlaceholders($content, $employeeMutation);
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

    private function replaceTemplatePlaceholders(string $xmlContent, EmployeeMutation $employeeMutation): string
    {
        $replacements = [
            '[NAMA]' => htmlspecialchars($employeeMutation->nama, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
            '[SAP]' => htmlspecialchars($employeeMutation->sap, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
            '[JABATAN_BARU]' => htmlspecialchars($employeeMutation->jabatan_baru, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
            '[JABATAN BARU]' => htmlspecialchars($employeeMutation->jabatan_baru, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
            '{{ NAMA }}' => htmlspecialchars($employeeMutation->nama, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
            '{{ SAP }}' => htmlspecialchars($employeeMutation->sap, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
            '{{ JABATAN_BARU }}' => htmlspecialchars($employeeMutation->jabatan_baru, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $xmlContent);
    }
}
