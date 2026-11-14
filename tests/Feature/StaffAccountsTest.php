<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The two classes of operator (§Staff accounts).
 *
 * The safety model: staff can do the day job everywhere in the panel, but account
 * management and the Maintenance pages are superadmin-only — and the gate lives in
 * `canAccess()` on the resource/pages, so a guessed URL gets a 403, not a form.
 */
class StaffAccountsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->superadmin()->create([
            'email' => 'admin@thesharkweb.com',
        ]);
    }

    public function test_a_superadmin_can_create_a_staff_account(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Rakib Staff',
                'email' => 'staff@thesharkweb.com',
                'password' => 'super-secret-9',
                'role' => 'staff',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $staff = User::query()->where('email', 'staff@thesharkweb.com')->first();

        $this->assertNotNull($staff, 'The staff account must be created.');
        $this->assertSame('staff', $staff->role->value);
        $this->assertNotSame('super-secret-9', $staff->password, 'The password must be stored hashed.');
        $this->assertTrue(Hash::check('super-secret-9', $staff->password));
    }

    public function test_a_staff_account_can_sign_in_but_sees_no_user_management(): void
    {
        $staff = User::factory()->create(['email' => 'staff@example.test']);

        $this->actingAs($staff)
            ->get('/theshark-hq')
            ->assertOk();

        $this->actingAs($staff)
            ->get('/theshark-hq/users')
            ->assertForbidden();

        $this->actingAs($staff)
            ->get('/theshark-hq/users/create')
            ->assertForbidden();
    }

    public function test_staff_cannot_reach_the_maintenance_pages(): void
    {
        $staff = User::factory()->create(['email' => 'worker@example.test']);

        foreach (['/theshark-hq/manage-maintenance', '/theshark-hq/manage-cleaner'] as $url) {
            $this->actingAs($staff)
                ->get($url)
                ->assertForbidden();
        }
    }

    /** The pre-role env var keeps working: an email on the list still grants the powers. */
    public function test_the_email_list_still_grants_superadmin_powers(): void
    {
        config(['sharkweb.maintenance.super_admins' => 'legacy-owner@example.test']);

        $legacy = User::factory()->create(['email' => 'legacy-owner@example.test']);

        $this->assertTrue($legacy->isSuperAdmin(), 'An email on the list must keep its powers.');

        $this->actingAs($legacy)
            ->get('/theshark-hq/users')
            ->assertOk();
    }

    public function test_a_role_superadmin_needs_no_email_list_entry(): void
    {
        config(['sharkweb.maintenance.super_admins' => '']);

        $this->assertTrue($this->owner->isSuperAdmin());

        $this->actingAs($this->owner)
            ->get('/theshark-hq/manage-cleaner')
            ->assertOk();
    }

    public function test_you_cannot_delete_your_own_account(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(EditUser::class, ['record' => $this->owner->getKey()])
            ->assertActionHidden('delete');
    }

    public function test_the_users_pages_render_for_a_superadmin(): void
    {
        User::factory()->create(['name' => 'Rakib Staff']);

        $this->actingAs($this->owner)
            ->get('/theshark-hq/users')
            ->assertOk()
            ->assertSee('Rakib Staff');
    }

    public function test_passwords_are_stored_hashed_not_plaintext(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Hash Probe',
                'email' => 'hashed@thesharkweb.com',
                'password' => 'another-secret-3',
                'role' => 'staff',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $staff = User::query()->where('email', 'hashed@thesharkweb.com')->first();

        $this->assertNotNull($staff);
        $this->assertNotSame('another-secret-3', $staff->password);
        $this->assertTrue(Hash::check('another-secret-3', $staff->password));
    }
}
