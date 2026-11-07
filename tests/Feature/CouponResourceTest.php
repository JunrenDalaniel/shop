<?php

use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Models\Coupon;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('coupons are listed and searchable by code', function () {
    $match = Coupon::factory()->create(['code' => 'SAVE50']);
    $other = Coupon::factory()->create(['code' => 'OTHER1']);

    Livewire::test(ListCoupons::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$match, $other])
        ->searchTable('SAVE50')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$other]);
});

test('coupons can be filtered by active status', function () {
    $active = Coupon::factory()->create();
    $inactive = Coupon::factory()->inactive()->create();

    Livewire::test(ListCoupons::class)
        ->filterTable('is_active', true)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$inactive]);
});

test('a coupon can be created', function () {
    Livewire::test(CreateCoupon::class)
        ->fillForm([
            'code' => 'WELCOME100',
            'discount' => 100,
            'expires_at' => now()->addMonth()->toDateString(),
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Coupon::where('code', 'WELCOME100')->first())
        ->discount->toBe('100.00')
        ->is_active->toBeTrue();
});

test('coupon validation requires a unique code and a discount', function () {
    Coupon::factory()->create(['code' => 'TAKEN']);

    Livewire::test(CreateCoupon::class)
        ->fillForm(['code' => 'TAKEN', 'discount' => null])
        ->call('create')
        ->assertHasFormErrors(['code' => 'unique', 'discount' => 'required']);
});

test('a coupon can be edited without changing its code', function () {
    $coupon = Coupon::factory()->create(['code' => 'KEEP10']);

    Livewire::test(EditCoupon::class, ['record' => $coupon->getKey()])
        ->fillForm(['discount' => 250, 'is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($coupon->fresh())
        ->code->toBe('KEEP10')
        ->discount->toBe('250.00')
        ->is_active->toBeFalse();
});
