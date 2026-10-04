<?php

namespace App\Http\Controllers;

use App\Models\GymPackage;
use App\Repositories\GymPackageRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManagerGymPackageController extends Controller
{
    public function __construct(private GymPackageRepository $packages) {}

    public function index(): View
    {
        return view('manager.packages.index', ['packages' => $this->packages->forManagement()]);
    }

    public function create(): View
    {
        return view('manager.packages.form', [
            'package' => new GymPackage,
            'isEditing' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->packages->create($request->validate($this->rules()));

        return redirect()->route('manager.packages.index')->with('success', 'Đã tạo gói tập và mở bán.');
    }

    public function edit(GymPackage $package): View
    {
        return view('manager.packages.form', [
            'package' => $package,
            'isEditing' => true,
        ]);
    }

    public function update(Request $request, GymPackage $package): RedirectResponse
    {
        $this->packages->update($package, $request->validate($this->rules()));

        return redirect()->route('manager.packages.index')->with('success', 'Đã cập nhật thông tin gói tập.');
    }

    public function toggleActive(GymPackage $package): RedirectResponse
    {
        $this->packages->toggleActive($package);

        return redirect()->route('manager.packages.index')->with(
            'success',
            $package->fresh()->is_active ? 'Đã mở bán gói tập.' : 'Đã ngừng bán gói tập; lịch sử đơn và hội viên vẫn được giữ.'
        );
    }

    /** @return array<string, list<string>> */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:65535'],
        ];
    }
}
