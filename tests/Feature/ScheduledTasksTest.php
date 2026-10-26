<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * A guard on the hosting constraint, not on the code.
 *
 * THE cPanel CRON RUNS EVERY FIVE MINUTES — `0,5,10,15,20,25,30,35,40,45,50,55 * * * *`
 * (verified in cPanel → Cron Jobs). Laravel's scheduler only runs a task when the current
 * minute MATCHES its expression, and there is no catch-up: a task scheduled for 23:32 is
 * never run, and it fails silently — no error, no log line, nothing in the panel. The first
 * symptom is a feature that quietly does not happen.
 *
 * So every scheduled minute must land on a five-minute boundary. This test makes a new task
 * fail CI on the pull request instead of disappearing in production months later.
 *
 * If the host's cron is ever changed to `* * * * *`, this test can be deleted — but the
 * alignment it enforces costs nothing, so keeping it is the safer default.
 */
class ScheduledTasksTest extends TestCase
{
    public function test_every_scheduled_task_lands_on_a_five_minute_boundary(): void
    {
        $schedule = app(Schedule::class);
        $offenders = [];

        foreach ($schedule->events() as $event) {
            $minute = explode(' ', trim((string) $event->expression))[0] ?? '';

            if (! $this->landsOnFiveMinuteBoundary($minute)) {
                $offenders[] = sprintf(
                    'minute "%s" in "%s" (%s)',
                    $minute,
                    $event->expression,
                    $this->commandOf($event),
                );
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['The cPanel cron runs every 5 minutes, so these tasks would never run:'],
            $offenders,
            ['Move the time to the nearest :00/:05/:10/... and keep this test green.'],
        )));
    }

    /**
     * `*` (every minute), a step of five (`star-slash-5`), `0,5,10,...` and a bare multiple
     * of five are all fine.
     *
     * A step of two is not: it fires at :00, :02, :04 — and :02 is a minute the host never
     * wakes on, so half the runs never happen.
     */
    private function landsOnFiveMinuteBoundary(string $minute): bool
    {
        if ($minute === '*') {
            return true;
        }

        if (str_starts_with($minute, '*/')) {
            $step = (int) substr($minute, 2);

            // Firing minutes are 0, step, 2*step, ... so they are all multiples of five
            // exactly when the step is (five, ten, fifteen, ...). A step of one is every
            // minute, which is trivially fine; a step of two or seven is not.
            return $step === 1 || ($step > 0 && $step % 5 === 0);
        }

        foreach (explode(',', $minute) as $value) {
            if (! is_numeric($value) || (int) $value % 5 !== 0) {
                return false;
            }
        }

        return true;
    }

    private function commandOf(object $event): string
    {
        return is_string($event->command ?? null) && $event->command !== ''
            ? $event->command
            : (string) ($event->description ?? 'closure');
    }
}
