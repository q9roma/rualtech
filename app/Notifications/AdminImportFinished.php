<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class AdminImportFinished extends Notification
{
    public function __construct(public string $title, public string $body, public ?string $report = null) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toArray(object $notifiable): array
    {
        return array_filter(['title' => $this->title, 'body' => $this->body, 'admin_import' => $this->report]);
    }
}
