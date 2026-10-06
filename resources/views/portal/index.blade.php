@extends('layouts.portal')

@section('content')
    <header class="portal-header">
        <div class="portal-container portal-header__inner">
            <a class="portal-header__brand" href="{{ route('portal.home') }}" aria-label="HC Portal">
                <img src="{{ asset('images/logo-sig-white.png') }}" alt="SIG">
            </a>

            <div class="portal-header__actions">
                <img class="portal-header__tonasa" src="{{ asset('images/logo-tonasa.png') }}" alt="Semen Tonasa">
                <button class="portal-button portal-button--header" type="button" data-open-login>
                    Masuk ke Portal
                </button>
            </div>
        </div>
    </header>

    <main>
        <section
            class="portal-hero"
            aria-labelledby="portal-title"
            style="--portal-hero-image: url('{{ asset('images/tonasa-bg.jpg') }}')"
        >
            <div class="portal-container portal-hero__inner">
                <p class="portal-eyebrow">Dept. of Human Capital &amp; GRC</p>
                <h1 id="portal-title">Platform terpadu</h1>
                <p class="portal-hero__description">
                    untuk mengintegrasikan alur kerja, layanan informasi,<br class="portal-desktop-break">
                    serta tata kelola dan pengembangan talenta berkelanjutan
                </p>

                <div class="portal-hero__actions">
                    <a class="portal-button portal-button--outline" href="#unit">Pilih Unit</a>
                    <label class="portal-search">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="10.8" cy="10.8" r="6.8" />
                            <path d="m16 16 5 5" />
                        </svg>
                        <input type="search" placeholder="Cari unit atau workspace..." aria-label="Cari unit atau workspace">
                    </label>
                </div>
            </div>
        </section>

        <section class="portal-section" id="unit" aria-labelledby="portal-unit-title">
            <div class="portal-container">
                <div class="portal-section__heading">
                    <p class="portal-eyebrow">HC Portal</p>
                    <h2 id="portal-unit-title">Pilih Unit</h2>
                </div>

                <div class="portal-unit-grid">
                    @foreach ($units as $unit)
                        <x-portal.unit-card :unit="$unit" />
                    @endforeach
                </div>

                <div class="portal-workspace-panels" aria-live="polite">
                    @foreach ($units as $unit)
                        <section
                            class="portal-workspace-panel"
                            id="portal-workspace-{{ $unit['id'] }}"
                            data-workspace-panel="{{ $unit['id'] }}"
                            aria-labelledby="portal-panel-title-{{ $unit['id'] }}"
                            hidden
                        >
                            <div class="portal-workspace-panel__heading">
                                <p class="portal-eyebrow">Workspace Unit</p>
                                <h3 id="portal-panel-title-{{ $unit['id'] }}">{{ $unit['name'] }}</h3>
                            </div>

                            @if (count($unit['workspace_groups']) > 0)
                                @foreach ($unit['workspace_groups'] as $group)
                                    <div class="portal-workspace-group">
                                        <h4>{{ $group['label'] }}</h4>
                                        <div class="portal-workspace-grid">
                                            @foreach ($group['workspaces'] as $workspace)
                                                <x-portal.workspace-icon :workspace="$workspace" />
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <p class="portal-workspace-empty">Workspace unit ini belum tersedia.</p>
                            @endif
                        </section>
                    @endforeach
                </div>
            </div>
        </section>
    </main>

    <footer class="portal-footer">
        <div class="portal-container portal-footer__inner">
            <strong>HC.Portal</strong>
            <span>Dept. of Human Capital &amp; GRC · PT Semen Tonasa</span>
        </div>
    </footer>

    <x-portal.login-modal />
@endsection
