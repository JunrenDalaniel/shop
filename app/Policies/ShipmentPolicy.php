<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Shipment;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ShipmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Shipment');
    }

    public function view(AuthUser $authUser, Shipment $shipment): bool
    {
        return $authUser->can('View:Shipment');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Shipment');
    }

    public function update(AuthUser $authUser, Shipment $shipment): bool
    {
        return $authUser->can('Update:Shipment');
    }

    public function delete(AuthUser $authUser, Shipment $shipment): bool
    {
        return $authUser->can('Delete:Shipment');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Shipment');
    }

    public function restore(AuthUser $authUser, Shipment $shipment): bool
    {
        return $authUser->can('Restore:Shipment');
    }

    public function forceDelete(AuthUser $authUser, Shipment $shipment): bool
    {
        return $authUser->can('ForceDelete:Shipment');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Shipment');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Shipment');
    }

    public function replicate(AuthUser $authUser, Shipment $shipment): bool
    {
        return $authUser->can('Replicate:Shipment');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Shipment');
    }
}
