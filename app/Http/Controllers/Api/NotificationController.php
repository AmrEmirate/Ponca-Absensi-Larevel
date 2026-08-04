<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * GET /api/notifications
     * Retrieve notifications for the current authenticated user (or all Admin notifications if user is Admin)
     */
    public function index(Request $request)
    {
        $jwtUser = $request->attributes->get('user');

        $query = Notification::query();

        if ($jwtUser->role === 'ADMIN') {
            $query->where(function ($q) use ($jwtUser) {
                $q->whereNull('user_id')
                  ->orWhere('user_id', $jwtUser->id);
            });
        } else {
            $query->where('user_id', $jwtUser->id);
        }

        $notifications = $query->orderBy('id', 'desc')->take(50)->get()->map(function ($n) {
            return [
                'id' => $n->id,
                'userId' => $n->user_id,
                'user_id' => $n->user_id,
                'title' => $n->title,
                'message' => $n->message,
                'type' => $n->type,
                'isRead' => (bool) $n->is_read,
                'is_read' => (bool) $n->is_read,
                'createdAt' => $n->created_at ? $n->created_at->toIso8601String() : null,
                'created_at' => $n->created_at ? $n->created_at->toIso8601String() : null,
            ];
        });

        $unreadCount = $notifications->where('isRead', false)->count();

        return response()->json([
            'unreadCount' => $unreadCount,
            'unread_count' => $unreadCount,
            'data' => $notifications,
        ]);
    }

    /**
     * POST /api/notifications/{id}/read
     */
    public function markAsRead(Request $request, int $id)
    {
        $notification = Notification::find($id);
        if ($notification) {
            $notification->update(['is_read' => true]);
        }

        return response()->json(['message' => 'Notifikasi ditandai sebagai dibaca']);
    }

    /**
     * POST /api/notifications/read-all
     */
    public function markAllAsRead(Request $request)
    {
        $jwtUser = $request->attributes->get('user');

        if ($jwtUser->role === 'ADMIN') {
            Notification::where(function ($q) use ($jwtUser) {
                $q->whereNull('user_id')->orWhere('user_id', $jwtUser->id);
            })->update(['is_read' => true]);
        } else {
            Notification::where('user_id', $jwtUser->id)->update(['is_read' => true]);
        }

        return response()->json(['message' => 'Semua notifikasi ditandai sebagai dibaca']);
    }
}
