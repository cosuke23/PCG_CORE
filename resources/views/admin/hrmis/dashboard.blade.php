@extends('layouts.admin')

@section('title', 'HRMIS overview')

@section('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/hrmis-overview.css') }}">
@endsection

@section('content')
    @php
        $notClassified = max(0, $total - $officers - $nonOfficers);
        $scopeText = $root ? 'limited to ' . $root->name . ' and its units' : 'organisation-wide';
    @endphp
    <div class="pcg-page hx" id="hx" data-export-url="{{ route('admin.hrmis.export') }}"
        data-person-url="{{ route('admin.personnel.show', ['personnel' => '__ID__']) }}"
        @if (\App\Support\AdminPermissions::can('reports.view')) data-can-export="1" @endif>

        <header class="hx-head">
            <div>
                <p class="hx-eyebrow">HRMIS · {{ $root ? $root->code : 'PCG' }} · personnel master</p>
                <h2>Personnel overview</h2>
                <p class="hx-lede">
                    Every figure is counted from the personnel master, {{ $scopeText }}, as of {{ now()->format('d M Y, H:i') }}.
                    Nothing is sampled or estimated. Select a card or a bar to filter; selections combine.
                </p>
            </div>
            <div class="hx-head__actions">
                <a class="pcg-btn pcg-btn--secondary" href="{{ route('admin.personnel.index') }}">Open personnel list</a>
                @if (\App\Support\AdminPermissions::can('reports.view'))
                    <button type="button" class="pcg-btn pcg-btn--primary" id="hx-export" disabled>
                        <i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export <span id="hx-export-n">0</span> to CSV
                    </button>
                @endif
            </div>
        </header>

        @if ($total === 0)
            <section class="pcg-card hx-empty-card">
                <div class="pcg-card__body">
                    <p class="pcg-empty">
                        No personnel records {{ $root ? 'are assigned to ' . $root->code . ' yet' : 'yet' }}.
                        @if ($orgWide)
                            Copy approved registrations with <code>php artisan pcg:personnel-sync</code> (dry run first), or <a href="{{ route('admin.personnel.create') }}">add a record</a>.
                        @endif
                    </p>
                </div>
            </section>
        @endif

        {{-- toolbar: search, active filters, result count --}}
        <section class="hx-toolbar" aria-label="Search and filters">
            <div class="hx-search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <label class="pcg-visually-hidden" for="hx-q">Search by name, serial number, rank or unit</label>
                <input type="search" id="hx-q" class="pcg-input" placeholder="Search name, serial, rank or unit" autocomplete="off" spellcheck="false">
            </div>
            <div class="hx-active" aria-live="polite">
                <span class="hx-count" id="hx-count">{{ number_format($total) }} of {{ number_format($total) }} people</span>
                <ul class="hx-chips" id="hx-chips" aria-label="Active filters"></ul>
                <button type="button" class="pcg-btn pcg-btn--ghost pcg-btn--sm" id="hx-clear" hidden>Clear all</button>
            </div>
        </section>

        {{-- KPI cards: each one is a filter (except pending registrations, which opens its page) --}}
        <section class="hx-kpis" aria-label="Key figures">
            <button type="button" class="hx-kpi hx-kpi--hero" data-kpi="all" aria-pressed="false">
                <span class="hx-kpi__label">Personnel</span>
                <span class="hx-kpi__value"><span data-v="all">{{ number_format($total) }}</span><small data-v="all-of"></small></span>
                <span class="hx-kpi__hint">{{ $orgWide ? 'all records' : 'currently assigned in scope' }} · select to clear filters</span>
                <span class="hx-split" aria-hidden="true">
                    <span class="hx-split__seg hx-split__seg--a" data-split="officer"></span>
                    <span class="hx-split__seg hx-split__seg--b" data-split="non_officer"></span>
                    <span class="hx-split__seg hx-split__seg--c" data-split="unclassified"></span>
                </span>
            </button>
            <button type="button" class="hx-kpi" data-kpi="cls:officer" aria-pressed="false">
                <span class="hx-kpi__label">Officers</span>
                <span class="hx-kpi__value" data-v="cls:officer">{{ number_format($officers) }}</span>
                <span class="hx-kpi__hint">by classification</span>
            </button>
            <button type="button" class="hx-kpi" data-kpi="cls:non_officer" aria-pressed="false">
                <span class="hx-kpi__label">Non-officers</span>
                <span class="hx-kpi__value" data-v="cls:non_officer">{{ number_format($nonOfficers) }}</span>
                <span class="hx-kpi__hint">by classification</span>
            </button>
            @if ($notClassified > 0)
                <button type="button" class="hx-kpi" data-kpi="cls:unclassified" aria-pressed="false">
                    <span class="hx-kpi__label">Not classified</span>
                    <span class="hx-kpi__value" data-v="cls:unclassified">{{ number_format($notClassified) }}</span>
                    <span class="hx-kpi__hint">classification not recorded</span>
                </button>
            @endif
            <button type="button" class="hx-kpi" data-kpi="flag:moved" aria-pressed="false">
                <span class="hx-kpi__label">Movements, last 30 days</span>
                <span class="hx-kpi__value" data-v="movements">{{ number_format($movementCount) }}</span>
                <span class="hx-kpi__hint">assignments that started · <span data-v="flag:moved">0</span> people</span>
            </button>
            <button type="button" class="hx-kpi hx-kpi--warn" data-kpi="flag:incomplete" aria-pressed="false">
                <span class="hx-kpi__label">Records needing attention</span>
                <span class="hx-kpi__value" data-v="flag:incomplete">{{ number_format($incomplete) }}</span>
                <span class="hx-kpi__hint">missing rank, birth date or service entry date</span>
            </button>
            <button type="button" class="hx-kpi" data-kpi="flag:inactive" aria-pressed="false">
                <span class="hx-kpi__label">Not active</span>
                <span class="hx-kpi__value" data-v="flag:inactive">{{ number_format($inactive) }}</span>
                <span class="hx-kpi__hint">service status other than active</span>
            </button>
            @if ($orgWide)
                <button type="button" class="hx-kpi hx-kpi--warn" data-kpi="flag:unassigned" aria-pressed="false">
                    <span class="hx-kpi__label">Without assignment</span>
                    <span class="hx-kpi__value" data-v="flag:unassigned">{{ number_format($unassigned) }}</span>
                    <span class="hx-kpi__hint">not visible to command HR</span>
                </button>
                @if ($pendingRegistrations !== null)
                    <a class="hx-kpi hx-kpi--link" href="{{ route('admin.dashboard') }}">
                        <span class="hx-kpi__label">Pending registrations <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                        <span class="hx-kpi__value">{{ number_format($pendingRegistrations) }}</span>
                        <span class="hx-kpi__hint">self-registrations awaiting approval (not in the master yet)</span>
                    </a>
                @endif
            @endif
        </section>

        <div class="hx-grid">
            <section class="pcg-card hx-chart" aria-labelledby="hx-unit-h">
                <div class="hx-chart__head">
                    <h3 id="hx-unit-h">Personnel by {{ $root ? 'unit' : 'command' }}</h3>
                    <p>People with a current assignment, counted under the {{ $root ? 'unit' : 'command' }} it falls in (subordinate units included). Select a bar to keep only that {{ $root ? 'unit' : 'command' }}.</p>
                </div>
                <div class="hx-bars" id="hx-bars-unit" role="group" aria-label="Filter by {{ $root ? 'unit' : 'command' }}"></div>
            </section>

            <section class="pcg-card hx-chart" aria-labelledby="hx-rank-h">
                <div class="hx-chart__head">
                    <h3 id="hx-rank-h">Personnel by rank</h3>
                    <p>Officers first, most senior first. Select one or more ranks; within a chart, selections add up.</p>
                </div>
                <div class="hx-bars hx-bars--scroll" id="hx-bars-rank" role="group" aria-label="Filter by rank"></div>
            </section>

            <section class="pcg-card hx-chart" aria-labelledby="hx-missing-h">
                <div class="hx-chart__head">
                    <h3 id="hx-missing-h">What incomplete records are missing</h3>
                    <p>A record can miss more than one item, so these can add up to more than the records needing attention.</p>
                </div>
                <div class="hx-bars" id="hx-bars-missing" role="group" aria-label="Filter by missing item"></div>
            </section>

            <section class="pcg-card hx-chart" aria-labelledby="hx-days-h">
                <div class="hx-chart__head">
                    <h3 id="hx-days-h">Assignments started, last 30 days</h3>
                    <p id="hx-days-note">One column per day, for the people currently shown.</p>
                </div>
                <div class="hx-days" id="hx-days" role="img" aria-labelledby="hx-days-h hx-days-note"></div>
                <div class="hx-days__axis" aria-hidden="true"><span id="hx-days-from"></span><span id="hx-days-to"></span></div>
            </section>
        </div>

        <section class="pcg-card hx-results" aria-labelledby="hx-res-h">
            <div class="hx-chart__head hx-results__head">
                <div>
                    <h3 id="hx-res-h">People</h3>
                    <p>Select a name for details. Sort with the column headings.</p>
                </div>
            </div>
            <div class="pcg-table-wrap">
                <table class="pcg-table hx-table" id="hx-table">
                    <thead>
                        <tr>
                            <th scope="col" aria-sort="none"><button type="button" data-sort="rank">Rank</button></th>
                            <th scope="col" aria-sort="ascending"><button type="button" data-sort="name">Name</button></th>
                            <th scope="col" aria-sort="none"><button type="button" data-sort="serial">Serial number</button></th>
                            <th scope="col" aria-sort="none"><button type="button" data-sort="unit">Unit</button></th>
                            <th scope="col" aria-sort="none"><button type="button" data-sort="status">Status</button></th>
                            <th scope="col">Notes</th>
                        </tr>
                    </thead>
                    <tbody id="hx-tbody"></tbody>
                </table>
            </div>
            <p class="pcg-empty" id="hx-none" hidden>No one matches these filters. Remove a filter above or clear all.</p>
            <div class="hx-more"><button type="button" class="pcg-btn pcg-btn--secondary" id="hx-more" hidden>Show more</button></div>
        </section>

        <section class="pcg-card pcg-mt" aria-labelledby="hx-mov-h">
            <div class="hx-chart__head">
                <h3 id="hx-mov-h">Recent movements</h3>
                <p>Assignments that started in the last 30 days, for the people currently shown.</p>
            </div>
            <div class="pcg-table-wrap">
                <table class="pcg-table">
                    <thead><tr><th scope="col">Personnel</th><th scope="col">To</th><th scope="col">Effective</th><th scope="col">Order / reference</th></tr></thead>
                    <tbody id="hx-moves"></tbody>
                </table>
            </div>
            <p class="pcg-empty" id="hx-moves-none" hidden>No assignments started in the last 30 days for the people shown.</p>
        </section>

        {{-- person details --}}
        <dialog class="hx-drawer" id="hx-drawer" aria-labelledby="hx-d-name">
            <div class="hx-drawer__inner">
                <div class="hx-drawer__top">
                    <div>
                        <p class="hx-eyebrow" id="hx-d-rank"></p>
                        <h3 id="hx-d-name"></h3>
                    </div>
                    <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" id="hx-d-close" aria-label="Close details"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </div>
                <dl class="pcg-dl" id="hx-d-list"></dl>
                <div id="hx-d-flags" class="hx-d-flags"></div>
                <h4 class="hx-d-sub">Movements in the last 30 days</h4>
                <ul class="pcg-list" id="hx-d-moves"></ul>
                <a class="pcg-btn pcg-btn--primary pcg-btn--block" id="hx-d-open" href="#">Open full record</a>
            </div>
        </dialog>

        <form id="hx-export-form" method="POST" action="{{ route('admin.hrmis.export') }}" hidden>
            @csrf
            <input type="hidden" name="ids" id="hx-export-ids">
            <input type="hidden" name="filters" id="hx-export-filters">
        </form>

        <script type="application/json" id="hx-data" nonce="{{ $cspNonce ?? '' }}">@json($explorer)</script>
    </div>
@endsection

@section('scripts')
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ \App\Support\Asset::v('js/hrmis-overview.js') }}" defer></script>
@endsection
