@if ($paginator->hasPages())
    <div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-2">
        <div class="text-secondary small">
            Menampilkan <span class="fw-semibold text-body">{{ $paginator->firstItem() }}</span> &ndash; <span class="fw-semibold text-body">{{ $paginator->lastItem() }}</span> dari <span class="fw-semibold text-body">{{ $paginator->total() }}</span> data
        </div>
        <nav class="ms-auto" aria-label="Navigasi Halaman">
            <ul class="pagination pagination-sm m-0 gap-1 align-items-center border-0">
                {{-- Previous Page Link --}}
                @if ($paginator->onFirstPage())
                    <li class="page-item disabled" aria-disabled="true" aria-label="Previous">
                        <span class="page-link border-0 rounded-2 bg-transparent text-muted opacity-50 px-2 py-1 d-inline-flex align-items-center" aria-hidden="true" style="font-size: 0.82rem;">
                            <i class="ti ti-chevron-left me-1 fs-3"></i> <span class="d-none d-sm-inline">Previous</span>
                        </span>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link border-0 rounded-2 text-secondary bg-transparent px-2 py-1 d-inline-flex align-items-center custom-page-nav" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous" style="font-size: 0.82rem;">
                            <i class="ti ti-chevron-left me-1 fs-3"></i> <span class="d-none d-sm-inline">Previous</span>
                        </a>
                    </li>
                @endif

                {{-- Pagination Elements --}}
                @foreach ($elements as $element)
                    {{-- "Three Dots" Separator --}}
                    @if (is_string($element))
                        <li class="page-item disabled" aria-disabled="true">
                            <span class="page-link border-0 bg-transparent text-muted px-2 py-1" style="min-width: 28px; text-align: center;">{{ $element }}</span>
                        </li>
                    @endif

                    {{-- Array Of Links --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <li class="page-item active" aria-current="page">
                                    <span class="page-link border-0 rounded-2 fw-bold text-white shadow-sm" style="background-color: var(--brand-blue, #195b9e); min-width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem;">
                                        {{ $page }}
                                    </span>
                                </li>
                            @else
                                <li class="page-item">
                                    <a class="page-link border-0 rounded-2 text-secondary bg-transparent custom-page-item" style="min-width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem;" href="{{ $url }}">
                                        {{ $page }}
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Next Page Link --}}
                @if ($paginator->hasMorePages())
                    <li class="page-item">
                        <a class="page-link border-0 rounded-2 text-secondary bg-transparent px-2 py-1 d-inline-flex align-items-center custom-page-nav" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next" style="font-size: 0.82rem;">
                            <span class="d-none d-sm-inline">Next</span> <i class="ti ti-chevron-right ms-1 fs-3"></i>
                        </a>
                    </li>
                @else
                    <li class="page-item disabled" aria-disabled="true" aria-label="Next">
                        <span class="page-link border-0 rounded-2 bg-transparent text-muted opacity-50 px-2 py-1 d-inline-flex align-items-center" aria-hidden="true" style="font-size: 0.82rem;">
                            <span class="d-none d-sm-inline">Next</span> <i class="ti ti-chevron-right ms-1 fs-3"></i>
                        </span>
                    </li>
                @endif
            </ul>
        </nav>
    </div>
@else
    <div class="d-flex align-items-center justify-content-between w-100">
        <div class="text-secondary small">
            Menampilkan <span class="fw-semibold text-body">{{ $paginator->total() }}</span> data
        </div>
    </div>
@endif
<style>
.custom-page-item:hover, .custom-page-nav:hover {
    background-color: rgba(16, 45, 82, 0.08) !important;
    color: var(--brand-blue, #195b9e) !important;
}
</style>
