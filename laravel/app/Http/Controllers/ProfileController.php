<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('profile.show', ['customer' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone,'.$request->user()->id],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female,other'],
            'username' => ['prohibited'],
            'email' => ['prohibited'],
        ]);

        $request->user()->update(collect($validated)->only(['name', 'phone', 'birth_date', 'gender'])->all());

        return redirect()->route('my.profile.show')->with('success', 'Thông tin cá nhân đã được cập nhật.');
    }
}
