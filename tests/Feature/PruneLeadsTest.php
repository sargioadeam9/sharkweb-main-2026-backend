<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PruneLeadsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAncientLead(array $attributes = []): Lead
    {
        $ancientDate = Carbon::now()->subDays(750);

        $lead = Lead::create(array_merge([
            'name' => 'Old Prospect',
            'email' => 'old@example.com',
            'status' => LeadStatus::Contacted,
        ], $attributes));

        // Use direct DB update so timestamps are precisely ancient
        Lead::where('id', $lead->id)->update([
            'created_at' => $ancientDate,
            'updated_at' => $ancientDate,
        ]);

        return $lead->fresh();
    }

    public function test_dry_run_reports_candidates_without_deleting(): void
    {
        $ancient = $this->makeAncientLead(['email' => 'dryrun@example.com']);

        $this->artisan('leads:prune', ['--dry-run' => true])
            ->expectsOutputToContain('would prune: #' . $ancient->id)
            ->expectsOutputToContain('Would prune 1 enquiry records')
            ->assertExitCode(0);

        $this->assertDatabaseHas('leads', ['id' => $ancient->id]);
    }

    public function test_it_prunes_leads_older_than_retention_window(): void
    {
        $ancient = $this->makeAncientLead(['email' => 'pruneme@example.com']);
        $recent = Lead::create([
            'name' => 'Fresh Prospect',
            'email' => 'fresh@example.com',
            'status' => LeadStatus::New,
        ]);

        $this->artisan('leads:prune')
            ->expectsOutputToContain('Pruned 1 enquiry records')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('leads', ['id' => $ancient->id]);
        $this->assertDatabaseHas('leads', ['id' => $recent->id]);
    }

    public function test_it_does_not_prune_leads_with_recent_interaction(): void
    {
        $lead = $this->makeAncientLead(['email' => 'nudged@example.com']);

        // Update booking reminder sent recently
        $lead->booking_reminder_sent_at = Carbon::now()->subDays(10);
        $lead->save();

        $this->artisan('leads:prune')
            ->expectsOutputToContain('No enquiries are due for retention pruning.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('leads', ['id' => $lead->id]);
    }

    public function test_it_skips_converted_clients(): void
    {
        $clientLead = $this->makeAncientLead([
            'name' => 'Acme Corp',
            'email' => 'ceo@acme.com',
            'internal_notes' => 'Signed enterprise client contract in 2024.',
        ]);

        $this->artisan('leads:prune')
            ->expectsOutputToContain('skipping converted client: #' . $clientLead->id)
            ->assertExitCode(0);

        $this->assertDatabaseHas('leads', ['id' => $clientLead->id]);
    }

    public function test_it_refuses_to_delete_when_candidates_exceed_limit_in_live_mode(): void
    {
        $this->makeAncientLead(['email' => 'first@example.com']);
        $this->makeAncientLead(['email' => 'second@example.com']);

        $this->artisan('leads:prune --limit=1')
            ->expectsOutputToContain('Safety abort: 2 candidate enquiries exceed the single-run limit of 1')
            ->assertExitCode(1);

        $this->assertSame(2, Lead::count());
    }
}