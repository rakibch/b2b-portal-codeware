<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** In-app notification centre (database channel). Users only ever see their own notifications. */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unread' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $user = $request->user();
        $query = $request->boolean('unread') ? $user->unreadNotifications() : $user->notifications();
        $page = $query->latest()->simplePaginate((int) ($validated['per_page'] ?? 15));

        return response()->json([
            'data' => collect($page->items())->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->data['type'] ?? null,
                'title' => $n->data['title'] ?? '',
                'body' => $n->data['body'] ?? '',
                'path' => $n->data['path'] ?? null,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ]),
            'unread_count' => $user->unreadNotifications()->count(),
            'has_more' => $page->hasMorePages(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        return response()->json(['unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['unread_count' => 0]);
    }
}
