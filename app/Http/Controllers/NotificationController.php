<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\MemberNotifications;
use Illuminate\Http\Request;

/**
 * Member notifications (App\Support\MemberNotifications): the list page (opening
 * it marks everything read) and the session-less poll the Android app uses.
 */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $items = MemberNotifications::latest((int) $user->id);
        $unread = $items->whereNull('read_at')->count();
        MemberNotifications::markAllRead((int) $user->id);
        return view('notifications.index', ['items' => $items, 'unread' => $unread]);
    }

    /**
     * GET /api/app/notifications?u=<user id>&t=<token>&after=<last id seen>
     * The newest 10 notifications after `after` (titles and portal paths only).
     */
    public function poll(Request $request)
    {
        $data = $request->validate([
            'u' => 'required|integer|min:1',
            't' => 'required|string|size:64',
            'after' => 'nullable|integer|min:0',
        ]);
        $user = User::query()->whereKey($data['u'])->first(['id', 'password', 'status']);
        if (!$user || !MemberNotifications::checkToken($user, $data['t']) || strtolower(trim((string) $user->status)) !== 'approved') {
            return response()->json(['error' => 'unauthorized'], 401);
        }
        $after = (int) ($data['after'] ?? 0);
        $items = MemberNotifications::ready()
            ? \Illuminate\Support\Facades\DB::table('member_notifications')->where('user_id', $user->id)->where('id', '>', $after)
                ->orderByDesc('id')->limit(10)->get(['id', 'title', 'body', 'url'])
            : collect();
        return response()->json([
            'unread' => MemberNotifications::unread((int) $user->id),
            'items' => $items->values(),
        ])->header('Cache-Control', 'no-store');
    }
}
