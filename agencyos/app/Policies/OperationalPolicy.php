<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\TenantModel;
use App\Models\User;
use App\Models\Website;
use App\Tenancy\TenantContext;

class OperationalPolicy
{
    public function view(User $user, TenantModel $record): bool
    {
        if ((int) $record->agency_id !== app(TenantContext::class)->id()) {
            return false;
        }
        $module = $record->getTable();
        if ($module !== 'clients' && ($subscription = $record->client?->subscription) && ! $subscription->operational() && $subscription->expiry_mode === 'full_suspension') {
            return false;
        }
        if (! $user->hasPermission($module.'.view')) {
            return false;
        }
        if ($user->hasPermission('clients.view')) {
            return true;
        }
        if ($record instanceof Project) {
            return $record->users()->where('users.id', $user->id)->exists();
        }
        if ($record instanceof Website) {
            return $record->projects()->whereHas('users', fn ($q) => $q->where('users.id', $user->id))->exists();
        }

        return false;
    }

    public function update(User $user, TenantModel $record): bool
    {
        if ($record->getTable() !== 'clients' && ($subscription = $record->client?->subscription) && ! $subscription->operational()) {
            return false;
        }

        return $this->view($user, $record) && $user->hasPermission($record->getTable().'.edit');
    }

    public function delete(User $user, TenantModel $record): bool
    {
        return $this->view($user, $record) && $user->hasPermission($record->getTable().'.delete');
    }
}
