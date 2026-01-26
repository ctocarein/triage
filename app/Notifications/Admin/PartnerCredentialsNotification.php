<?php

namespace App\Notifications\Admin;

use Illuminate\Notifications\Notification;

/**
 * Deprecated legacy notification (admin system removed).
 */
class PartnerCredentialsNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return [];
    }
}