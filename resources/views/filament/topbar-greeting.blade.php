{{--
    Topbar greeting: who is signed in, what day it is, and a live clock.

    WHY THIS SHIPS ITS OWN <style> BLOCK INSTEAD OF TAILWIND CLASSES.
    Filament's panel stylesheet is pre-compiled and PURGED — it only contains the utility
    classes Filament's own views happened to use at build time. `sm:flex`, `leading-tight`
    and `tabular-nums` are all absent from it, so writing them here would silently render an
    unstyled block that looks like a CSS bug and is not one. A dozen scoped rules cost less
    than finding that out the hard way.

    WHY THE TIMEZONE IS CONFIGURED RATHER THAN `now()`.
    `config('app.timezone')` is UTC, deliberately — timestamps are stored in UTC. But a clock
    the owner reads should show the time he is working in, so this uses
    `sharkweb.filament.timezone` (Asia/Dhaka by default) and resolves it in the browser with
    `Intl` too, so the ticking clock stays correct even if the browser is somewhere else.

    WHY PLAIN JAVASCRIPT AND NOT ALPINE.
    Render hooks render outside a Livewire component, so `@script`/`Alpine.data()` are not
    reliably registered here. The elements are re-queried on every tick rather than captured
    once, so a Livewire morph or a `wire:navigate` swap cannot leave the interval holding a
    detached node.
--}}
@php
    $timezone = (string) config('sharkweb.filament.timezone', 'Asia/Dhaka');
    $now = now()->setTimezone($timezone);

    $greeting = match (true) {
        (int) $now->format('G') < 12 => 'Good morning',
        (int) $now->format('G') < 17 => 'Good afternoon',
        default => 'Good evening',
    };

    $name = trim((string) (filament()->auth()->user()?->name ?? ''));
    $firstName = $name === '' ? '' : (preg_split('/\s+/u', $name) ?: [''])[0];
@endphp

<style>
    .shark-topbar-greeting {
        /* Hidden on narrow screens: the topbar already carries the brand, the search and the
           user menu, and a three-line stack there is noise rather than information. */
        display: none;
        flex-direction: column;
        align-items: flex-end;
        justify-content: center;
        line-height: 1.3;
        padding-inline-end: 1rem;
        margin-inline-end: 0.25rem;
        border-inline-end: 1px solid rgba(255, 255, 255, 0.12);
        text-align: end;
        white-space: nowrap;
    }

    @media (min-width: 640px) {
        .shark-topbar-greeting {
            display: flex;
        }
    }

    .shark-topbar-greeting__hello {
        font-size: 0.8125rem;
        font-weight: 500;
        color: #F5F5F5;
    }

    .shark-topbar-greeting__when {
        font-size: 0.75rem;
        color: #A1A1A1;
        /* Stops the block twitching sideways as the digits change. */
        font-variant-numeric: tabular-nums;
    }

    .shark-topbar-greeting__sep {
        margin-inline: 0.3rem;
        opacity: 0.5;
    }
</style>

<div class="shark-topbar-greeting" data-shark-timezone="{{ $timezone }}">
    <span class="shark-topbar-greeting__hello">
        {{ $greeting }}{{ $firstName !== '' ? ', '.$firstName : '' }}
    </span>

    <span class="shark-topbar-greeting__when">
        {{-- Server-rendered first, so there is no empty frame before the script runs. --}}
        <span class="shark-topbar-greeting__date">{{ $now->format('l, j F Y') }}</span>
        <span class="shark-topbar-greeting__sep" aria-hidden="true">·</span>
        <span class="shark-topbar-greeting__time">{{ $now->format('g:i A') }}</span>
    </span>
</div>

<script>
    (function () {
        var formatters = null;
        var formatterZone = null;

        function tick() {
            var root = document.querySelector('.shark-topbar-greeting');

            if (!root) {
                return;
            }

            var dateEl = root.querySelector('.shark-topbar-greeting__date');
            var timeEl = root.querySelector('.shark-topbar-greeting__time');
            var timezone = root.getAttribute('data-shark-timezone');

            if (!dateEl || !timeEl || !timezone) {
                return;
            }

            // The formatters are rebuilt only when the zone changes: constructing an Intl
            // formatter is not free and this runs once a second.
            if (timezone !== formatterZone) {
                formatterZone = timezone;
                formatters = {
                    date: new Intl.DateTimeFormat('en-GB', {
                        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: timezone
                    }),
                    time: new Intl.DateTimeFormat('en-GB', {
                        hour: 'numeric', minute: '2-digit', hour12: true, timeZone: timezone
                    })
                };
            }

            var now = new Date();

            dateEl.textContent = formatters.date.format(now);
            // en-GB renders the meridiem lowercase; the panel is a formal surface.
            timeEl.textContent = formatters.time.format(now).toUpperCase();
        }

        tick();
        setInterval(tick, 1000);
    })();
</script>
