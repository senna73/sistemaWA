<?php

namespace App\Services\Auth;

use App\Models\Collaborator;
use App\Models\User;
use App\Support\AccessControl;
use App\Support\Document;

class CollaboratorAccessService
{
    public function findCollaboratorByCpf(string $identifier): ?Collaborator
    {
        $digits = Document::digits($identifier);

        if (strlen($digits) !== 11) {
            return null;
        }

        return Collaborator::query()->where('document', $digits)->first();
    }

    public function findUserByIdentifier(string $identifier): ?User
    {
        if (Document::looksLikeEmail($identifier)) {
            return User::query()->where('email', strtolower(trim($identifier)))->first();
        }

        $collaborator = $this->findCollaboratorByCpf($identifier);

        return $collaborator?->user;
    }

    public function canStartFirstAccess(Collaborator $collaborator): bool
    {
        if ($collaborator->user?->active) {
            return false;
        }

        if ($collaborator->active) {
            return true;
        }

        return $collaborator->offboardingProcesses()->exists();
    }

    public function createFromFirstAccess(Collaborator $collaborator, string $email, string $password): User
    {
        User::releaseInactiveConflicts($email, $collaborator->id);

        $user = User::create([
            'name' => $collaborator->name,
            'email' => strtolower(trim($email)),
            'password' => $password,
            'collaborator_id' => $collaborator->id,
            'role' => 'employee',
            'email_verified_at' => now(),
            'active' => true,
        ]);

        $this->assignCollaboratorAccess($user);

        return $user;
    }

    public function assignCollaboratorAccess(User $user): void
    {
        AccessControl::applyToUser($user, 'employee');
    }
}
