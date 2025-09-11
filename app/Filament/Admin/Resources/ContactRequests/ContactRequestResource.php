<?php

namespace App\Filament\Admin\Resources\ContactRequests;

use App\Filament\Admin\Resources\ContactRequests\Pages\CreateContactRequest;
use App\Filament\Admin\Resources\ContactRequests\Pages\EditContactRequest;
use App\Filament\Admin\Resources\ContactRequests\Pages\ListContactRequests;
use App\Filament\Admin\Resources\ContactRequests\Pages\ViewContactRequest;
use App\Models\ContactRequest;
use BackedEnum;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;

class ContactRequestResource extends Resource
{
    protected static ?string $model = ContactRequest::class;
    
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;
    
    protected static ?string $navigationLabel = 'Заявки';
    
    protected static ?string $modelLabel = 'Заявка';
    
    protected static ?string $pluralModelLabel = 'Заявки';
    
    // protected static ?string $navigationGroup = 'Контент';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Информация о клиенте')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Имя')
                            ->required()
                            ->maxLength(100),
                        
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('phone')
                            ->label('Телефон')
                            ->tel()
                            ->maxLength(20),
                        
                        Forms\Components\TextInput::make('company')
                            ->label('Компания')
                            ->maxLength(100),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Заявка')
                    ->schema([
                        Forms\Components\TextInput::make('subject')
                            ->label('Тема')
                            ->required()
                            ->maxLength(200),
                        
                        Forms\Components\TextInput::make('service')
                            ->label('Услуга')
                            ->maxLength(200),
                        
                        Forms\Components\Textarea::make('message')
                            ->label('Сообщение')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                
                Forms\Components\Section::make('Управление')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Статус')
                            ->options(ContactRequest::getStatuses())
                            ->required()
                            ->default(ContactRequest::STATUS_NEW),
                        
                        Forms\Components\Select::make('source')
                            ->label('Источник')
                            ->options(ContactRequest::getSources())
                            ->required()
                            ->default(ContactRequest::SOURCE_WEBSITE),
                        
                        Forms\Components\DateTimePicker::make('processed_at')
                            ->label('Обработана')
                            ->nullable(),
                        
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Заметки администратора')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('name')
                    ->label('Имя')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable()
                    ->copyable(),
                
                Tables\Columns\TextColumn::make('subject')
                    ->label('Тема')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(function (ContactRequest $record): ?string {
                        return strlen($record->subject) > 40 ? $record->subject : null;
                    }),
                
                Tables\Columns\TextColumn::make('service')
                    ->label('Услуга')
                    ->searchable()
                    ->limit(30)
                    ->badge()
                    ->color('info'),
                
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Статус')
                    ->formatStateUsing(fn (string $state): string => ContactRequest::getStatuses()[$state] ?? $state)
                    ->colors([
                        'danger' => ContactRequest::STATUS_NEW,
                        'warning' => ContactRequest::STATUS_IN_PROGRESS,
                        'success' => ContactRequest::STATUS_COMPLETED,
                        'secondary' => ContactRequest::STATUS_CANCELLED,
                    ]),
                
                Tables\Columns\TextColumn::make('source')
                    ->label('Источник')
                    ->formatStateUsing(fn (string $state): string => ContactRequest::getSources()[$state] ?? $state)
                    ->badge()
                    ->color('secondary'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создана')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Обработана')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('Не обработана'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options(ContactRequest::getStatuses()),
                
                Tables\Filters\SelectFilter::make('source')
                    ->label('Источник')
                    ->options(ContactRequest::getSources()),
                
                Tables\Filters\Filter::make('unprocessed')
                    ->label('Необработанные')
                    ->query(fn (Builder $query): Builder => $query->whereNull('processed_at')),
                
                Tables\Filters\Filter::make('today')
                    ->label('За сегодня')
                    ->query(fn (Builder $query): Builder => $query->whereDate('created_at', today())),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Просмотр'),
                Tables\Actions\EditAction::make()
                    ->label('Редактировать'),
                Tables\Actions\Action::make('mark_in_progress')
                    ->label('В работу')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->visible(fn (ContactRequest $record): bool => $record->status === ContactRequest::STATUS_NEW)
                    ->action(fn (ContactRequest $record) => $record->markAsInProgress())
                    ->requiresConfirmation(),
                
                Tables\Actions\Action::make('mark_completed')
                    ->label('Завершить')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (ContactRequest $record): bool => $record->status !== ContactRequest::STATUS_COMPLETED)
                    ->action(fn (ContactRequest $record) => $record->markAsCompleted())
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Удалить'),
                    Tables\Actions\BulkAction::make('mark_in_progress')
                        ->label('Отметить как "В работе"')
                        ->icon('heroicon-o-play')
                        ->color('warning')
                        ->action(function ($records) {
                            $records->each->markAsInProgress();
                        })
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Информация о клиенте')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Имя'),
                        
                        Infolists\Components\TextEntry::make('email')
                            ->label('Email')
                            ->copyable(),
                        
                        Infolists\Components\TextEntry::make('phone')
                            ->label('Телефон')
                            ->copyable(),
                        
                        Infolists\Components\TextEntry::make('company')
                            ->label('Компания'),
                    ])
                    ->columns(2),
                
                Infolists\Components\Section::make('Заявка')
                    ->schema([
                        Infolists\Components\TextEntry::make('subject')
                            ->label('Тема'),
                        
                        Infolists\Components\TextEntry::make('service')
                            ->label('Услуга')
                            ->badge()
                            ->color('info'),
                        
                        Infolists\Components\TextEntry::make('message')
                            ->label('Сообщение')
                            ->prose()
                            ->columnSpanFull(),
                    ]),
                
                Infolists\Components\Section::make('Управление')
                    ->schema([
                        Infolists\Components\TextEntry::make('status')
                            ->label('Статус')
                            ->formatStateUsing(fn (string $state): string => ContactRequest::getStatuses()[$state] ?? $state)
                            ->badge()
                            ->color(fn (string $state): string => match($state) {
                                ContactRequest::STATUS_NEW => 'danger',
                                ContactRequest::STATUS_IN_PROGRESS => 'warning',
                                ContactRequest::STATUS_COMPLETED => 'success',
                                ContactRequest::STATUS_CANCELLED => 'secondary',
                                default => 'secondary',
                            }),
                        
                        Infolists\Components\TextEntry::make('source')
                            ->label('Источник')
                            ->formatStateUsing(fn (string $state): string => ContactRequest::getSources()[$state] ?? $state),
                        
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Создана')
                            ->dateTime('d.m.Y H:i'),
                        
                        Infolists\Components\TextEntry::make('processed_at')
                            ->label('Обработана')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('Не обработана'),
                        
                        Infolists\Components\TextEntry::make('admin_notes')
                            ->label('Заметки администратора')
                            ->prose()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                
                Infolists\Components\Section::make('Техническая информация')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('meta_data')
                            ->label('Дополнительные данные')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactRequests::route('/'),
            'create' => CreateContactRequest::route('/create'),
            'view' => ViewContactRequest::route('/{record}'),
            'edit' => EditContactRequest::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', ContactRequest::STATUS_NEW)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }
}
