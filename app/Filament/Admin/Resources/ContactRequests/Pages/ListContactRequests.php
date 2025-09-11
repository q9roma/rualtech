<?php

namespace App\Filament\Admin\Resources\ContactRequests\Pages;

use App\Filament\Admin\Resources\ContactRequests\ContactRequestResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListContactRequests extends ListRecords
{
    protected static string $resource = ContactRequestResource::class;

    public function getTitle(): string
    {
        return 'Заявки';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Создать заявку'),
        ];
    }
}
