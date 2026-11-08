<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::findOrCreate('super_admin'));
    $this->actingAs($this->admin);
});

test('super admin can list users', function () {
    $users = User::factory()->count(3)->create();

    Livewire::test(ListUsers::class)
        ->assertOk()
        ->assertCanSeeTableRecords($users);
});

test('user without permission cannot access the users resource', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/users')
        ->assertForbidden();
});

test('create form is prefilled with the default password', function () {
    Livewire::test(CreateUser::class)
        ->assertSchemaStateSet(['password' => UserForm::DEFAULT_PASSWORD]);
});

test('new user is created with the default credentials and a role', function () {
    $role = Role::findOrCreate('manager');

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'roles' => [$role->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'jane@example.com')->firstOrFail();

    expect(Hash::check(UserForm::DEFAULT_PASSWORD, $user->password))->toBeTrue()
        ->and($user->hasRole('manager'))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue();
});

test('creating a user validates required fields and unique email', function () {
    $existing = User::factory()->create();

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => '', 'email' => $existing->email])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'email' => 'unique']);
});

test('roles can be assigned and changed when editing a user', function () {
    $user = User::factory()->create();
    $editor = Role::findOrCreate('editor');
    $manager = Role::findOrCreate('manager');
    $user->assignRole($editor);

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->fillForm(['roles' => [$manager->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->getRoleNames()->all())->toBe(['manager']);
});

test('editing a user updates details and keeps the password when left blank', function () {
    $user = User::factory()->create();
    $originalHash = $user->password;

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->fillForm(['name' => 'Renamed', 'password' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->name)->toBe('Renamed')
        ->and($user->password)->toBe($originalHash);
});

test('editing a user can set a new password', function () {
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->fillForm(['password' => 'new-secret-123'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('new-secret-123', $user->refresh()->password))->toBeTrue();
});

test('a user can be deleted', function () {
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->callAction('delete');

    $this->assertModelMissing($user);
});

test('admins cannot delete their own account', function () {
    Livewire::test(EditUser::class, ['record' => $this->admin->getKey()])
        ->assertActionHidden('delete');

    expect(User::find($this->admin->getKey()))->not->toBeNull();
});

test('bulk delete skips the signed in admin', function () {
    $others = User::factory()->count(2)->create();

    Livewire::test(ListUsers::class)
        ->selectTableRecords($others->pluck('id')->push($this->admin->getKey())->all())
        ->callAction(TestAction::make('delete')->table()->bulk());

    expect(User::whereIn('id', $others->pluck('id'))->count())->toBe(0)
        ->and(User::find($this->admin->getKey()))->not->toBeNull();
});
