<?php

namespace App\Filament\Admin\Resources\BlogPosts;

use App\Filament\Admin\Resources\BlogPosts\Pages\CreateBlogPost;
use App\Filament\Admin\Resources\BlogPosts\Pages\EditBlogPost;
use App\Filament\Admin\Resources\BlogPosts\Pages\ListBlogPosts;
use App\Filament\Admin\Resources\BlogPosts\Pages\ViewBlogPost;
use App\Filament\Admin\Resources\BlogPosts\Schemas\BlogPostForm;
use App\Filament\Admin\Resources\BlogPosts\Schemas\BlogPostInfolist;
use App\Filament\Admin\Resources\BlogPosts\Tables\BlogPostsTable;
use App\Models\BlogPost;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;
    
    protected static ?string $navigationLabel = 'Блог';
    
    protected static ?string $modelLabel = 'запись блога';
    
    protected static ?string $pluralModelLabel = 'записи блога';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return BlogPostForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BlogPostInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BlogPostsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlogPosts::route('/'),
            'create' => CreateBlogPost::route('/create'),
            'view' => ViewBlogPost::route('/{record}'),
            'edit' => EditBlogPost::route('/{record}/edit'),
        ];
    }
}
