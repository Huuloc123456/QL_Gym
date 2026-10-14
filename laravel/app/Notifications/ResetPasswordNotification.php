<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(public readonly string $token) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $resetPath = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], absolute: false);
        $resetUrl = rtrim((string) config('app.url'), '/').$resetPath;
        $expiresInMinutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Khôi phục mật khẩu - The Gym')
            ->greeting('Xin chào '.$notifiable->name.'!')
            ->line('Chúng tôi nhận được yêu cầu đổi mật khẩu cho tài khoản The Gym của bạn.')
            ->action('Đặt lại mật khẩu', $resetUrl)
            ->line("Liên kết này chỉ sử dụng được một lần và hết hạn sau {$expiresInMinutes} phút.")
            ->line('Nếu bạn không yêu cầu đổi mật khẩu, hãy bỏ qua email này. Mật khẩu hiện tại của bạn vẫn an toàn.');
    }
}
