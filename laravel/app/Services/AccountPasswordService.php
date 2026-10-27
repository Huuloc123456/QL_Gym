<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AccountPasswordService
{
    public function __construct(private OriginalGymSql $sql) {}

    public function change(User $user, string $password, string $currentSessionId): void
    {
        $this->savePassword($user, $password, $currentSessionId);
    }

    public function reset(User $user, string $password): void
    {
        $this->savePassword($user, $password, null);
    }

    private function savePassword(User $user, string $password, ?string $currentSessionId): void
    {
        $this->sql->transaction(function () use ($user, $password, $currentSessionId): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $lockedUser->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            if (Schema::hasTable('sessions')) {
                $sessions = DB::table('sessions')->where('user_id', $lockedUser->id);

                if ($currentSessionId !== null) {
                    $sessions->where('id', '!=', $currentSessionId);
                }

                $sessions->delete();
            }

            $this->sql->audit('Đổi mật khẩu', 'users:'.$lockedUser->id);
        });
    }
}
