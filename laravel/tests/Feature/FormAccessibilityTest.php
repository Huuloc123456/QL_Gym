<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_login_describes_the_error_at_the_username_field(): void
    {
        $response = $this->followingRedirects()->from(route('login'))->post(route('login.store'), [
            'username' => 'unknown_member',
            'password' => 'wrong-password',
        ])->assertOk();
        $this->assertFieldError($response->getContent(), 'username');
        $response->assertSee('unknown_member')->assertSee('Tên đăng nhập hoặc mật khẩu chưa chính xác.');
    }

    public function test_invalid_package_values_have_individual_error_descriptions(): void
    {
        $response = $this->actingAs(User::factory()->manager()->create())->followingRedirects()
            ->from(route('manager.packages.create'))
            ->post(route('manager.packages.store'), [
                'name' => 'Gói sai',
                'price' => -1,
                'duration_months' => 0,
            ])->assertOk();
        $this->assertFieldError($response->getContent(), 'package-price');
        $this->assertFieldError($response->getContent(), 'package-duration');
        $this->assertDatabaseMissing('gym_packages', ['name' => 'Gói sai']);
    }

    private function assertFieldError(string $html, string $fieldId): void
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $field = $document->getElementById($fieldId);
        $this->assertNotNull($field);
        $this->assertSame('true', $field->getAttribute('aria-invalid'), $document->saveHTML($field));
        $descriptionIds = preg_split('/\s+/', trim($field->getAttribute('aria-describedby')));
        $this->assertContains($fieldId.'-error', $descriptionIds);
        $error = $document->getElementById($fieldId.'-error');
        $this->assertNotNull($error);
        $this->assertNotSame('', trim($error->textContent));
    }
}
