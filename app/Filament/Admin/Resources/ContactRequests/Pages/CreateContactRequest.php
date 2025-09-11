<?php

namespace App\Filament\Admin\Resources\ContactRequests\Pages;

use App\Filament\Admin\Resources\ContactRequests\ContactRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateContactRequest extends CreateRecord
{
    protected static string $resource = ContactRequestResource::class;

    public function getTitle(): string
    {
        return 'Создать заявку';
    }
}
