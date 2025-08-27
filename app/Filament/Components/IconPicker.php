<?php

namespace App\Filament\Components;

use App\Helpers\IconHelper;
use Filament\Forms\Components\Field;
use Filament\Support\Concerns\HasExtraAlpineAttributes;

class IconPicker extends Field
{
    use HasExtraAlpineAttributes;

    protected string $view = 'filament.components.icon-picker';
    
    protected ?string $placeholder = null;

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;
        return $this;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    public function getOptions(): array
    {
        return IconHelper::getAvailableIcons();
    }

    public function getState(): mixed
    {
        $state = parent::getState();
        
        if (blank($state)) {
            return null;
        }

        return $state;
    }
}
