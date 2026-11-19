<?php

namespace App\Notifications\Channels;

use App\Services\SmsService;
use Illuminate\Notifications\Notification;

/**
 * Delivers a notification's `toSms()` text to the number the notifiable
 * routes `sms` to — for a User, their *verified* number only (see
 * User::routeNotificationForSms). Nothing is sent without one.
 */
class SmsChannel
{
    public function __construct(private readonly SmsService $sms) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $to = $notifiable->routeNotificationFor('sms', $notification);

        if (! $to || ! method_exists($notification, 'toSms')) {
            return;
        }

        $this->sms->send($to, $notification->toSms($notifiable));
    }
}
