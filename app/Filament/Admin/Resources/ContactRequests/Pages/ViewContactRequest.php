<?php

namespace App\Filament\Admin\Resources\ContactRequests\Pages;

use App\Filament\Admin\Resources\ContactRequests\ContactRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewContactRequest extends ViewRecord
{
    protected static string $resource = ContactRequestResource::class;

    public function getTitle(): string
    {
        return 'Просмотр заявки #' . $this->record->id;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Редактировать'),
            Actions\DeleteAction::make()
                ->label('Удалить'),
        ];
    }
}
