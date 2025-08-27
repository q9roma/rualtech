@php
    $id = $getId();
    $isDisabled = $isDisabled();
    $statePath = $getStatePath();
    $options = $getOptions();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            isOpen: false,
            search: '',
            get filteredOptions() {
                const options = @js($options);
                if (!this.search) return options;
                
                return Object.fromEntries(
                    Object.entries(options).filter(([key, value]) => 
                        key.toLowerCase().includes(this.search.toLowerCase()) ||
                        value.toLowerCase().includes(this.search.toLowerCase())
                    )
                );
            }
        }"
        class="relative"
    >
        <!-- Кнопка выбора -->
        <button
            type="button"
            x-on:click="isOpen = !isOpen"
            @if ($isDisabled) disabled @endif
            class="relative w-full border border-gray-300 bg-white rounded-lg shadow-sm pl-3 pr-10 py-2 text-left cursor-pointer focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500 sm:text-sm {{ $isDisabled ? 'opacity-50 cursor-not-allowed' : '' }}"
        >
            <span class="flex items-center">
                <template x-if="state">
                    <div class="flex items-center space-x-2">
                        <!-- Превью выбранной иконки -->
                        <div x-show="state && state.endsWith('.svg')">
                            <img :src="'/icons/' + state" class="w-5 h-5" alt="Иконка">
                        </div>
                        <div x-show="state && !state.endsWith('.svg')" class="w-5 h-5 flex items-center justify-center">
                            <span class="text-xs text-gray-500" x-text="state"></span>
                        </div>
                        <span x-text="@js($options)[state] || state" class="block truncate"></span>
                    </div>
                </template>
                <template x-if="!state">
                    <span class="block truncate text-gray-500">{{ $getPlaceholder() ?? 'Выберите иконку' }}</span>
                </template>
            </span>
            <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3z" clip-rule="evenodd" />
                </svg>
            </span>
        </button>

        <!-- Выпадающий список -->
        <div
            x-show="isOpen"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click.away="isOpen = false"
            class="absolute z-10 mt-1 w-full bg-white shadow-lg max-h-96 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm"
        >
            <!-- Поиск -->
            <div class="sticky top-0 z-10 bg-white border-b border-gray-200 px-3 py-2">
                <input
                    type="text"
                    x-model="search"
                    placeholder="Поиск иконки..."
                    class="w-full border border-gray-300 rounded-md px-3 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                >
            </div>

            <!-- Опция "Без иконки" -->
            <div
                x-on:click="state = null; isOpen = false"
                class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-gray-50"
                :class="{ 'bg-primary-50 text-primary-900': !state }"
            >
                <div class="flex items-center space-x-2">
                    <div class="w-5 h-5 border border-gray-300 rounded flex items-center justify-center">
                        <span class="text-xs text-gray-400">—</span>
                    </div>
                    <span class="block truncate text-gray-500">Без иконки</span>
                </div>
                <span x-show="!state" class="absolute inset-y-0 right-0 flex items-center pr-4 text-primary-600">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                </span>
            </div>

            <!-- Список иконок -->
            <template x-for="[key, value] in Object.entries(filteredOptions)" :key="key">
                <div
                    x-on:click="state = key; isOpen = false"
                    class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-gray-50"
                    :class="{ 'bg-primary-50 text-primary-900': state === key }"
                >
                    <div class="flex items-center space-x-2">
                        <!-- SVG иконки -->
                        <div x-show="key.endsWith('.svg')" class="w-5 h-5">
                            <img :src="'/icons/' + key" class="w-5 h-5" alt="Иконка">
                        </div>
                        <!-- Heroicons -->
                        <div x-show="!key.endsWith('.svg')" class="w-5 h-5 flex items-center justify-center bg-gray-100 rounded text-xs text-gray-600">
                            H
                        </div>
                        <span class="block truncate" x-text="value"></span>
                    </div>
                    <span x-show="state === key" class="absolute inset-y-0 right-0 flex items-center pr-4 text-primary-600">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </span>
                </div>
            </template>

            <!-- Нет результатов -->
            <div x-show="Object.keys(filteredOptions).length === 0" class="py-2 px-3 text-gray-500 text-sm">
                Иконки не найдены
            </div>
        </div>
    </div>
</x-dynamic-component>
