<?php

namespace App\Services\Rh;

use App\Models\User;
use App\Notifications\AttendanceNotification;
use Illuminate\Support\Collection;

class AttendanceNotifier
{
    /**
     * @param  Collection<int, User>  $users
     */
    public function notify(Collection $users, string $title, string $body, ?string $url = null): void
    {
        $users->unique('id')->each(function (User $user) use ($title, $body, $url) {
            $user->notify(new AttendanceNotification($title, $body, $url));
        });
    }

    /**
     * @return Collection<int, User>
     */
    public function accountingUsers(): Collection
    {
        return User::query()->where('role', 'accounting')->where('active', true)->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function owners(): Collection
    {
        return User::query()->where('role', 'super_admin')->where('active', true)->get();
    }
}
