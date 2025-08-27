<?php

namespace App\Filament\Admin\Resources\Services\Pages;

use App\Filament\Admin\Resources\Services\ServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServices extends ListRecords
{
    protected static string $resource = ServiceResource::class;

    public function getTitle(): string
    {
        return 'Услуги';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Новая услуга'),
        ];
    }
}
