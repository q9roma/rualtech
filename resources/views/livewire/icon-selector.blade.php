<div class="relative" x-data="{ open: @entangle('isOpen') }">
    <!-- Лейбл -->
    @if($label)
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</label>
    @endif

    <!-- Кнопка выбора -->
    <button
        type="button"
        wire:click="toggleDropdown"
        class="relative w-full bg-white border border-gray-300 rounded-lg shadow-sm pl-3 pr-10 py-2 text-left cursor-pointer focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm hover:bg-gray-50"
    >
        <span class="flex items-center">
            @if($selectedIcon)
                <div class="flex items-center space-x-3">
                    <!-- Иконка -->
                    <div class="flex-shrink-0">
                        @if(str_ends_with($selectedIcon, '.svg'))
                            <img src="{{ asset('icons/' . $selectedIcon) }}" class="w-5 h-5" alt="Иконка">
                        @else
                            <div class="w-5 h-5 bg-blue-100 rounded flex items-center justify-center">
                                <span class="text-blue-600 text-xs font-bold">H</span>
                            </div>
                        @endif
                    </div>
                    <!-- Название -->
                    <span class="block truncate text-gray-900">
                        {{ $allIcons[$selectedIcon] ?? $selectedIcon }}
                    </span>
                </div>
            @else
                <span class="block truncate text-gray-500">{{ $placeholder }}</span>
            @endif
        </span>
        
        <!-- Стрелка -->
        <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
            <svg class="h-5 w-5 text-gray-400 transition-transform duration-150" 
                 :class="{'rotate-180': open}"
                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </span>
    </button>

    <!-- Выпадающий список -->
    <div x-show="open"
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="transform opacity-0 scale-95"
         x-transition:enter-end="transform opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="transform opacity-100 scale-100"
         x-transition:leave-end="transform opacity-0 scale-95"
         class="absolute z-10 mt-1 w-full bg-white shadow-lg max-h-80 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-hidden focus:outline-none sm:text-sm">
        
        <!-- Поиск -->
        <div class="sticky top-0 z-10 bg-white border-b border-gray-200 px-3 py-2">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Поиск иконки..."
                class="w-full border border-gray-300 rounded-md px-3 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
            >
        </div>

        <!-- Прокручиваемый список -->
        <div class="max-h-64 overflow-auto">
            <!-- Опция "Без иконки" -->
            <div wire:click="clearIcon"
                 class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-gray-50 {{ !$selectedIcon ? 'bg-indigo-50 text-indigo-900' : 'text-gray-900' }}">
                <div class="flex items-center space-x-3">
                    <div class="w-5 h-5 border border-gray-300 rounded flex items-center justify-center">
                        <span class="text-xs text-gray-400">×</span>
                    </div>
                    <span class="block truncate text-gray-500">Без иконки</span>
                </div>
                @if(!$selectedIcon)
                    <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-indigo-600">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </span>
                @endif
            </div>

            <!-- Список иконок -->
            @forelse($icons as $key => $name)
                <div wire:click="selectIcon('{{ $key }}')"
                     class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-gray-50 {{ $selectedIcon === $key ? 'bg-indigo-50 text-indigo-900' : 'text-gray-900' }}">
                    <div class="flex items-center space-x-3">
                        <!-- Иконка -->
                        <div class="flex-shrink-0">
                            @if(str_ends_with($key, '.svg'))
                                <img src="{{ asset('icons/' . $key) }}" class="w-5 h-5" alt="{{ $name }}">
                            @else
                                <div class="w-5 h-5 bg-blue-100 rounded flex items-center justify-center">
                                    <span class="text-blue-600 text-xs font-bold">H</span>
                                </div>
                            @endif
                        </div>
                        
                        <!-- Название -->
                        <div class="flex flex-col">
                            <span class="block truncate font-medium">{{ $name }}</span>
                            <span class="block truncate text-xs text-gray-500">{{ $key }}</span>
                        </div>
                    </div>
                    
                    <!-- Галочка для выбранного -->
                    @if($selectedIcon === $key)
                        <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-indigo-600">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    @endif
                </div>
            @empty
                <div class="py-4 px-3 text-gray-500 text-sm text-center">
                    Иконки не найдены
                </div>
            @endforelse
        </div>
    </div>

    <!-- Закрытие при клике вне -->
    <div x-show="open" x-on:click="open = false" class="fixed inset-0 z-0"></div>
</div>
