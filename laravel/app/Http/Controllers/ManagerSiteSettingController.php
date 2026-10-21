<?php

namespace App\Http\Controllers;

use App\Models\GymSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManagerSiteSettingController extends Controller
{
    public function edit(): View
    {
        return view('manager.settings.edit', ['settings' => GymSetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'gym_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'opening_hours' => ['nullable', 'string', 'max:255'],
            'introduction' => ['nullable', 'string', 'max:3000'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account' => ['nullable', 'string', 'max:80'],
            'bank_account_name' => ['nullable', 'string', 'max:160'],
            'faq_items' => ['nullable', 'array', 'max:4'],
            'faq_items.*' => ['array:question,answer'],
            'faq_items.*.question' => ['nullable', 'string', 'max:180', 'required_with:faq_items.*.answer'],
            'faq_items.*.answer' => ['nullable', 'string', 'max:800', 'required_with:faq_items.*.question'],
        ]);

        $validated['faq_items'] = collect($validated['faq_items'] ?? [])
            ->map(fn (array $item): array => [
                'question' => trim($item['question'] ?? ''),
                'answer' => trim($item['answer'] ?? ''),
            ])
            ->filter(fn (array $item): bool => $item['question'] !== '' && $item['answer'] !== '')
            ->values()
            ->all();

        GymSetting::current()->fill($validated)->save();

        return redirect()->route('manager.settings.edit')->with('success', 'Đã lưu cài đặt phòng tập.');
    }
}
