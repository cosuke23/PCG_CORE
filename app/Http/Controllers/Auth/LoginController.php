<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Show the login form
     */
    public function showLoginForm()
    {
        return view('pages.login');
    }

    /**
     * Handle user login and create session
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials)) {

            $user = Auth::user();

            // Check if approved
            // Only approved accounts may log in. Statuses are stored with mixed
            // case ("Approved", "Pending", "Rejected"); compare case-insensitively
            // so that no unexpected value falls through to a successful login.
            $status = strtolower(trim((string) $user->status));

            if ($status !== 'approved') {

                Auth::logout();

                $message = match ($status) {
                    'pending'  => 'Your account is waiting for admin approval.',
                    'rejected' => 'Your account has been rejected by admin.',
                    default    => 'Your account is not active. Please contact the administrator.',
                };

                return back()
                    ->withErrors(['email' => $message])
                    ->withInput($request->only('email'));
            }

            // optional second step chosen by the member (/security): emailed code
            if (\App\Support\MemberLoginCode::wanted($user)) {
                Auth::logout();
                $request->session()->regenerate();
                if (!\App\Support\MemberLoginCode::issue($user)) {
                    return back()->withErrors(['email' => 'The sign-in code could not be emailed. Try again later.'])->withInput($request->only('email'));
                }
                session(['member_2fa_user_id' => $user->id]);
                return redirect()->route('login.code');
            }

            return $this->completeLogin($request, $user);
        }

        return back()->withErrors([
            'email' => 'Invalid credentials.'
        ])->withInput($request->only('email'));
    }

    private function completeLogin(Request $request, $user)
    {
        $request->session()->regenerate();

        session([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'user_role' => $user->role ?? 'user',
        ]);
        \App\Support\MemberDevices::start($request, (int) $user->id);

        return redirect()->intended('/dashboard')
            ->with('success', 'Welcome back!');
    }

    private function pendingUser(): ?\App\Models\User
    {
        $id = session('member_2fa_user_id');
        $user = $id ? \App\Models\User::find($id) : null;
        return $user && strtolower(trim((string) $user->status)) === 'approved' ? $user : null;
    }

    public function codeForm()
    {
        $user = $this->pendingUser();
        if (!$user) {
            return redirect('/login');
        }
        return view('auth.member-code', ['maskedEmail' => \App\Support\EmailLoginCode::masked((string) $user->email), 'minutes' => \App\Support\MemberLoginCode::minutes()]);
    }

    public function codeVerify(Request $request)
    {
        $request->validate(['code' => 'required|string|max:32']);
        $user = $this->pendingUser();
        if (!$user) {
            return redirect('/login')->withErrors(['email' => 'Sign in again.']);
        }
        if ($reason = \App\Support\MemberLoginCode::check($user, (string) $request->input('code'))) {
            \Illuminate\Support\Facades\Log::warning('Failed member sign-in code', ['user_id' => $user->id, 'ip' => $request->ip()]);
            return back()->withErrors(['code' => $reason]);
        }
        session()->forget('member_2fa_user_id');
        Auth::login($user);
        return $this->completeLogin($request, $user);
    }

    public function codeResend(Request $request)
    {
        $user = $this->pendingUser();
        if (!$user) {
            return redirect('/login');
        }
        if (($wait = \App\Support\MemberLoginCode::secondsUntilResend()) > 0) {
            return back()->with('error', "Wait {$wait} seconds before asking for a new code.");
        }
        return \App\Support\MemberLoginCode::issue($user)
            ? back()->with('success', 'A new code is on its way.')
            : back()->with('error', 'The sign-in code could not be emailed. Try again later.');
    }

    /**
     * Logout user and destroy session
     */
    public function logout(Request $request)
    {
        \App\Support\MemberDevices::end($request);
        Auth::logout();

        // Invalidate session & regenerate token for security
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('success', 'You have been logged out.');
    }
}
