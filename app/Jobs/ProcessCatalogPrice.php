<?php
namespace App\Jobs;

use App\Admin\CatalogPriceWorkflow;
use App\Models\{CatalogPriceUpload, Product, User};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Gate;

class ProcessCatalogPrice implements ShouldQueue
{
    use Queueable;
    public int $tries = 1;
    public int $timeout = 600;
    public function __construct(public string $id, public bool $apply = false) { $this->onQueue('catalog-prices'); }
    public function middleware(): array
    {
        return [(new \Illuminate\Queue\Middleware\WithoutOverlapping('catalog-price:'.$this->id))->dontRelease()->expireAfter(660)];
    }
    public function handle(CatalogPriceWorkflow $workflow): void
    {
        $upload = CatalogPriceUpload::findOrFail($this->id);
        Gate::forUser(User::findOrFail($upload->user_id))->authorize('create', Product::class);
        if ($this->apply) {
            $workflow->apply($upload);
        } elseif ($upload->status === 'queued') {
            $upload->update(['status' => 'preparing']);
            $workflow->prepare($upload);
        }
    }
    public function failed(?\Throwable $exception): void
    {
        CatalogPriceUpload::whereKey($this->id)->whereIn('status', ['queued', 'preparing', 'applying'])->update([
            'status' => 'failed', 'error' => $exception instanceof \App\Admin\CatalogPriceException ? $exception->getMessage().' Каталог не изменён.' : 'Не удалось завершить обработку на сервере. Каталог не изменён. Повторите проверку; если ошибка повторяется, обратитесь к администратору.',
        ]);
    }
}
