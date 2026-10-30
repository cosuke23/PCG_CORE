@if ($paginator->hasPages())
    <nav class="pcg-pagination" aria-label="Pages">
        @if ($paginator->onFirstPage())
            <span class="pcg-btn pcg-btn--secondary pcg-btn--sm" aria-disabled="true">Previous</span>
        @else
            <a class="pcg-btn pcg-btn--secondary pcg-btn--sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
        @endif
        <span class="pcg-pagination__info">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a class="pcg-btn pcg-btn--secondary pcg-btn--sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
        @else
            <span class="pcg-btn pcg-btn--secondary pcg-btn--sm" aria-disabled="true">Next</span>
        @endif
    </nav>
@endif
