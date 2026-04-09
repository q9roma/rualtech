@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{!! __('Pagination Navigation') !!}" class="flex justify-between">
        @if ($paginator->onFirstPage())
            <span class="relative inline-flex cursor-default items-center rounded-md border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-400">
                {!! __('pagination.previous') !!}
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="relative inline-flex items-center rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                {!! __('pagination.previous') !!}
            </a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="relative inline-flex items-center rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                {!! __('pagination.next') !!}
            </a>
        @else
            <span class="relative inline-flex cursor-default items-center rounded-md border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-400">
                {!! __('pagination.next') !!}
            </span>
        @endif
    </nav>
@endif
