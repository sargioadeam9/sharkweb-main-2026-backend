<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * The audit trails that are NOT written by a model trait (§Maintenance).
 *
 * Login, logout and settings changes happen outside any model save, so each one is logged by
 * hand — and each of those hand-written calls was wrong: activitylog v4 has no `causer()`
 * method (it is `causedBy()`), and because every one of them fails soft, the mistake produced
 * no error at all. Three audit trails that were believed to exist had never written a row.
 *
 * These tests exist so that cannot happen again: they assert the ROW, not the call.
 */
class ActivityTrailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superadmin()->create([
            'email' => 'admin@thesharkweb.com',
        ]);
    }

    public function test_signing_in_writes_an_activity_row(): void
    {
        event(new Login('web', $this->admin, false));

        $row = Activity::query()->where('log_name', 'filament')->where('event', 'login')->first();

        $this->assertNotNull($row, 'A panel login must leave a trace.');
        $this->assertSame($this->admin->id, $row->causer_id);
    }

    public function test_signing_out_writes_an_activity_row(): void
    {
        event(new Logout('web', $this->admin));

        $row = Activity::query()->where('log_name', 'filament')->where('event', 'logout')->first();

        $this->assertNotNull($row, 'A panel logout must leave a trace.');
        $this->assertSame($this->admin->id, $row->causer_id);
    }

    /**
     * Settings have no model, so the page logs the change itself — and names the fields that
     * were touched, never their values (the page also edits API tokens).
     */
    public function test_saving_settings_writes_an_activity_row_with_the_field_names(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ManageSettings::class)
            ->call('save')
            ->assertHasNoFormErrors();

        $row = Activity::query()->where('log_name', 'settings')->where('event', 'settings_updated')->first();

        $this->assertNotNull($row, 'A settings change must leave a trace.');
        $this->assertSame($this->admin->id, $row->causer_id);

        $fields = $row->properties->get('fields', []);

        $this->assertContains('site_name', $fields);

        /*
         * NAMES, never VALUES. The page edits API tokens, so a log that recorded the
         * submitted state would put live credentials in the activity table — readable in the
         * panel. The whole property bag must therefore be the field list and nothing else.
         */
        $this->assertSame(['fields'], array_keys($row->properties->toArray()));

        foreach ($fields as $field) {
            $this->assertIsString($field, 'Every logged field must be a name, not a value.');
        }
    }
}
