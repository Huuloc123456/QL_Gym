<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GymSetting extends Model
{
    protected $fillable = [
        'gym_name', 'phone', 'email', 'address', 'opening_hours', 'introduction',
        'bank_name', 'bank_code', 'bank_account', 'bank_account_name', 'payment_hours', 'reminder_days',
        'shipping_fee', 'email_enabled',
        'faq_items',
    ];

    protected function casts(): array
    {
        return ['faq_items' => 'array', 'email_enabled' => 'boolean'];
    }

    public static function current(): self
    {
        $settings = self::query()->find(1);
        if ($settings !== null) {
            return $settings;
        }

        $settings = new self;
        $settings->forceFill(['id' => 1, 'gym_name' => 'The Gym'])->save();

        return $settings;
    }
}
