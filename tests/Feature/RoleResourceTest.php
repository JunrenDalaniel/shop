<?php

use App\Models\User;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Spatie\Permission\Models\Role;

test('super admin can access the roles resource', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('super_admin'));

    $this->actingAs($user)
        ->get(RoleResource::getUrl('index'))
        ->assertOk();
});

test('user without a role cannot access the roles resource', function () {
    $this->actingAs(User::factory()->create())
        ->get(RoleResource::getUrl('index'))
        ->assertForbidden();
});
