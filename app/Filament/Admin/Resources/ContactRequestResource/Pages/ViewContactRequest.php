<?php

namespace App\Filament\Admin\Resources\ContactRequestResource\Pages;

use App\Filament\Admin\Resources\ContactRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewContactRequest extends ViewRecord
{
    protected static string $resource = ContactRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}