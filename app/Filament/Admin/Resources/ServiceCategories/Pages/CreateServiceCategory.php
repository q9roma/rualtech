<?php

namespace App\Filament\Admin\Resources\ServiceCategories\Pages;

use App\Filament\Admin\Resources\ServiceCategories\ServiceCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateServiceCategory extends CreateRecord
{
    protected static string $resource = ServiceCategoryResource::class;

    public function getTitle(): string
    {
        return 'Создать категорию услуг';
    }
}
