<?php

namespace App\Filament\Admin\Resources\Orders;

use App\Commerce\OrderWorkflow;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\{TextEntry, RepeatableEntry};
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\ViewAction;
use Illuminate\Database\Eloquent\Builder;
use Lunar\Models\Order;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static string|\BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedShoppingBag;
    protected static ?string $navigationLabel = 'Заказы';
    protected static ?string $modelLabel = 'заказ';
    protected static ?string $pluralModelLabel = 'Заказы';
    protected static ?string $recordTitleAttribute = 'reference';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotNull('placed_at')->with(['billingAddress', 'currency']);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('placed_at', 'desc')->columns([
            TextColumn::make('reference')->label('Номер')->searchable(),
            TextColumn::make('status')->label('Статус')->wrap()->formatStateUsing(fn ($state) => OrderWorkflow::LABELS[$state] ?? $state),
            TextColumn::make('total')->label('Итого')->formatStateUsing(fn ($state) => $state->formatted),
            TextColumn::make('placed_at')->label('Создан')->dateTime('d.m.Y H:i')->sortable()->visibleFrom('md'),
            TextColumn::make('billingAddress.last_name')->label('Фамилия')->searchable()->visibleFrom('md'),
            TextColumn::make('billingAddress.contact_email')->label('Email')->searchable()->visibleFrom('md'),
        ])->filters([SelectFilter::make('status')->label('Статус')->options(OrderWorkflow::LABELS)])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Заказ')->schema([
                TextEntry::make('reference')->label('Номер'),
                TextEntry::make('status')->label('Статус')->formatStateUsing(fn ($state) => OrderWorkflow::LABELS[$state] ?? $state),
                TextEntry::make('placed_at')->label('Создан')->dateTime('d.m.Y H:i'),
                TextEntry::make('total')->label('Итого')->formatStateUsing(fn ($state) => $state->formatted),
                TextEntry::make('meta.prototype')->label('Тестовый заказ')->formatStateUsing(fn ($state) => $state ? 'Да — без оплаты и резервирования' : 'Нет'),
                TextEntry::make('meta.notes')->label('Комментарий покупателя')->placeholder('Не указан'),
            ])->columns(2)->columnSpanFull(),
            Section::make('Контакты покупателя')->schema(array_map(
                fn ($label, $field) => TextEntry::make('billingAddress.'.$field)->label($label)->placeholder('Не указано'),
                ['Имя', 'Фамилия', 'Email', 'Телефон', 'Организация', 'Город', 'Адрес', 'Индекс'],
                ['first_name', 'last_name', 'contact_email', 'contact_phone', 'company_name', 'city', 'line_one', 'postcode']
            ))->columns(2)->columnSpanFull(),
            RepeatableEntry::make('lines')->label('Состав и доставка — снимок при оформлении')->schema([
                TextEntry::make('description')->label('Наименование'),
                TextEntry::make('meta.sku')->label('Артикул')->placeholder('—'),
                TextEntry::make('quantity')->label('Количество'),
                TextEntry::make('unit_price')->label('Цена')->formatStateUsing(fn ($state) => $state->formatted),
                TextEntry::make('total')->label('Сумма')->formatStateUsing(fn ($state) => $state->formatted),
            ])->columns(3)->columnSpanFull(),
            RepeatableEntry::make('status_history')->label('История статусов')->state(fn (Order $record) =>
                $record->activities()->where('log_name', 'order-status')->with('causer')->latest('id')->get()->map(fn ($log) => [
                    'date' => $log->created_at->format('d.m.Y H:i:s'), 'actor' => $log->causer?->name ?? 'Удалённый сотрудник',
                    'transition' => (OrderWorkflow::LABELS[$log->properties['from']] ?? $log->properties['from']).' → '.(OrderWorkflow::LABELS[$log->properties['to']] ?? $log->properties['to']),
                    'reason' => $log->properties['reason'],
                ])->all())->schema([
                    TextEntry::make('date')->label('Когда'), TextEntry::make('actor')->label('Кто'),
                    TextEntry::make('transition')->label('Переход'), TextEntry::make('reason')->label('Комментарий'),
                ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListOrders::route('/'), 'view' => Pages\ViewOrder::route('/{record}')];
    }
}
