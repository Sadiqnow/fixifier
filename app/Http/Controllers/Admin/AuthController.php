<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function create(Request $request)
    {
        if ($request->user('web')?->role?->value === 'admin' && $request->user('web')->is_active) {
            return redirect()->route('admin.overview');
        }

        return view('auth.login');
    }

    public function store(Request $request)
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::guard('web')->attempt($data + ['role' => 'admin', 'is_active' => true], false)) {
            throw ValidationException::withMessages(['email' => 'The administrator credentials are invalid.']);
        }
        $request->session()->regenerate();

        return redirect()->route('admin.overview');
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
