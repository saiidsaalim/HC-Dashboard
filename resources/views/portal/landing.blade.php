@extends('layouts.portal')

@section('content')
    @include('portal.partials.header')

    <section class="p-hero">
        <div class="p-wrap">
            <div class="p-eyebrow">{{ config('hc_portal.department') }}</div>
            <h1>{{ config('hc_portal.hero.title') }}</h1>
            <p>
                @foreach (config('hc_portal.hero.lines') as $line)
                    {{ $line }}@if (! $loop->last)<br>@endif
                @endforeach
            </p>
            <div class="p-acts">
                <a class="p-btn p-btn-ghost" href="#unit">Pilih Unit</a>
                {{-- Search: hanya tampilan, belum berfungsi --}}
                <div class="p-search">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
                    <input type="search" placeholder="Search workspace, menu..." aria-label="Search">
                </div>
            </div>
        </div>
    </section>

    <main class="p-wrap">
        <section id="unit" class="p-sec">
            <h2 class="p-h2">Pilih Unit</h2>
            <div class="p-units">
                @foreach (config('hc_portal.units') as $unit)
                    <button type="button" class="p-ucard" data-unit="{{ $unit['key'] }}" aria-pressed="false">
                        <b>{{ $unit['name'] }}</b>
                        <span class="p-status">Active</span>
                    </button>
                @endforeach
            </div>
        </section>

        <section id="workspace" class="p-sec">
            <h2 class="p-h2">Workspace</h2>
            <div class="p-panel">
                <p class="p-empty" id="ws-empty">Pilih unit di atas untuk menampilkan workspace.</p>

                @foreach (config('hc_portal.units') as $unit)
                    <div class="p-ws" id="ws-{{ $unit['key'] }}" hidden>
                        @forelse ($unit['groups'] as $group)
                            <h3 class="p-group">{{ $group['title'] }}</h3>
                            <div class="p-tiles">
                                @foreach ($group['items'] as $item)
                                    <a class="p-tile" href="{{ route('login') }}" data-open-login
                                       style="--c: {{ $item['color'] }}">
                                        <div class="p-ic"><svg><use href="#{{ $item['icon'] }}"/></svg></div>
                                        <b>{{ $item['name'] }}</b>
                                    </a>
                                @endforeach
                            </div>
                        @empty
                            <h3 class="p-group">{{ $unit['name'] }}</h3>
                            <p class="p-empty">Workspace unit ini belum tersedia.</p>
                        @endforelse
                    </div>
                @endforeach
            </div>
        </section>
    </main>

    <footer class="p-footer">
        <div class="p-wrap p-foot">
            <div><b>{{ config('hc_portal.name') }}</b> · {{ config('hc_portal.department') }}<br>{{ config('hc_portal.company') }}</div>
            <div>&copy; {{ date('Y') }}</div>
        </div>
    </footer>

    @include('portal.partials.login-modal')
@endsection
