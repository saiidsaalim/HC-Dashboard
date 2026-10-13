<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    /**
     * Display the mandatory first-login password form.
     */
    public function editFirstLogin(Request $request): View|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return to_route('portal');
        }

        return view('auth.first-password-change');
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed', 'different:current_password'],
        ]);

        $mustChangePassword = $request->user()->must_change_password;

        DB::transaction(function () use ($request, $validated): void {
            $request->user()->forceFill([
                'password' => Hash::make($validated['password']),
                'must_change_password' => false,
            ])->save();
        });

        $request->session()->regenerate();

        if ($mustChangePassword) {
            return to_route('portal')->with('status', 'Password berhasil diperbarui.');
        }

        return back()->with('status', 'password-updated');
    }
}
