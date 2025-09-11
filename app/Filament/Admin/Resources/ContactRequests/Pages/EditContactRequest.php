<?php

namespace App\Filament\Admin\Resources\ContactRequests\Pages;

use App\Filament\Admin\Resources\ContactRequests\ContactRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContactRequest extends EditRecord
{
    protected static string $resource = ContactRequestResource::class;

    public function getTitle(): string
    {
        return 'Редактировать заявку #' . $this->record->id;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->label('Просмотр'),
            Actions\DeleteAction::make()
                ->label('Удалить'),
        ];
    }
}
