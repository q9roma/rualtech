@php
    $id = $getId();
    $isDisabled = $isDisabled();
    $statePath = $getStatePath();
    $placeholder = $getPlaceholder();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div 
        x-data="{ 
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            updateIcon(value) {
                this.state = value;
            }
        }"
        x-on:icon-selected.window="updateIcon($event.detail)"
    >
        @livewire('icon-selector', [
            'value' => $getState(),
            'placeholder' => $placeholder,
            'label' => null
        ])
    </div>
</x-dynamic-component>
