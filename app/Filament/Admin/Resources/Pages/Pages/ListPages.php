<?php

namespace App\Filament\Admin\Resources\Pages\Pages;

use App\Filament\Admin\Resources\Pages\PageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    public function getTitle(): string
    {
        return 'Страницы';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Новая страница'),
        ];
    }
}
