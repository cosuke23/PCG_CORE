@extends('layouts.unit')

@section('title', '201 files')

@section('content')
    <div class="pcg-page uc">
        <header class="uc-head">
            <div>
                <h2 class="uc-title">{{ $personnel->count() }} {{ $personnel->count() === 1 ? 'member has' : 'members have' }} a 201 file</h2>
                <p class="uc-lede">Open a member to read their 201 documents. Every opening is logged.</p>
            </div>
        </header>

        <div class="uc-tools">
            <label class="uc-search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <span class="pcg-visually-hidden">Search by name, rank or serial number</span>
                <input type="search" id="uf-search" placeholder="Search name, rank or serial number" autocomplete="off">
            </label>
        </div>

        <section class="uc-queue" aria-label="Members">
            <table class="uc-table uf-table">
                <thead>
                    <tr>
                        <th scope="col">Member</th>
                        <th scope="col">Rank</th>
                        <th scope="col">Class</th>
                        <th scope="col">Unit</th>
                        <th scope="col"><span class="pcg-visually-hidden">Action</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($personnel as $p)
                        @php($name = trim(($p->last_name ?? '') . ', ' . ($p->first_name ?? '') . ' ' . ($p->middle_name ?? ''), ', '))
                        <tr data-search="{{ strtolower($name . ' ' . $p->rank . ' ' . $p->serial_number) }}">
                            <td><strong class="uc-name">{{ $name ?: 'Name not given' }}</strong><span class="uc-serial pcg-mono">{{ $p->serial_number }}</span></td>
                            <td>{{ $p->rank ?: '—' }}</td>
                            <td>{{ $p->class ?: '—' }}</td>
                            <td>{{ $p->mother_unit ?: '—' }}</td>
                            <td class="uc-action">
                                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" data-uf-open="{{ $p->serial_number }}">
                                    Open <span class="pcg-visually-hidden">{{ $name }}</span>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="uc-empty" id="uf-empty" @if ($personnel->isNotEmpty()) hidden @endif>
                {{ $personnel->isEmpty() ? 'No member has uploaded a 201 file yet.' : 'No member matches.' }}
            </p>
        </section>
    </div>

    <dialog class="uc-dialog" id="uf-dialog" aria-labelledby="uf-name" data-base="{{ \App\Support\Uploads::baseUrl() }}">
        <div class="uc-dialog__form">
            <header class="uc-dialog__head">
                <div>
                    <h2 id="uf-name"></h2>
                    <p class="uc-dialog__sub"><span class="pcg-mono" id="uf-serial"></span></p>
                </div>
                <button type="button" class="pcg-btn pcg-btn--ghost pcg-btn--sm" data-uc-close aria-label="Close">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </header>
            <div class="uc-dialog__body">
                <aside class="uc-items" aria-label="201 documents"><ul id="uf-items"></ul></aside>
                <section class="uc-viewer" aria-live="polite">
                    <header class="uc-viewer__head">
                        <h3 id="uf-title"></h3>
                        <a id="uf-open" class="uc-viewer__open" target="_blank" rel="noopener" hidden>
                            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Open in a new tab</a>
                    </header>
                    <div class="uc-viewer__frame" id="uf-frame"></div>
                </section>
            </div>
        </div>
    </dialog>
@endsection

@section('scripts')
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ \App\Support\Asset::v('js/unit-checker.js') }}"></script>
@endsection
