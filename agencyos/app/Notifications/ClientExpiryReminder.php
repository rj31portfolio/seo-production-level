<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientExpiryReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $clientName, public string $expiry, public int $days)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('SEO service renewal reminder')->line($this->clientName.' SEO service expires on '.$this->expiry.'.')->line($this->days === 0 ? 'The service period expires today.' : $this->days.' days remaining.')->line('Contact your agency to arrange renewal.');
    }
}
