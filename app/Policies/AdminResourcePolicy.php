<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** All current back-office resources are staff-only, including direct HTTP mutations. */
class AdminResourcePolicy
{
    public function viewAny(User $user): bool { return $user->is_admin; }
    public function view(User $user, Model $record): bool { return $user->is_admin; }
    public function create(User $user): bool { return $user->is_admin; }
    public function update(User $user, Model $record): bool { return $user->is_admin; }
    public function delete(User $user, Model $record): bool { return $user->is_admin; }
    public function deleteAny(User $user): bool { return $user->is_admin; }
    public function restore(User $user, Model $record): bool { return $user->is_admin; }
    public function restoreAny(User $user): bool { return $user->is_admin; }
    public function forceDelete(User $user, Model $record): bool { return $user->is_admin; }
    public function forceDeleteAny(User $user): bool { return $user->is_admin; }
    public function replicate(User $user, Model $record): bool { return $user->is_admin; }
    public function reorder(User $user): bool { return $user->is_admin; }
}
