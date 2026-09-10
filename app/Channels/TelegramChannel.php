<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * قناة تيليجرام مبنية يدوياً باستخدام Telegram Bot API مباشرة،
 * بدون الاعتماد على package خارجي — أقل اعتمادية وأسهل شرحها لعميل تقني.
 */
class TelegramChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toTelegram')) {
            return;
        }

        $token = config('monitoring.notifications.telegram_bot_token');
        $chatId = config('monitoring.notifications.telegram_chat_id');

        if (! $token || ! $chatId) {
            return; // التليجرام غير مفعّل بهذه البيئة، تجاهل بصمت
        }

        $message = $notification->toTelegram($notifiable);

        $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id'    => $chatId,
            'text'       => $message,
            'parse_mode' => 'Markdown',
        ]);

        if ($response->failed()) {
            Log::warning('Telegram notification failed to send', [
                'response' => $response->body(),
            ]);
        }
    }
}
