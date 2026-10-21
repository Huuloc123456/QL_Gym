<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GymSchemaCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_merged_schema_keeps_local_settings_and_account_lock_fields_alongside_portal_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('gym_settings', [
            'gym_name',
            'opening_hours',
            'bank_account_name',
            'introduction',
            'faq_items',
            'payment_hours',
            'reminder_days',
            'shipping_fee',
            'email_enabled',
            'last_processed_at',
        ]));

        $this->assertTrue(Schema::hasColumns('users', [
            'is_active',
            'locked_at',
            'locked_by',
            'sql_customer_id',
            'sql_employee_id',
        ]));
    }
}
