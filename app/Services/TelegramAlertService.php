<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lead;
use App\Models\WebhookLog;
use App\Support\ShopGateways;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Instant mobile alerts for new leads (§14.8).
 *
 * Telegram is used rather than SMS or push because it is free, has no per-message cost, and
 * delivers in about a second — which matters when the whole value of an alert is that it
 * arrives before the lead goes cold.
 *
 * Like the other outbound services here, this never throws and never queues. It runs inside
 * a lead submission, and a messaging outage must not cost us the lead itself. The lead is
 * already persisted before this is called.
 *
 * FORMAT: HTML, not MarkdownV2. Telegram's MarkdownV2 requires escaping ~18 reserved
 * characters, and a single unescaped `-` or `.` in a client's message causes the API to
 * reject the whole message. HTML needs three (`<`, `>`, `&`), so it is far harder to break
 * with arbitrary user input.
 */
class TelegramAlertService
{
    /**
     * Send a new-lead alert. Returns true when Telegram accepted it.
     */
    public function sendLeadAlert(Lead $lead): bool
    {
        if (! config('sharkweb.telegram.enabled')) {
            return false;
        }

        $token = (string) config('sharkweb.telegram.bot_token');
        $chatId = (string) config('sharkweb.telegram.chat_id');

        if ($token === '' || $chatId === '') {
            Log::warning('Telegram alert skipped: TELEGRAM_BOT_TOKEN or TELEGRAM_CHAT_ID is not configured.');

            return false;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $this->compose($lead),
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        return $this->dispatch($token, $payload, $lead);
    }

    /**
     * Send a plain message. Used for system notifications (backup results, errors).
     */
    public function sendMessage(string $text): bool
    {
        if (! config('sharkweb.telegram.enabled')) {
            return false;
        }

        $token = (string) config('sharkweb.telegram.bot_token');
        $chatId = (string) config('sharkweb.telegram.chat_id');

        if ($token === '' || $chatId === '') {
            return false;
        }

        return $this->dispatch($token, [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ], null);
    }

    /**
     * Confirm a button press. Telegram shows this as a toast; without it the client spins
     * for a few seconds and looks broken.
     */
    public function answerCallback(string $callbackId, string $text): bool
    {
        return $this->call('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => $text,
        ], 'telegram.answer_callback');
    }

    /**
     * Rewrite the alert once a decision has been made.
     *
     * The text is replaced rather than the buttons merely removed, so the chat history reads
     * as a record: what was decided, by whom, and when — and a second tap has nothing to hit.
     */
    public function replaceMessage(string $chatId, int $messageId, string $text): bool
    {
        return $this->call('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ], 'telegram.edit_message');
    }

    /** Swap the keyboard under a message without touching its text. */
    public function replaceKeyboard(string $chatId, int $messageId, array $keyboard): bool
    {
        return $this->call('editMessageReplyMarkup', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => $keyboard,
        ], 'telegram.edit_keyboard');
    }

    /** True when a bot token and chat are both configured and alerts are switched on. */
    private function configured(): bool
    {
        if (! config('sharkweb.telegram.enabled')) {
            return false;
        }

        if (filled(config('sharkweb.telegram.bot_token')) && filled(config('sharkweb.telegram.chat_id'))) {
            return true;
        }

        Log::warning('Telegram alert skipped: TELEGRAM_BOT_TOKEN or TELEGRAM_CHAT_ID is not configured.');

        return false;
    }

    /**
     * The alert body.
     *
     * Ordered by what the recipient needs in the notification preview alone: score, then
     * name and the goal, then the message. Everything else is below the fold on purpose.
     */
    private function compose(Lead $lead): string
    {
        $score = $lead->lead_score?->getLabel() ?? 'Unknown';
        $status = $lead->status?->getLabel() ?? 'New';

        $lines = [
            '<b>🎯 New lead — '.$this->escape($score).'</b>',
            '',
            '<b>'.$this->escape($lead->displayName()).'</b>',
            '📧 '.$this->escape((string) $lead->email),
        ];

        if (filled($lead->phone)) {
            $lines[] = '📞 '.$this->escape((string) $lead->phone);
        }

        $lines[] = '🎯 '.$this->escape((string) ($lead->goal ?: 'Growth Audit'));
        $lines[] = '📊 '.$this->escape($status);

        if (filled($lead->message)) {
            $lines[] = '';
            // Telegram caps messages at 4096 characters.
            $lines[] = '<blockquote>'.$this->escape(mb_substr((string) $lead->message, 0, 600)).'</blockquote>';
        }

        if ($lead->hasAttribution()) {
            $lines[] = '';
            $lines[] = '📈 '.$this->escape($this->attributionLine($lead));
        }

        $lines[] = '';
        $lines[] = '<i>'.now()->format('j M Y, H:i').' · #'.$lead->id.'</i>';

        return implode("\n", $lines);
    }

    private function attributionLine(Lead $lead): string
    {
        return collect([$lead->utm_source, $lead->utm_medium, $lead->utm_campaign])
            ->filter()
            ->implode(' / ') ?: 'Direct';
    }

    private function dispatch(string $token, array $payload, ?Lead $lead, string $event = 'telegram.lead_alert', ?array $context = null): bool
    {
        return $this->call('sendMessage', $payload, $event, $context, $token, $lead);
    }

    /**
     * One Telegram Bot API call. Never throws, never queues, always logged.
     *
     * The webhook replies (answering a tap, rewriting the alert) go through here too, so an
     * outage while the operator is tapping a button degrades to "the buttons did not update"
     * rather than a 500 that makes Telegram retry the same callback for hours.
     */
    private function call(
        string $method,
        array $payload,
        string $event,
        ?array $context = null,
        ?string $token = null,
        ?Lead $lead = null,
    ): bool {
        $token ??= (string) config('sharkweb.telegram.bot_token');

        if ($token === '') {
            Log::warning('Telegram call skipped: no bot token configured.', ['method' => $method]);

            return false;
        }

        $url = "https://api.telegram.org/bot{$token}/{$method}";
        $startedAt = microtime(true);

        try {
            $response = Http::timeout((int) config('sharkweb.telegram.timeout', 5))
                ->asJson()
                ->post($url, $payload);

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            if ($response->successful() && ($response->json('ok') === true)) {
                $this->log($lead, $event, $response->status(), $durationMs, $context);

                return true;
            }

            Log::warning('Telegram call rejected.', [
                'method' => $method,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $this->log($lead, $event, $response->status(), $durationMs, $context);

            return false;
        } catch (Throwable $e) {
            // A messaging failure must never surface to the visitor or lose the lead.
            Log::warning('Telegram transport failure.', ['method' => $method, 'error' => $e->getMessage()]);

            $this->log($lead, $event, null, (int) round((microtime(true) - $startedAt) * 1000), $context);

            return false;
        }
    }

    /**
     * Record the attempt without storing the message body — webhook_logs is readable in the
     * admin panel, and the alert contains a client's enquiry.
     */
    private function log(?Lead $lead, string $event, ?int $status, int $durationMs, ?array $context = null): void
    {
        try {
            WebhookLog::record(
                event: $event,
                payload: $context ?? ($lead ? ['lead_id' => $lead->id] : null),
                status: $status,
                body: null,
                durationMs: $durationMs,
            );
        } catch (Throwable $e) {
            Log::warning('Could not write Telegram webhook log.', ['error' => $e->getMessage()]);
        }
    }

    /** Telegram HTML mode only requires these three. */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
