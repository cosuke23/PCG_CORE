<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Public contact form (stored in contact_messages) and the admin inbox.
 */
class ContactController extends Controller
{
    public function show()
    {
        return view('pages.contact');
    }

    public function store(Request $request)
    {
        // Hidden field that people never fill in; bots usually do.
        if (filled($request->input('website'))) {
            return redirect()->route('contact')->with('success', 'Thank you. Your message has been sent.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'nullable|string|max:150',
            'message' => 'required|string|min:10|max:5000',
        ]);

        DB::table('contact_messages')->insert(array_merge($data, [
            'status' => 'new',
            'ip' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        Log::info('Contact message received', ['ip' => $request->ip()]);

        return redirect()->route('contact')->with('success', 'Thank you. Your message has been sent to the MEPCOM HRMIS administrators.');
    }

    // ---------------- admin inbox ----------------

    public function index(Request $request)
    {
        $status = $request->query('status', 'new');
        abort_unless(in_array($status, ['new', 'read', 'archived', 'all'], true), 404);

        $query = DB::table('contact_messages')->orderByDesc('id');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return view('admin.messages.index', [
            'messages' => $query->paginate(25)->withQueryString(),
            'status' => $status,
            'counts' => DB::table('contact_messages')->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function updateStatus(Request $request, int $id)
    {
        $request->validate(['status' => 'required|in:new,read,archived']);

        $updated = DB::table('contact_messages')->where('id', $id)->update([
            'status' => $request->status,
            'read_at' => $request->status === 'new' ? null : now(),
            'updated_at' => now(),
        ]);
        abort_unless($updated, 404);

        return back()->with('success', 'Message marked as ' . $request->status . '.');
    }
}
