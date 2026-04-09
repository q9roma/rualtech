<?php

namespace App\Filament\Admin\Resources\Products\Tables;

use App\Filament\Imports\ProductImporter;
use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ImportAction;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category')->label('Категория')->searchable()->sortable()->toggleable(),
                TextColumn::make('brand')->label('Бренд')->searchable()->sortable()->badge()->color('primary'),
                TextColumn::make('sku')->label('Артикул')->searchable()->copyable()->toggleable(),
                TextColumn::make('name')->label('Наименование')->searchable()->limit(60)->tooltip(fn ($record) => $record->name),
                TextColumn::make('slug')->label('Slug')->toggleable(isToggledHiddenByDefault: true)->copyable(),
                TextColumn::make('availability')->label('Наличие')->badge()
                    ->color(fn (?string $state): string => match (true) {
                        str_contains((string) $state, 'заказ') => 'warning',
                        is_numeric(str_replace(['>', '<', ' '], '', (string) $state)) && (int) str_replace(['>', '<', ' '], '', (string) $state) > 0 => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('price')->label('Цена')->money('RUB')->sortable(),
                IconColumn::make('is_active')->label('Активен')->boolean(),
                TextColumn::make('updated_at')->label('Обновлён')->dateTime('d.m.Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('brand')->label('Бренд')
                    ->options(fn () => Product::query()->distinct()->orderBy('brand')->pluck('brand', 'brand')->filter()->toArray())
                    ->getOptionLabelUsing(fn (?string $value): ?string => $value),
                SelectFilter::make('category')->label('Категория')
                    ->options(fn () => Product::query()->distinct()->orderBy('category')->pluck('category', 'category')->filter()->toArray())
                    ->getOptionLabelUsing(fn (?string $value): ?string => $value),
                TernaryFilter::make('is_active')->label('Активные'),
            ])
            ->headerActions([
                ImportAction::make()
                    ->importer(ProductImporter::class)
                    ->label('Импорт из файла')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    // В русской локали Excel чаще выдаёт CSV с «;» и ценами вида «2 800,00». Авто-определение
                    // разделителя иногда выбирает «,» — ломаются колонки, часть строк уходит в ошибки (кажется, что «слито по SKU»).
                    ->csvDelimiter(fn (): ?string => app()->getLocale() === 'ru' ? ';' : null)
                    ->modalDescription(function (ImportAction $action): Htmlable {
                        $downloadExample = $action->getModalAction('downloadExample');

                        return new HtmlString(
                            '<p class="mb-3 text-sm text-gray-600 dark:text-gray-400">'
                            . 'Нужен файл CSV (в Excel: «Сохранить как» → «CSV UTF-8»). '
                            . 'После импорта смотрите уведомление: число успешных строк и при расхождении — CSV с ошибками.'
                            . '</p>'
                            . ($downloadExample?->toHtml() ?? '')
                        );
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('brand')
            ->striped();
    }
}
