<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardAuthController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->session()->get('ops_dashboard_unlocked') === true) {
            return redirect()->intended('/');
        }

        return view('dashboard-login', [
            'error' => $request->session()->get('dashboard_login_error'),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $configuredPassword = (string) config('services.ops_dashboard.password', 'Savera@2026');
        if (! hash_equals($configuredPassword, (string) $request->input('password'))) {
            return back()
                ->withInput()
                ->with('dashboard_login_error', 'Password dashboard tidak sesuai.');
        }

        $request->session()->put('ops_dashboard_unlocked', true);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('ops_dashboard_unlocked');

        return redirect()->route('dashboard-login');
    }
}
