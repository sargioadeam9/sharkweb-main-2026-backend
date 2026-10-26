{{--
    Shown on every panel screen while ISR revalidation is switched off.

    WHY THIS EXISTS. `ISR_ENABLED=false` is not an error — nothing fails, nothing logs, and
    the save genuinely persists. What happens instead is that the public page keeps serving
    its cached copy until the 5-minute ISR window lapses. From the owner's side that is
    indistinguishable from "the edit did not save", which is exactly the confusion this
    banner is meant to end.

    It reads the live config rather than a hardcoded string, so flipping the flag back on
    makes it disappear without a deploy.
--}}
@if (! config('sharkweb.isr.enabled'))
    <div class="fi-isr-warning">
        <div class="fi-isr-warning__icon" aria-hidden="true">
            <x-filament::icon icon="heroicon-o-exclamation-triangle" />
        </div>

        <div class="fi-isr-warning__body">
            <p class="fi-isr-warning__title">Automatic publishing is off</p>
            <p class="fi-isr-warning__text">
                Your edits are being saved, but the live site will keep showing the old copy
                for up to five minutes. Set <code>ISR_ENABLED=true</code> in the backend
                <code>.env</code> and clear the config cache to make changes appear immediately.
            </p>
        </div>
    </div>

    <style>
        .fi-isr-warning {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
            margin: 0 0 1rem;
            padding: 0.875rem 1rem;
            border: 1px solid rgba(245, 158, 11, 0.35);
            border-radius: 0.75rem;
            background: rgba(245, 158, 11, 0.1);
            color: rgb(253 230 138);
        }

        .fi-isr-warning__icon {
            flex: none;
            width: 1.25rem;
            height: 1.25rem;
            margin-top: 0.125rem;
            color: rgb(251 191 36);
        }

        .fi-isr-warning__title {
            margin: 0;
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1.25rem;
        }

        .fi-isr-warning__text {
            margin: 0.25rem 0 0;
            font-size: 0.8125rem;
            line-height: 1.4rem;
            color: rgba(253, 230, 138, 0.85);
        }

        .fi-isr-warning__text code {
            padding: 0 0.25rem;
            border-radius: 0.25rem;
            background: rgba(0, 0, 0, 0.25);
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 0.75rem;
            color: rgb(253 230 138);
        }
    </style>
@endif
