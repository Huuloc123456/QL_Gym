<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GymMessageNotification extends Notification
{
    public function __construct(public string $title, public string $body, public string $path) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->title.' - The Gym')
            ->greeting('Xin chào '.$notifiable->name.'!')->line($this->body)
            ->action('Xem trên The Gym', rtrim(config('app.url'), '/').$this->path)
            ->line('Vui lòng kiểm tra trạng thái mới nhất trên website trước khi thanh toán hoặc đến tập.');
    }
}
