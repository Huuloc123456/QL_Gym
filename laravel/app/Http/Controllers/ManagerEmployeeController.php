<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Repositories\EmployeeAccountRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
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

    public function store(Request $request): RedirectResponse
    {
        $this->employees->create($request->validate($this->rules()));

        return redirect()->route('manager.employees.index')->with('success', 'Đã tạo tài khoản nhân viên.');
    }

    public function edit(User $employee): View
    {
        return view('manager.employees.form', [
            'employee' => $employee,
            'isEditing' => true,
        ]);
    }

    public function update(Request $request, User $employee): RedirectResponse
    {
        $validated = $request->validate($this->rules($employee));

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        $this->employees->update($employee, $validated);

        return redirect()->route('manager.employees.index')->with('success', 'Đã cập nhật tài khoản nhân viên.');
    }

    /** @return array<string, mixed> */
    private function rules(?User $employee = null): array
    {
        $uniqueUsername = Rule::unique('users', 'username');
        $uniqueEmail = Rule::unique('users', 'email');
        $uniquePhone = Rule::unique('users', 'phone');

        if ($employee !== null) {
            $uniqueUsername->ignore($employee->id);
            $uniqueEmail->ignore($employee->id);
            $uniquePhone->ignore($employee->id);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'alpha_dash', 'max:50', $uniqueUsername],
            'email' => ['required', 'email', 'max:255', $uniqueEmail],
            'phone' => ['required', 'string', 'max:20', $uniquePhone],
            'password' => [
                $employee === null ? 'required' : 'nullable',
                'confirmed',
                Password::min(8),
            ],
        ];
    }
}
