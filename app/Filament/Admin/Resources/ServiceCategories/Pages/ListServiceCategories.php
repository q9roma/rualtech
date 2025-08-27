<?php

namespace App\Filament\Admin\Resources\ServiceCategories\Pages;

use App\Filament\Admin\Resources\ServiceCategories\ServiceCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServiceCategories extends ListRecords
{
    protected static string $resource = ServiceCategoryResource::class;

    public function getTitle(): string
    {
        return 'Категории услуг';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Новая категория услуг'),
        ];
    }
}
