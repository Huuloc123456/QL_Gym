<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class EmployeeAccountRepository
{
    public function paginate(?string $search = null): LengthAwarePaginator
    {
        return User::query()
            ->where('role', 'employee')
            ->when($search !== null && $search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('username', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();
    }

    /** @param array{name: string, username: string, email: string, phone: string, password: string} $attributes */
    public function create(array $attributes): User
    {
        return User::query()->create([...$attributes, 'role' => 'employee']);
    }

    /** @param array{name: string, username: string, email: string, phone: string, password?: string|null} $attributes */
    public function update(User $employee, array $attributes): void
    {
        $employee->update($attributes);
    }
}
