<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * TelegramAlertService — Sends error notifications to a Telegram chat.
 *
 * Setup:
 * 1. Create a bot via @BotFather on Telegram → get the token
 * 2. Create a group/channel, add the bot, get the chat_id
 *    (send a message, then visit: https://api.telegram.org/bot<TOKEN>/getUpdates)
 * 3. Set in .env:
 *    TELEGRAM_BOT_TOKEN=123456:ABC-DEF...
 *    TELEGRAM_CHAT_ID=-100123456789
 *    TELEGRAM_ALERTS_ENABLED=true
 */
class TelegramAlertService
{
    private string $token;

    private string $chatId;

    private bool $enabled;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token', '');
        $this->chatId = config('services.telegram.chat_id', '');
        $this->enabled = config('services.telegram.alerts_enabled', false);
    }

    /**
     * Send an error alert to Telegram.
     */
    public function sendError(\Throwable $exception, array $context = []): void
    {
        if (! $this->enabled || empty($this->token) || empty($this->chatId)) {
            return;
        }

        try {
            $message = $this->formatError($exception, $context);
            $this->send($message);
        } catch (\Throwable $e) {
            // Don't let alert failures cascade — just log
            Log::warning("[TelegramAlert] Failed to send: {$e->getMessage()}");
        }
    }

    /**
     * Send a custom alert message.
     */
    public function sendAlert(string $title, string $body, string $level = 'warning'): void
    {
        if (! $this->enabled || empty($this->token) || empty($this->chatId)) {
            return;
        }

        $emoji = match ($level) {
            'critical' => '🔴',
            'error' => '❌',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            default => '📋',
        };

        try {
            $message = "{$emoji} *{$this->escape($title)}*\n\n{$this->escape($body)}";
            $message .= "\n\n🕐 ".now()->format('Y-m-d H:i:s').' UTC';
            $message .= "\n🌐 ".config('app.url');
            $this->send($message);
        } catch (\Throwable $e) {
            Log::warning("[TelegramAlert] Failed to send alert: {$e->getMessage()}");
        }
    }

    private function formatError(\Throwable $exception, array $context): string
    {
        $env = app()->environment();
        $url = $context['url'] ?? request()?->fullUrl() ?? 'N/A';
        $user = $context['user'] ?? (auth()->user()?->email ?? 'guest');
        $class = class_basename($exception);
        $file = Str::after($exception->getFile(), base_path().'/');
        $line = $exception->getLine();
        $messageText = Str::limit($exception->getMessage(), 300);

        // Truncate trace to keep Telegram message under 4096 chars
        $trace = Str::limit($exception->getTraceAsString(), 500);

        $text = "🚨 *ERROR EN PRODUCCIÓN*\n\n";
        $text .= "📛 *{$this->escape($class)}*\n";
        $text .= "💬 `{$this->escape($messageText)}`\n\n";
        $text .= "📁 `{$this->escape($file)}:{$line}`\n";
        $text .= "🌐 `{$this->escape($url)}`\n";
        $text .= "👤 `{$this->escape($user)}`\n";
        $text .= "🏗 `{$env}`\n";
        $text .= '🕐 '.now()->format('Y-m-d H:i:s')." UTC\n";

        if ($trace) {
            $text .= "\n📋 *Stack trace:*\n```\n{$trace}\n```";
        }

        return $text;
    }

    private function send(string $message): void
    {
        $response = Http::timeout(5)
            ->retry(2, 100)
            ->post("https://api.telegram.org/bot{$this->token}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ]);

        if (! $response->successful()) {
            Log::warning("[TelegramAlert] API error: {$response->status()} — {$response->body()}");
        }
    }

    /**
     * Escape Markdown special characters for Telegram.
     */
    private function escape(string $text): string
    {
        // For Markdown parse_mode, escape these characters
        return str_replace(
            ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'],
            ['\\_', '\\*', '\\[', '\\]', '\\(', '\\)', '\\~', '\\`', '\\>', '\\#', '\\+', '\\-', '\\=', '\\|', '\\{', '\\}', '\\.', '\\!'],
            $text
        );
    }
}
