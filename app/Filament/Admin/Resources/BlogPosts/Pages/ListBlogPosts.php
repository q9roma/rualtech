<?php

namespace App\Filament\Admin\Resources\BlogPosts\Pages;

use App\Filament\Admin\Resources\BlogPosts\BlogPostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBlogPosts extends ListRecords
{
    protected static string $resource = BlogPostResource::class;

    public function getTitle(): string
    {
        return 'Статьи блога';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Новая статья'),
        ];
    }
}
