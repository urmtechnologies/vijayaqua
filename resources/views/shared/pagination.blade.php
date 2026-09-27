@if($paginator->hasPages())
<nav class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-4" aria-label="Users pagination">
    <small class="text-muted">Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</small>
    <ul class="pagination mb-0">
        <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
            <a class="page-link" href="{{ $paginator->previousPageUrl() ?? '#' }}" aria-label="Previous" @if($paginator->onFirstPage()) aria-disabled="true" tabindex="-1" @endif>&lsaquo;</a>
        </li>
        @foreach($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
            <li class="page-item {{ $page === $paginator->currentPage() ? 'active' : '' }}">
                <a class="page-link" href="{{ $url }}" @if($page === $paginator->currentPage()) aria-current="page" @endif>{{ $page }}</a>
            </li>
        @endforeach
        <li class="page-item {{ $paginator->hasMorePages() ? '' : 'disabled' }}">
            <a class="page-link" href="{{ $paginator->nextPageUrl() ?? '#' }}" aria-label="Next" @unless($paginator->hasMorePages()) aria-disabled="true" tabindex="-1" @endunless>&rsaquo;</a>
        </li>
    </ul>
</nav>
@endif
