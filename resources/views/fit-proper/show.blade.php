@extends('layouts.app')
@section('page-title','Detail Fit & Proper')
@section('content')
<div class="fp-detail space-y-6" data-fp-root>
<div data-fp-fragment="heading"><x-ui.page-header :title="$activity->name" eyebrow="Fit & Proper" :description="$activity->target_position" :back-href="route('fit-proper')" back-label="Daftar kegiatan" /></div>
@include('fit-proper._messages')
<nav aria-label="Bagian detail kegiatan" class="fp-detail-nav flex flex-wrap gap-3 text-sm text-sky-700">@foreach(['ringkasan'=>'Ringkasan','peserta'=>'Peserta','pic'=>'PIC','penguji'=>'Penguji','progres'=>'Progres','rekap'=>'Rekap Penilaian','hasil'=>'Hasil Akhir','riwayat'=>'Riwayat'] as $id=>$label)<a href="#{{ $id }}" class="rounded-lg px-3 py-2 font-semibold">{{ $label }}</a>@endforeach</nav>
<section data-fp-structure="ringkasan" id="ringkasan" class="rounded-xl border border-slate-200 bg-white p-5">
<div data-fp-fragment="summary"><h2 class="font-display text-lg font-bold">Ringkasan · {{ $activity->status }}</h2>
<p class="mt-2 text-sm">{{ $activity->department }} · {{ $activity->scheduled_at->format('d/m/Y') }}</p><p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $activity->description }}</p>
<dl class="fp-detail-metrics mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
<div><dt>Peserta</dt><dd>{{ $activity->participants->count() }}</dd></div>
<div><dt>Penguji</dt><dd>{{ $activity->examiners->count() }}</dd></div>
<div><dt>Passing grade</dt><dd>{{ $activity->standard['passing_grade'] }}</dd></div>
<div><dt>Penilaian terkirim</dt><dd>{{ $activity->examiners->whereNotNull('submitted_at')->count() }} / {{ $activity->examiners->count() }}</dd></div>
</dl>
<p class="mt-4 text-sm">Standar tersimpan: KPI {{ $activity->standard['weight_kpi']*100 }}%, asesmen {{ $activity->standard['weight_assessment']*100 }}%, FP {{ $activity->standard['weight_fp']*100 }}%. Skala KPI {{ $activity->standard['max_kpi'] }}, asesmen {{ $activity->standard['max_assessment'] }}. Passing grade {{ $activity->standard['passing_grade'] }}.</p>
<p class="mt-2 text-xs text-slate-500">Pembuat ID {{ $activity->created_by ?? '—' }} · Finalisasi {{ $activity->finalized_at?->format('d/m/Y H:i') ?? 'Belum' }}</p>
</div><div class="mt-4 flex flex-wrap gap-3">
@if($activity->status !== 'Selesai')
<form data-fp-async method="POST" action="{{ route('fit-proper.transition',$activity) }}" data-fp-confirm="Ubah status kegiatan? Kembali Draft membatalkan tautan dan status kirim seluruh penguji.">@csrf<input type="hidden" name="status" value="{{ $activity->status === 'Draft' ? 'Berjalan' : 'Draft' }}"><button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">{{ $activity->status === 'Draft' ? 'Mulai Kegiatan / Buka Penilaian' : 'Kembali ke Draft' }}</button></form>
@if($activity->status === 'Berjalan') @can('finalize',$activity)<form data-fp-async method="POST" action="{{ route('fit-proper.transition',$activity) }}" data-fp-confirm="Finalisasi mengunci seluruh hasil secara permanen. Lanjutkan?">@csrf<input type="hidden" name="status" value="Selesai"><button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Finalisasi</button></form>@endcan @endif
@endif
<a href="{{ route('fit-proper.print',$activity) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Cetak laporan</a><a href="{{ route('fit-proper.excel',$activity) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Ekspor Excel</a>
</div>
@if($activity->status === 'Draft')<details class="mt-4"><summary class="cursor-pointer text-sm font-semibold">Edit kegiatan</summary><form data-fp-async method="POST" action="{{ route('fit-proper.update',$activity) }}" class="mt-3">@method('PUT') @include('fit-proper._activity-form')</form></details>
@if($activity->participants->isEmpty() && $activity->examiners->isEmpty())<form method="POST" action="{{ route('fit-proper.destroy',$activity) }}" class="mt-3" onsubmit="return confirm('Hapus kegiatan kosong ini?')">@csrf @method('DELETE')<button class="text-sm text-red-700 underline">Hapus kegiatan kosong</button></form>@endif
@endif
</section>
<section data-fp-structure="peserta" id="peserta" class="rounded-xl border border-slate-200 bg-white p-5">
<div class="flex items-center justify-between gap-3"><h2 class="font-display text-lg font-bold">Peserta · {{ $activity->participants->count() }}</h2>@if($activity->status === 'Draft')<a class="rounded-lg bg-slate-950 px-4 py-2 text-sm text-white" href="{{ route('fit-proper.participants.create',$activity) }}">Tambah peserta</a>@endif</div>
<div class="mt-4 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b text-slate-500"><th class="p-3">Identitas</th><th class="p-3">Profil</th><th class="p-3">KPI / asesmen</th><th class="p-3">Tindakan</th></tr></thead><tbody class="divide-y">
@foreach($activity->participants as $p)<tr><td class="p-3"><strong>{{ $p->name }}</strong><p class="text-xs">{{ $p->sap ?: $p->internal_identity }}</p><p class="text-xs">Umur: {{ $p->age() ?? '—' }} tahun</p></td><td class="p-3">{{ $p->profile['department'] ?? '—' }}<p>{{ $p->profile['current_position'] ?? '—' }} → {{ $p->profile['target_position'] ?? '—' }}</p><p>JG {{ $p->profile['current_jg'] ?? '—' }} → {{ $p->profile['target_jg'] ?? '—' }} · gap {{ $p->profile['gap_jg'] ?? '—' }}</p></td><td class="p-3">KPI {{ $p->kpi() === null ? 'Belum lengkap' : number_format($p->kpi(),2) }}<p>Asesmen {{ $p->assessment ?? 'Belum lengkap' }}</p></td><td class="p-3"><div class="flex flex-wrap gap-3">
@if($p->cv_path)<a href="{{ route('fit-proper.cv',[$activity,$p]) }}" class="text-sky-700 underline">CV</a>@endif
@if($activity->status === 'Draft')<a href="{{ route('fit-proper.participants.edit',[$activity,$p]) }}" class="text-sky-700 underline">Edit</a><form method="POST" action="{{ route('fit-proper.participants.delete',[$activity,$p]) }}" onsubmit="return confirm('Hapus peserta beserta nilainya?')">@csrf @method('DELETE')<button class="text-red-700 underline">Hapus</button></form>@endif
</div><details class="mt-2"><summary class="cursor-pointer">Profil lengkap</summary>@foreach(['education'=>'Pendidikan','education_background'=>'Latar pendidikan','experience'=>'Pengalaman','training'=>'Pelatihan','achievement'=>'Achievement','assessment_notes'=>'Rekomendasi asesmen'] as $key=>$label)<p class="mt-1 whitespace-pre-line">{{ $label }}: {{ $p->profile[$key] ?? '—' }}</p>@endforeach</details></td></tr>@endforeach
</tbody></table></div></section>
<div class="fp-detail-assignments grid items-start gap-6 xl:grid-cols-2">
<section data-fp-structure="pic" id="pic" class="rounded-xl border border-slate-200 bg-white p-5">
<h2 class="font-display text-lg font-bold">PIC</h2>
<div data-fp-fragment="pic-list" class="mt-3 space-y-2">
@forelse($activity->pics as $pic)
<div data-fp-pic-id="{{ $pic->id }}" class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 p-3 text-sm">
<span>{{ $pic->name }} · {{ $pic->email }}</span>
@if($activity->status === 'Draft') @can('create',\App\Models\FitProperActivity::class)
<form data-fp-async data-fp-confirm="Lepas PIC ini dari kegiatan?" method="POST" action="{{ route('fit-proper.pics.delete',[$activity,$pic]) }}">@csrf @method('DELETE')<button type="submit" class="text-red-700 underline">Lepas PIC</button></form>
@endcan @endif
</div>
@empty<p class="text-sm text-slate-500">Belum ditugaskan</p>@endforelse
</div>
@if($activity->status === 'Draft') @can('create',\App\Models\FitProperActivity::class)
<form data-fp-async data-fp-reset method="POST" action="{{ route('fit-proper.pics',$activity) }}" class="mt-4">
@csrf @method('PUT')
<fieldset data-fp-user-picker><legend class="text-sm font-semibold">Tambahkan PIC (bisa pilih beberapa)</legend>
<label class="mt-2 block text-sm">Cari nama atau email<input type="search" data-fp-user-search class="mt-1 w-full rounded-lg border-slate-300" placeholder="Cari pengguna…"></label>
<div class="mt-2 max-h-56 overflow-y-auto rounded-lg border border-slate-200 p-2">
@foreach($users as $user)
<label data-fp-user-option class="flex items-center gap-3 p-2 text-sm"><input type="checkbox" name="users[]" value="{{ $user->id }}" @disabled($activity->pics->contains('id',$user->id)) class="rounded border-slate-300"> <span>{{ $user->name }} · {{ $user->email }}</span></label>
@endforeach</div></fieldset>
<p class="mt-2 text-xs text-slate-500">PIC yang sudah ditugaskan tetap tersimpan. Gunakan Lepas PIC untuk menghapus satu penugasan.</p>
<button type="submit" class="mt-3 rounded-lg border border-slate-300 px-4 py-2 text-sm">Tambahkan PIC</button>
</form>
@endcan @endif
</section>
<section data-fp-structure="penguji" id="penguji" class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-display text-lg font-bold">Penguji</h2><p class="my-2 text-sm text-slate-600">Setiap penguji wajib menilai seluruh peserta. Nilai penguji yang dihapus ikut dihapus; perubahan hanya saat Draft.</p>
<div data-fp-fragment="examiner-list" class="divide-y">
<div class="py-3 text-sm" role="status"><p>Status kegiatan: <strong>{{ $activity->status }}</strong></p>
@if($activity->status === 'Draft')
<p class="mt-1 text-amber-800">Penilaian belum dibuka. Lengkapi peserta, PIC, dan penguji, lalu klik Mulai Kegiatan / Buka Penilaian di <a href="#ringkasan" class="underline">Ringkasan</a>. Membuat atau menyalin tautan tidak membuka kegiatan.</p>
@elseif($activity->status === 'Berjalan')
<p class="mt-1 text-emerald-800">Penilaian sudah dibuka. Tautan yang valid, belum kedaluwarsa, dan belum dicabut dapat digunakan tanpa login. Penilaian yang sudah dikirim terkunci.</p>
@else
<p class="mt-1 text-slate-600">Kegiatan selesai. Seluruh penilaian terkunci dan tautan tidak dapat digunakan lagi.</p>
@endif
<p class="mt-1 text-xs text-slate-500">Tanggal pelaksanaan adalah informasi jadwal; akses dibuka secara eksplisit melalui status Berjalan. Masa berlaku dan pencabutan tautan tetap diperiksa.</p></div><p class="py-2 text-sm font-semibold">{{ $activity->examiners->count() }} penguji ditugaskan</p>@foreach($activity->examiners as $e)<div data-fp-examiner-id="{{ $e->id }}" class="flex flex-wrap items-center justify-between gap-3 py-3"><div class="text-sm"><strong>{{ $e->name }}</strong> · {{ $e->user_id ? 'Internal' : 'Manual' }}<p>{{ $e->submitted_at ? 'Sudah dikirim' : 'Belum dikirim' }} · Tautan {{ $e->revoked_at ? 'dicabut' : ($e->expires_at ? 'kedaluwarsa '.$e->expires_at->format('d/m/Y H:i') : 'belum dibuat') }}</p></div>
<div class="flex flex-wrap gap-3 text-sm">@if($e->user_id === auth()->id())<a href="{{ route('fit-proper.assess',[$activity,$e]) }}" class="text-sky-700 underline">Penilaian saya</a>@endif
@if($activity->status !== 'Selesai')<form data-fp-async data-fp-confirm="Terbitkan ulang tautan? Tautan sebelumnya akan dibatalkan." method="POST" action="{{ route('fit-proper.token',[$activity,$e]) }}">@csrf<button type="submit" class="text-sky-700 underline">Terbitkan ulang tautan</button></form><form data-fp-async method="POST" action="{{ route('fit-proper.token',[$activity,$e]) }}">@csrf<input type="hidden" name="revoke" value="1"><button class="text-red-700 underline">Cabut tautan</button></form>@endif
@if($activity->status === 'Draft')<form data-fp-async method="POST" action="{{ route('fit-proper.examiners.delete',[$activity,$e]) }}" data-fp-confirm="Hapus penguji beserta seluruh nilainya?">@csrf @method('DELETE')<button class="text-red-700 underline">Hapus</button></form>@endif
</div></div>@endforeach</div>
@if($activity->status === 'Draft')<form data-fp-async data-fp-reset method="POST" action="{{ route('fit-proper.examiners',$activity) }}" class="fp-detail-examiner-form mt-4 grid gap-3">@csrf<label class="text-sm">Akun internal (opsional)<select name="user_id" class="mt-1 w-full rounded-lg border-slate-300"><option value="">Penguji manual</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }} · {{ $u->email }}</option>@endforeach</select></label><label class="text-sm">Nama penguji manual<input name="name" class="mt-1 w-full rounded-lg border-slate-300"></label><button class="self-end rounded-lg bg-slate-950 px-4 py-2 text-sm text-white">Tambah penguji</button></form>@endif
</section>
</div>
<section data-fp-fragment="progres" id="progres" class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-display text-lg font-bold">Progres</h2><p class="mt-2 text-sm">{{ $activity->examiners->whereNotNull('submitted_at')->count() }} / {{ $activity->examiners->count() }} penguji telah mengirim seluruh penilaian.</p><p class="mt-2 text-sm">{{ $recap['complete'] ? 'Seluruh data lengkap dan normalisasi tersedia.' : 'Belum siap finalisasi. Periksa kelengkapan KPI, asesmen dan seluruh penguji.' }}</p>@if($recap['zero_max'])<p class="mt-2 text-sm text-red-700">Normalisasi belum tersedia: belum ada rata-rata lengkap di atas nol.</p>@endif</section>
<section data-fp-fragment="rekap" id="rekap" class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-display text-lg font-bold">Rekap penilaian per penguji</h2>@include('fit-proper._details')</section>
<section data-fp-fragment="hasil" id="hasil" class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-display text-lg font-bold">Hasil {{ $activity->status === 'Selesai' ? 'Akhir · Final' : 'Sementara' }}</h2>@include('fit-proper._recap')<p class="mt-4 text-sm text-slate-600">Hasil ini merupakan referensi. Tidak membuat atau menyetujui Promosi dan tidak mengubah jabatan pegawai.</p></section>
<section data-fp-fragment="riwayat" id="riwayat" class="rounded-xl border border-slate-200 bg-white p-5">
<div class="flex flex-wrap items-center justify-between gap-2"><h2 class="font-display text-lg font-bold">Riwayat</h2><span class="text-sm text-slate-500">{{ $histories->count() }} aktivitas</span></div>
<p class="mt-1 text-sm text-slate-500">Buka aktivitas untuk melihat rincian proses.</p>
<ol class="mt-4 space-y-3 text-sm">
@forelse($histories as $h)
<li>
<details class="fp-history rounded-xl border border-slate-200" data-history-id="{{ $h->id }}">
<summary class="flex cursor-pointer items-center justify-between gap-3 p-4">
<span class="min-w-0"><strong class="block break-words">{{ $h->action }}</strong><span class="mt-1 block text-xs text-slate-500"><time>{{ $h->created_at }}</time> ? {{ $users->firstWhere('id', $h->user_id)?->name ?? ($h->user_id ? 'Pengguna '.$h->user_id : 'Penguji tautan / sistem') }}</span></span>
<span class="fp-history-chevron shrink-0" aria-hidden="true"></span>
</summary>
<div class="fp-history-content border-t border-slate-200 p-4">
<dl class="grid gap-3 sm:grid-cols-2"><div><dt class="text-xs text-slate-500">Aktivitas</dt><dd class="mt-1 break-words">{{ $h->action }}</dd></div><div><dt class="text-xs text-slate-500">Waktu</dt><dd class="mt-1">{{ $h->created_at }}</dd></div><div><dt class="text-xs text-slate-500">Pengguna</dt><dd class="mt-1">{{ $h->user_id ?? 'Penguji tautan / sistem' }}</dd></div></dl>
@if($h->context)<p class="mt-4 text-xs font-semibold text-slate-500">Rincian aktivitas</p><pre class="mt-2 whitespace-pre-wrap break-all text-xs text-slate-600">{{ $h->context }}</pre>@endif
</div>
</details>
</li>
@empty<li class="rounded-lg bg-slate-50 p-4 text-slate-500">Belum ada riwayat kegiatan.</li>@endforelse
</ol></section>
</div>
@endsection
