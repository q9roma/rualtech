<?php

namespace App\Livewire;

use App\Helpers\IconHelper;
use Livewire\Component;

class IconSelector extends Component
{
    public $selectedIcon = null;
    public $isOpen = false;
    public $search = '';
    public $placeholder = 'Выберите иконку';
    public $label = 'Иконка';

    public function mount($value = null, $placeholder = null, $label = null)
    {
        $this->selectedIcon = $value;
        if ($placeholder) $this->placeholder = $placeholder;
        if ($label) $this->label = $label;
    }

    public function selectIcon($icon)
    {
        $this->selectedIcon = $icon;
        $this->isOpen = false;
        $this->search = '';
        $this->dispatch('iconSelected', $icon);
    }

    public function clearIcon()
    {
        $this->selectedIcon = null;
        $this->isOpen = false;
        $this->dispatch('iconSelected', null);
    }

    public function toggleDropdown()
    {
        $this->isOpen = !$this->isOpen;
        if (!$this->isOpen) {
            $this->search = '';
        }
    }

    public function getFilteredIconsProperty()
    {
        $icons = IconHelper::getAvailableIcons();
        
        if (empty($this->search)) {
            return $icons;
        }

        return array_filter($icons, function($label, $key) {
            return str_contains(strtolower($label), strtolower($this->search)) ||
                   str_contains(strtolower($key), strtolower($this->search));
        }, ARRAY_FILTER_USE_BOTH);
    }

    public function render()
    {
        return view('livewire.icon-selector', [
            'icons' => $this->filteredIcons,
            'allIcons' => IconHelper::getAvailableIcons()
        ]);
    }
}
