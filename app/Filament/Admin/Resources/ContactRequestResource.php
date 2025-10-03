<?php

namespace App\Filament\Admin\Resources;

use App\Models\ContactRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\DateTimePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Infolists\Components\TextEntry;
use App\Filament\Admin\Resources\ContactRequestResource\Pages;

class ContactRequestResource extends Resource
{
    protected static ?string $model = ContactRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Заявки';

    protected static ?string $pluralModelLabel = 'заявки';

    protected static ?string $modelLabel = 'заявка';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Контактная информация')
                    ->schema([
                        TextInput::make('name')
                            ->label('Имя')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Телефон')
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('company')
                            ->label('Компания')
                            ->maxLength(255),
                    ])->columns(2),

                Section::make('Детали заявки')
                    ->schema([
                        TextInput::make('subject')
                            ->label('Тема')
                            ->maxLength(255),
                        TextInput::make('service')
                            ->label('Услуга')
                            ->maxLength(255),
                        Select::make('source')
                            ->label('Источник')
                            ->options(ContactRequest::getSources()),
                        Textarea::make('message')
                            ->label('Сообщение')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])->columns(3),

                Section::make('Статус и обработка')
                    ->schema([
                        Select::make('status')
                            ->label('Статус')
                            ->options(ContactRequest::getStatuses())
                            ->default(ContactRequest::STATUS_NEW)
                            ->required(),
                        DateTimePicker::make('processed_at')
                            ->label('Дата обработки'),
                        Textarea::make('admin_notes')
                            ->label('Заметки администратора')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                TextEntry::make('name')
                    ->label('Имя'),
                TextEntry::make('email')
                    ->label('Email')
                    ->copyable(),
                TextEntry::make('phone')
                    ->label('Телефон')
                    ->copyable(),
                TextEntry::make('company')
                    ->label('Компания'),
                TextEntry::make('subject')
                    ->label('Тема'),
                TextEntry::make('service')
                    ->label('Услуга'),
                TextEntry::make('source')
                    ->label('Источник')
                    ->formatStateUsing(fn ($state) => ContactRequest::getSources()[$state] ?? $state),
                TextEntry::make('message')
                    ->label('Сообщение')
                    ->columnSpanFull(),
                TextEntry::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'warning',
                        'in_progress' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'new' => 'Новая',
                        'in_progress' => 'В работе',
                        'completed' => 'Завершена',
                        'cancelled' => 'Отменена',
                        default => $state,
                    }),
                TextEntry::make('processed_at')
                    ->label('Дата обработки')
                    ->dateTime('d.m.Y H:i'),
                TextEntry::make('created_at')
                    ->label('Дата создания')
                    ->dateTime('d.m.Y H:i'),
                TextEntry::make('admin_notes')
                    ->label('Заметки администратора')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Имя')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('service')
                    ->label('Услуга')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'warning',
                        'in_progress' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'new' => 'Новая',
                        'in_progress' => 'В работе',
                        'completed' => 'Завершена',
                        'cancelled' => 'Отменена',
                        default => $state,
                    }),
                TextColumn::make('created_at')
                    ->label('Создана')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(ContactRequest::getStatuses()),
                SelectFilter::make('source')
                    ->label('Источник')
                    ->options(ContactRequest::getSources()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListContactRequests::route('/'),
            'create' => Pages\CreateContactRequest::route('/create'),
            'view' => Pages\ViewContactRequest::route('/{record}'),
            'edit' => Pages\EditContactRequest::route('/{record}/edit'),
        ];
    }
}
