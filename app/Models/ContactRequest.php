<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ContactRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'subject',
        'message',
        'service',
        'source',
        'status',
        'processed_at',
        'admin_notes',
        'meta_data'
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'meta_data' => 'array'
    ];

    // Статусы
    const STATUS_NEW = 'new';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_NEW => 'Новая',
            self::STATUS_IN_PROGRESS => 'В работе',
            self::STATUS_COMPLETED => 'Завершена',
            self::STATUS_CANCELLED => 'Отменена'
        ];
    }

    // Источники
    const SOURCE_WEBSITE = 'website';
    const SOURCE_SERVICE_PAGE = 'service_page';
    const SOURCE_CONTACT_FORM = 'contact_form';

    public static function getSources(): array
    {
        return [
            self::SOURCE_WEBSITE => 'Сайт',
            self::SOURCE_SERVICE_PAGE => 'Страница услуги',
            self::SOURCE_CONTACT_FORM => 'Форма обратной связи'
        ];
    }

    // Scopes
    public function scopeNew(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_NEW);
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    // Accessors
    public function getStatusLabelAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    public function getSourceLabelAttribute(): string
    {
        return self::getSources()[$this->source] ?? $this->source;
    }

    public function getIsNewAttribute(): bool
    {
        return $this->status === self::STATUS_NEW;
    }

    // Методы
    public function markAsInProgress(): void
    {
        $this->update([
            'status' => self::STATUS_IN_PROGRESS,
            'processed_at' => now()
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'processed_at' => now()
        ]);
    }

    public function addAdminNote(string $note): void
    {
        $currentNotes = $this->admin_notes ? $this->admin_notes . "\n\n" : '';
        $timestamp = now()->format('d.m.Y H:i');
        
        $this->update([
            'admin_notes' => $currentNotes . "[{$timestamp}] {$note}"
        ]);
    }
}
