@if ($paginator->hasPages())
    <div class="pagination">
        <div class="inline">
            @if ($paginator->onFirstPage())
                <span class="btn btn-light">السابق</span>
            @else
                <a class="btn btn-light" href="{{ $paginator->previousPageUrl() }}">السابق</a>
            @endif

            @if ($paginator->hasMorePages())
                <a class="btn btn-light" href="{{ $paginator->nextPageUrl() }}">التالي</a>
            @else
                <span class="btn btn-light">التالي</span>
            @endif
        </div>

        <span class="page-indicator">صفحة {{ $paginator->currentPage() }} من {{ $paginator->lastPage() }}</span>
    </div>
@endif
