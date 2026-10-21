<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeAccountRequest;
use App\Http\Requests\UpdateEmployeeAccountRequest;
use App\Http\Requests\UpdateEmployeePermissionsRequest;
use App\Models\User;
use App\Repositories\EmployeeAccountRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ManagerEmployeeController extends Controller
{
    public function __construct(private EmployeeAccountRepository $employees) {}

    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        return view('manager.employees.index', [
            'employees' => $this->employees->paginate($filters['q'] ?? null),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('manager.employees.form', [
            'employee' => new User,
            'isEditing' => false,
        ]);
    }

    public function store(StoreEmployeeAccountRequest $request): RedirectResponse
    {
        $employee = $this->employees->create($request->validated());

        return redirect()->route('manager.employees.permissions.edit', $employee)
            ->with('success', 'Đã tạo tài khoản. Hãy cấp quyền nghiệp vụ trước khi nhân viên đăng nhập.');
    }

    public function edit(User $employee): View
    {
        return view('manager.employees.form', [
            'employee' => $employee,
            'isEditing' => true,
        ]);
    }

    public function update(UpdateEmployeeAccountRequest $request, User $employee): RedirectResponse
    {
        $validated = $request->validated();

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        $this->employees->update($employee, $validated);

        return redirect()->route('manager.employees.index')->with('success', 'Đã cập nhật tài khoản nhân viên.');
    }

    public function editPermissions(User $employee): View
    {
        return view('manager.employees.permissions', compact('employee'));
    }

    public function updatePermissions(UpdateEmployeePermissionsRequest $request, User $employee): RedirectResponse
    {
        $employee->update($request->validated());

        return redirect()->route('manager.employees.index')
            ->with('success', 'Đã cập nhật quyền nghiệp vụ cho nhân viên.');
    }

    public function toggleActive(Request $request, User $employee): RedirectResponse
    {
        $isActivating = ! $employee->is_active;
        $employee->forceFill([
            'is_active' => $isActivating,
            'locked_at' => $isActivating ? null : Carbon::now(),
            'locked_by' => $isActivating ? null : $request->user()->id,
        ])->save();

        return redirect()->route('manager.employees.index')
            ->with('success', $isActivating ? 'Đã mở khóa tài khoản nhân viên.' : 'Đã khóa tài khoản nhân viên.');
    }
}
