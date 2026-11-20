<?php

namespace App\Http\Controllers;

use App\Support\MemberDevices;
use App\Support\MemberLoginCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The member's "Sign-in and devices" page (owner request 2026-11-20): turn the
 * emailed sign-in code on or off, see the devices signed in, sign one out or
 * every other one. Every change is audited.
 */
class SecurityController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        return view('applicant.security', [
            'codeAvailable' => MemberLoginCode::available(),
            'codeOn' => MemberLoginCode::wanted($user),
            'devices' => MemberDevices::active((int) $user->id),
            'thisDevice' => (int) $request->session()->get(MemberDevices::SESSION_KEY),
            'maskedEmail' => \App\Support\EmailLoginCode::masked((string) $user->email),
        ]);
    }

    public function loginCode(Request $request)
    {
        $data = $request->validate(['enabled' => 'required|in:0,1']);
        abort_unless(MemberLoginCode::available(), 409, 'Not available yet (database update pending).');
        $user = $request->user();
        if ($data['enabled'] === '1' && !filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return back()->with('error', 'Your account has no valid email address for the code. Ask HR to correct it first.');
        }
        DB::table('users')->where('id', $user->id)->update(['login_code_enabled' => $data['enabled'] === '1']);
        Log::channel('audit')->info('member.login_code_' . ($data['enabled'] === '1' ? 'on' : 'off'), ['user_id' => $user->id, 'ip' => $request->ip()]);
        return back()->with('success', $data['enabled'] === '1'
            ? 'From your next sign-in we will email you a code after your password.'
            : 'The emailed sign-in code is off. Your password alone signs you in.');
    }

    public function signOutOthers(Request $request)
    {
        $user = $request->user();
        $n = MemberDevices::signOutOthers((int) $user->id, (int) $request->session()->get(MemberDevices::SESSION_KEY));
        Log::channel('audit')->info('member.devices_signed_out', ['user_id' => $user->id, 'count' => $n, 'ip' => $request->ip()]);
        return back()->with('success', $n === 0 ? 'No other device was signed in.' : ($n === 1 ? '1 other device was signed out.' : "$n other devices were signed out."));
    }

    public function signOutDevice(Request $request, int $id)
    {
        $user = $request->user();
        if ($id === (int) $request->session()->get(MemberDevices::SESSION_KEY)) {
            return back()->with('error', 'To sign out this device, use Sign out at the top of the page.');
        }
        abort_unless(MemberDevices::signOutOne((int) $user->id, $id), 404);
        Log::channel('audit')->info('member.device_signed_out', ['user_id' => $user->id, 'device_id' => $id, 'ip' => $request->ip()]);
        return back()->with('success', 'That device was signed out.');
    }
}
