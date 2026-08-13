<?php

namespace App\Http\Controllers;

use App\Support\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();
        $notifications = $user->notifications()->latest()->paginate(25);

        $items = $notifications->getCollection()->map(function ($notification) {
            return [
                'raw' => $notification,
                'presented' => NotificationPresenter::present($notification),
            ];
        });

        $notifications->setCollection($items);

        $view = $request->routeIs('admin.*')
            ? 'admin.notifications.index'
            : 'storefront.account.notifications';

        return view($view, [
            'notifications' => $notifications,
        ]);
    }

    public function feed(): JsonResponse
    {
        $user = Auth::user();

        $items = $user->notifications()
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn ($notification) => NotificationPresenter::present($notification))
            ->values();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $items,
        ]);
    }

    public function markRead(string $id): JsonResponse|RedirectResponse
    {
        $notification = Auth::user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'ok' => true,
                'unread_count' => Auth::user()->unreadNotifications()->count(),
            ]);
        }

        $url = NotificationPresenter::present($notification)['url'];

        return $url
            ? redirect()->to($url)
            : back()->with('success', 'Notification marked as read.');
    }

    public function markAllRead(): JsonResponse|RedirectResponse
    {
        Auth::user()->unreadNotifications->markAsRead();

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'ok' => true,
                'unread_count' => 0,
            ]);
        }

        return back()->with('success', 'All notifications marked as read.');
    }
}
