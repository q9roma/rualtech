@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between">
        <div class="flex flex-1 justify-between sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="relative inline-flex items-center rounded-md border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-400">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="relative inline-flex items-center rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="relative ml-3 inline-flex items-center rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="relative ml-3 inline-flex items-center rounded-md border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-400">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>

        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-600">
                    @if ($paginator->firstItem())
                        <span class="font-medium text-gray-900">{{ $paginator->firstItem() }}</span>
                        —
                        <span class="font-medium text-gray-900">{{ $paginator->lastItem() }}</span>
                        из
                        <span class="font-medium text-gray-900">{{ $paginator->total() }}</span>
                    @else
                        {{ $paginator->count() }}
                    @endif
                </p>
            </div>

            <div>
                <span class="relative z-0 inline-flex overflow-hidden rounded-lg shadow-sm ring-1 ring-gray-900/5">
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                            <span class="relative inline-flex items-center bg-gray-50 px-2 py-2 text-gray-400" aria-hidden="true">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                </svg>
                            </span>
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="relative inline-flex items-center bg-white px-2 py-2 text-gray-600 hover:bg-gray-50 hover:text-gray-900" aria-label="{{ __('pagination.previous') }}">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                        </a>
                    @endif

                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span aria-disabled="true">
                                <span class="relative inline-flex items-center border-l border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-500">{{ $element }}</span>
                            </span>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page">
                                        <span class="relative inline-flex items-center border-l border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white">{{ $page }}</span>
                                    </span>
                                @else
                                    <a href="{{ $url }}" class="relative inline-flex items-center border-l border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 hover:text-blue-600" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="relative inline-flex items-center border-l border-gray-200 bg-white px-2 py-2 text-gray-600 hover:bg-gray-50 hover:text-gray-900" aria-label="{{ __('pagination.next') }}">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    @else
                        <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                            <span class="relative inline-flex items-center border-l border-gray-200 bg-gray-50 px-2 py-2 text-gray-400" aria-hidden="true">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </span>
                        </span>
                    @endif
                </span>
            </div>
        </div>
    </nav>
@endif
