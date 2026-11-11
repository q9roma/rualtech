<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Admin/Notifications', ['notifications' => $request->user()->notifications()->paginate(20)->through(fn ($n) => [
            'id' => $n->id, 'title' => strip_tags($n->data['title'] ?? 'Уведомление'),
            'body' => strip_tags($n->data['body'] ?? ''), 'read_at' => $n->read_at, 'created_at' => $n->created_at,
            'report' => $this->reportUrl($n->data),
        ])]);
    }

    private function reportUrl(array $data): ?string
    {
        if (isset($data['admin_import']) && \Illuminate\Support\Str::isUuid($data['admin_import'])) return '/admin/imports/'.$data['admin_import'].'/errors';
        foreach ($data['actions'] ?? [] as $action) {
            if (preg_match('~/imports/(\d+)/failed-rows/download~', $action['url'] ?? '', $match)) return '/admin/imports/legacy/'.$match[1].'/errors';
        }
        return null;
    }

    public function destroy(Request $request, string $notification)
    {
        $request->user()->notifications()->findOrFail($notification)->delete();
        return back();
    }

    public function read(Request $request, string $notification)
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();
        return back();
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return back()->with('success', 'Уведомления отмечены прочитанными.');
    }
}
