<?php

namespace App\Filament\Admin\Resources\Orders\Pages;

use App\Commerce\OrderWorkflow;
use App\Filament\Admin\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Forms\Components\{Select, Textarea, Hidden};
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('changeStatus')->label('Изменить статус')
            ->modalHeading('Изменение статуса заказа')
            ->modalDescription('Проверьте выбранный статус. Завершение и отмена окончательны. Оплата, возврат денег и уведомления этим действием не выполняются.')
            ->modalSubmitActionLabel('Сохранить статус')
            ->visible(fn () => Gate::allows('update', $this->record) && OrderWorkflow::options($this->record->status) !== [])
            ->fillForm(fn () => ['expected' => $this->record->status])
            ->schema([
                Hidden::make('expected')->required(),
                Select::make('status')->label('Новый статус')->options(fn () => OrderWorkflow::options($this->record->status))->required(),
                Textarea::make('reason')->label('Комментарий к изменению')->required()->maxLength(1000),
            ])->action(function (array $data) {
                app(OrderWorkflow::class)->change($this->record, auth()->user(), $data['expected'], $data['status'], $data['reason']);
                $this->record->refresh();
                $this->refreshFormData(['status']);
                Notification::make()->title('Статус обновлён')->success()->send();
            })];
    }
}
