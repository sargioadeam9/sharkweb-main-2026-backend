<?php

declare(strict_types=1);

/**
 * The Shark Web — project configuration.
 *
 * Everything environment-specific lives here rather than in scattered env() calls,
 * so `php artisan config:cache` works correctly in production. Never call env()
 * outside a config file.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Frontend origin
    |--------------------------------------------------------------------------
    | The public Next.js site. Used to build absolute canonical URLs, sitemap
    | entries and the IndexNow URL list.
    */
    'frontend_url' => env('FRONTEND_URL', 'https://thesharkweb.com'),

    /*
    |--------------------------------------------------------------------------
    | Server-to-server authentication (§7.1)
    |--------------------------------------------------------------------------
    | Next.js Server Actions authenticate against /api/v1 with this key. The
    | visitor's browser never sees it.
    */
    'api_key' => env('LARAVEL_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Meta Conversions API
    |--------------------------------------------------------------------------
    | Server-side conversion reporting. The credentials and the on/off switch live
    | on the Settings page, not here — a token that needs a deploy to rotate is a
    | token that stays broken for a week.
    |
    | `enabled` exists only because the settings migration seeds its toggle from
    | this value, so a deployment that already had META_CAPI_ENABLED=true does not
    | silently stop reporting on the day that migration runs. From then on the
    | panel is the switch; nothing reads this again.
    |
    | `graph_version` is pinned rather than floating: Meta deprecates API versions
    | on a schedule, and an unversioned call would change shape under us.
    */
    'meta' => [
        'enabled' => (bool) env('META_CAPI_ENABLED', false),
        'graph_version' => env('META_GRAPH_VERSION', 'v21.0'),
        'timeout' => (int) env('META_CAPI_TIMEOUT', 6),
    ],


    /*
    |--------------------------------------------------------------------------
    | API rate limit + trusted proxies
    |--------------------------------------------------------------------------
    | `api.rate_limit` is the global per-minute ceiling attached to every
    | key-guarded route (`throttle:api` in routes/api.php). The per-endpoint
    | limits (leads, newsletter, orders, beacon) are defined separately in
    | AppServiceProvider and are tighter than this.
    |
    | Trusted proxies are configured in bootstrap/app.php via the TRUSTED_PROXIES
    | environment variable — not here, because the middleware callback runs before
    | the config repository exists. See the TRUSTED_PROXIES block in .env.example
    | for when to set it and what goes wrong in each direction.
    */
    'api' => [
        'rate_limit' => (int) env('SHARKWEB_API_RATE_LIMIT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Draft preview (§5)
    |--------------------------------------------------------------------------
    */
    'preview_secret' => env('PREVIEW_SECRET'),
    'preview_token_ttl' => (int) env('PREVIEW_TOKEN_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | On-demand ISR revalidation (§6)
    |--------------------------------------------------------------------------
    | Observers POST to {nextjs_url}/api/revalidate when content changes.
    */
    'isr' => [
        'enabled' => (bool) env('ISR_ENABLED', true),
        'nextjs_url' => env('NEXTJS_URL', 'https://thesharkweb.com'),
        'endpoint' => env('NEXTJS_REVALIDATE_PATH', '/api/revalidate'),
        'secret' => env('REVALIDATE_SECRET'),
        'timeout' => (int) env('ISR_TIMEOUT', 5),
        'retries' => (int) env('ISR_RETRIES', 2),
        'retry_backoff_ms' => (int) env('ISR_RETRY_BACKOFF_MS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | IndexNow — Bing, Copilot, Perplexity, Naver, Seznam (§14.15)
    |--------------------------------------------------------------------------
    | The key must also be served as a plain-text file at
    | https://thesharkweb.com/{key}.txt or the submission is rejected.
    */
    'indexnow' => [
        'enabled' => (bool) env('INDEXNOW_ENABLED', false),
        'key' => env('INDEXNOW_KEY'),
        'endpoint' => env('INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
        'timeout' => (int) env('INDEXNOW_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Indexing API (§14.12)
    |--------------------------------------------------------------------------
    */
    'google_indexing' => [
        'enabled' => (bool) env('GOOGLE_INDEXING_ENABLED', false),
        'credentials_path' => env('GOOGLE_SERVICE_ACCOUNT_JSON'),
        'endpoint' => 'https://indexing.googleapis.com/v3/urlNotifications:publish',
        'token_uri' => 'https://oauth2.googleapis.com/token',
        'scope' => 'https://www.googleapis.com/auth/indexing',
    ],

    /*
    |--------------------------------------------------------------------------
    | Instant lead alerts (§14.8)
    |--------------------------------------------------------------------------
    */
    'telegram' => [
        'enabled' => (bool) env('TELEGRAM_ALERTS_ENABLED', false),
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
        'timeout' => (int) env('TELEGRAM_TIMEOUT', 5),

        /*
         * ONE-TAP PAYMENT APPROVALS.
         *
         * The order alert carries Approve / Reject buttons, and Telegram posts the tap back to
         * `/api/telegram/webhook`. That endpoint is public by necessity — Telegram cannot
         * carry our API key — so it is defended twice:
         *
         *  1. `webhook_secret` is registered with setWebhook and echoed back in the
         *     `X-Telegram-Bot-Api-Secret-Token` header. A request without it is refused.
         *  2. Only the configured `chat_id` above may act. A tap from any other chat is
         *     ignored, however it was forged.
         *
         * `operator_email` is the account recorded as `verified_by`. Money decisions need an
         * author, and a decision made from a chat must name the same person the panel would.
         * If that account does not exist the endpoint REFUSES to act — it never approves as
         * "nobody".
         */
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'operator_email' => env('TELEGRAM_OPERATOR_EMAIL', 'admin@thesharkweb.com'),

        /*
         * Where Telegram posts taps. Defaults to this app's own `/api/telegram/webhook`
         * resolved from APP_URL — override only if the public URL differs from APP_URL.
         */
        'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
    ],

    'leads' => [
        'notification_email' => env('LEAD_NOTIFICATION_EMAIL', 'info@thesharkweb.com'),
        'webhook_url' => env('LEAD_WEBHOOK_URL'),
        'webhook_secret' => env('LEAD_WEBHOOK_SECRET'),

        // §4 — 5 submissions per 10 minutes per IP.
        'rate_limit' => [
            'max_attempts' => (int) env('LEAD_RATE_MAX', 5),
            'decay_minutes' => (int) env('LEAD_RATE_DECAY_MINUTES', 10),
        ],

        // Submissions faster than this are bots (a human cannot fill the form this fast).
        'minimum_fill_seconds' => (int) env('LEAD_MIN_FILL_SECONDS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cal.com booking webhook
    |--------------------------------------------------------------------------
    | Inbound appointment booking webhook from Cal.com to stamp leads.booked_at.
    */
    'calcom' => [
        'webhook_secret' => env('CALCOM_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Weekly performance digest (§14.13)
    |--------------------------------------------------------------------------
    | `recipients` is an explicit allow-list so the digest can go to more than one
    | inbox without changing where new-lead alerts land. When DIGEST_RECIPIENTS is
    | empty it falls back to the lead notification address, which is what the
    | blueprint specifies.
    */
    'digest' => [
        'recipients' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('DIGEST_RECIPIENTS', ''))
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Daily KPI report (§Daily report)
    |--------------------------------------------------------------------------
    | THE DAY IS A DHAKA DAY, not a UTC one. The owner reads this at 23:30 local and
    | compares it with the working day he just had; a UTC window would cut the day at
    | 06:00 local and move the evening's traffic into "tomorrow".
    |
    | The on/off switch and the recipient live in Settings (general.daily_report_*),
    | not here — changing where a report goes must not need a deploy. Only the schedule
    | shape is config, because it is read when the scheduler builds its list.
    */
    'daily_report' => [
        'timezone' => env('DAILY_REPORT_TIMEZONE', 'Asia/Dhaka'),
        'send_at' => env('DAILY_REPORT_TIME', '23:30'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Agency assets & contact details
    |--------------------------------------------------------------------------
    | These are the contact details documented in docs/existingwebsitecontents.md (the
    | single authority for site content) and mirrored by the frontend footer.
    | They live in config rather than inline in a Blade template because the
    | hello@/info@ drift already happened once — one source, no second copy.
    |
    | `deck_url` defaults to null ON PURPOSE: no agency deck file is documented,
    | and a dead link in a client-facing email is worse than no link. Set
    | AGENCY_DECK_URL once the file actually exists; the email omits the whole
    | section until then.
    */
    'agency' => [
        'deck_url' => env('AGENCY_DECK_URL'),
    ],

    'contact' => [
        'email' => env('CONTACT_EMAIL', 'info@thesharkweb.com'),
        'whatsapp' => env('CONTACT_WHATSAPP', '+8801896444400'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Newsletter — Brevo (§Module G)
    |--------------------------------------------------------------------------
    */
    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
        'list_id' => env('BREVO_LIST_ID'),
        'endpoint' => 'https://api.brevo.com/v3/contacts',
    ],

    /*
    |--------------------------------------------------------------------------
    | CORS (§7.2)
    |--------------------------------------------------------------------------
    */
    'cors' => [
        'allowed_origins' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
        ))),
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Accept', 'Authorization', 'X-Requested-With'],
        'max_age' => 86400,
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Library
    |--------------------------------------------------------------------------
    | Single source of truth for image conversions so the admin panel and the
    | API agree on every generated size.
    */
    'media' => [
        'conversions' => [
            'thumb' => ['width' => 400, 'height' => 300],
            'card' => ['width' => 800, 'height' => 600],
            'hero' => ['width' => 1600, 'height' => 900],
            'social' => ['width' => 1200, 'height' => 630],
        ],
        'quality' => (int) env('MEDIA_QUALITY', 82),
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament admin panel
    |--------------------------------------------------------------------------
    | Aliased away from /admin to cut down automated scanning (§7.4).
    */
    'filament' => [
        'path' => env('FILAMENT_PATH', 'theshark-hq'),
        'brand_name' => env('FILAMENT_BRAND_NAME', 'The Shark Web HQ'),

        /*
         * The timezone the admin panel's clock shows.
         *
         * NOT `app.timezone`, which is UTC on purpose: timestamps are stored in UTC and that
         * is correct. But a clock the owner reads should show the time he is actually working
         * in, and a topbar saying 18:49 when it is nearly midnight locally is worse than no
         * clock at all.
         */
        'timezone' => env('FILAMENT_TIMEZONE', 'Asia/Dhaka'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance pages (§Maintenance)
    |--------------------------------------------------------------------------
    | Only these panel accounts may open Backups & restore, the cleaner and the
    | activity log — the pages that destroy or overwrite state. Empty means "every
    | operator", which today is the single owner account; add comma-separated emails
    | here the day a second account exists.
    */
    'maintenance' => [
        'super_admins' => env('MAINTENANCE_SUPER_ADMINS', 'admin@thesharkweb.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity log (§Maintenance)
    'activity' => [
        'retention_days' => (int) env('ACTIVITY_RETENTION_DAYS', 180),
        'delete_chunk' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Digital shop
    |--------------------------------------------------------------------------
    | Products are sold in BDT. Payment is the Send Money manual reconciliation
    | flow: the buyer sends money from their own bKash or Nagad wallet to the
    | personal number below, then submits the TrxID, and an operator approves it
    | in the admin panel. No merchant API is involved.
    */
    'shop' => [
        'currency' => env('SHOP_CURRENCY', 'BDT'),
        'currency_symbol' => env('SHOP_CURRENCY_SYMBOL', '৳'),

        // Applied server-side on every order. The client never sends a discount.
        'launch_discount_rate' => (float) env('SHOP_DISCOUNT_RATE', 0.10),

        /*
         * The Shop analytics page's month-to-date progress bar collects against this target,
         * in integer paisa. Config for the same reason the automation windows are: the owner
         * retunes a goal without a deploy. 0 hides the bar entirely — a shop with no target
         * should not see an empty gauge pretending to be information.
         */
        'monthly_target_minor' => (int) env('SHOP_MONTHLY_TARGET_MINOR', 5_000_000),

        /*
         * Where "a TrxID is waiting to be verified" alerts land. Unset falls back to the
         * lead notification address, so a shop-only account is optional — the alerts still
         * reach a monitored inbox either way.
         */
        'notification_email' => env('SHOP_NOTIFICATION_EMAIL'),

        // Max distinct products in one order. A digital order is normally 1–5 items;
        // the cap stops a malformed payload from turning one request into a 500-row
        // insert and a full catalogue scan.
        'max_items_per_order' => (int) env('SHOP_MAX_ITEMS', 20),

        /*
         * There is deliberately no `payment_enabled` flag.
         *
         * One existed while the gateway flow was a placeholder. Now that the flow is real it
         * would be a switch that looks like it disables payments but does not — someone would
         * flip it expecting the checkout to close and nothing would happen. Taking payments
         * offline is done by removing the receiving numbers from `gateways` below, which the
         * checkout actually reads.
         */

        /*
         * The personal numbers buyers send money to.
         *
         * These are the client's real receiving numbers, supplied in
         * docs/BKASH_NAGAD_SANDBOX_PLAN.md. They are configuration rather than constants because
         * they will change — a number that is wrong means money arrives in the wrong wallet,
         * so it must be editable without a deploy.
         */
        'gateways' => [
            'bkash' => [
                'label' => 'bKash',
                'account_type' => 'Personal',
                'number' => env('SHOP_BKASH_NUMBER', '01725111804'),
                'hotline' => '16247',
            ],
            'nagad' => [
                'label' => 'Nagad',
                'account_type' => 'Personal',
                'number' => env('SHOP_NAGAD_NUMBER', '01979996659'),
                'hotline' => '16167',
            ],
        ],

        /*
         * How long the buyer's verification window stays open, and how long the download
         * link stays valid once the order is approved.
         */
        'verification_window_minutes' => (int) env('SHOP_VERIFY_WINDOW', 15),
        'download_link_hours' => (int) env('SHOP_DOWNLOAD_HOURS', 72),

        /*
         * The Shop analytics automations (see App\Services\ShopAutomationService).
         *
         * Kept in config rather than as constants for the same reason the receiving numbers are:
         * the owner must be able to loosen a reminder window or silence an alert without a
         * deploy. Nothing here is a promise made to a buyer — `verification_window_minutes`
         * above is the only figure the checkout shows.
         *
         * `max_reminders_per_order` is the important one. It exists so the unpaid-order reminder
         * can never become a drip campaign against someone who changed their mind.
         */
        'automations' => [
            'unpaid_reminder_hours' => (int) env('SHOP_REMINDER_HOURS', 24),
            'reminder_cooldown_hours' => (int) env('SHOP_REMINDER_COOLDOWN_HOURS', 24),
            'max_reminders_per_order' => (int) env('SHOP_MAX_REMINDERS', 2),
            'stale_verification_minutes' => (int) env('SHOP_STALE_VERIFY_MINUTES', 30),
            'stale_alert_cooldown_hours' => (int) env('SHOP_STALE_ALERT_COOLDOWN_HOURS', 2),
            'reminder_batch_limit' => (int) env('SHOP_REMINDER_BATCH', 25),
        ],

        'rate_limit' => [
            'max_attempts' => (int) env('SHOP_ORDER_RATE_LIMIT', 10),
            'decay_minutes' => (int) env('SHOP_ORDER_RATE_DECAY', 10),
        ],
    ],

];
