@props(['icon', 'class' => 'w-6 h-6'])

@if($icon)
    {{-- Просто используем Heroicons через x-dynamic-component --}}
    <x-dynamic-component :component="'heroicon-o-' . $icon" {{ $attributes->merge(['class' => $class]) }} />
@endif
