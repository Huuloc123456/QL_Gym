<?php

namespace App\Repositories;

use App\Models\TrialRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class TrialRequestRepository
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): TrialRequest
    {
        return TrialRequest::query()->create($attributes);
    }

    public function paginateForStaff(?string $status, ?string $search): LengthAwarePaginator
    {
        $query = TrialRequest::query()->with([
            'handler',
            'entitlement' => fn ($query) => $query->with(['revokedBy'])->withCount('checkIns'),
        ]);

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($search !== null && $search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        return $query
            ->orderByRaw("CASE WHEN status IN ('received', 'pending') THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(20)
            ->withQueryString();
    }

    public function markContacted(TrialRequest $trialRequest, User $staff): void
    {
        $trialRequest->update([
            'status' => $trialRequest->status === 'received' ? 'contacted' : $trialRequest->status,
            'handled_by' => $staff->id,
            'handled_at' => now(config('app.timezone')),
        ]);
    }
}
