<?php

namespace App\Http\Controllers;

use App\Models\HeroBanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ManagerHeroBannerController extends Controller
{
    public function index(): View
    {
        return view('manager.banners.index', ['banners' => HeroBanner::query()->orderBy('position')->orderBy('id')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'alt_text' => ['required', 'string', 'max:255'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ]);

        $imagePath = $request->file('image')->store('hero-banners', 'public');
        if (! $imagePath) {
            return back()->withInput()->with('error', 'Không thể lưu ảnh banner. Hãy thử lại.');
        }

        HeroBanner::query()->create([
            'title' => $data['title'],
            'alt_text' => $data['alt_text'],
            'image_path' => $imagePath,
            'position' => (int) HeroBanner::query()->max('position') + 1,
            'is_active' => true,
        ]);

        return redirect()->route('manager.banners.index')->with('success', 'Đã thêm banner.');
    }

    public function update(Request $request, HeroBanner $banner): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'alt_text' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ]);

        $previousImage = $banner->image_path;
        $newImage = null;
        if ($request->hasFile('image')) {
            $newImage = $request->file('image')->store('hero-banners', 'public');
            if (! $newImage) {
                return back()->withInput()->with('error', 'Không thể lưu ảnh banner. Ảnh hiện tại vẫn được giữ.');
            }
        }
        $banner->fill(['title' => $data['title'], 'alt_text' => $data['alt_text']]);
        if ($newImage !== null) {
            $banner->image_path = $newImage;
        }
        $banner->save();
        if ($newImage !== null) {
            Storage::disk('public')->delete($previousImage);
        }

        return redirect()->route('manager.banners.index')->with('success', 'Đã cập nhật banner.');
    }

    public function toggle(HeroBanner $banner): RedirectResponse
    {
        $banner->update(['is_active' => ! $banner->is_active]);

        return back()->with('success', $banner->is_active ? 'Đã bật banner.' : 'Đã tạm ẩn banner.');
    }

    public function destroy(HeroBanner $banner): RedirectResponse
    {
        $imagePath = $banner->image_path;
        $banner->delete();
        Storage::disk('public')->delete($imagePath);

        return redirect()->route('manager.banners.index')->with('success', 'Đã xóa banner.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate(['positions' => ['required', 'array'], 'positions.*' => ['required', 'integer', 'min:0']]);
        $bannerIds = HeroBanner::query()
            ->whereKey(array_keys($data['positions']))
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
        if (count($bannerIds) !== count($data['positions'])) {
            return back()->withErrors(['positions' => 'Một banner trong danh sách không còn tồn tại. Tải lại trang rồi thử lại.']);
        }

        DB::transaction(function () use ($data): void {
            foreach ($data['positions'] as $id => $position) {
                HeroBanner::query()->whereKey($id)->update(['position' => $position]);
            }
        });

        return back()->with('success', 'Đã cập nhật thứ tự banner.');
    }
}
