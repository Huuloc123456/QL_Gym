<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class CourseTheoryAlignmentTest extends TestCase
{
    public function test_manager_crud_routes_use_resource_create_and_edit_paths(): void
    {
        $this->assertSame('/quan-ly/goi-tap/create', parse_url(route('manager.packages.create'), PHP_URL_PATH));
        $this->assertSame('/quan-ly/goi-tap/12/edit', parse_url(route('manager.packages.edit', 12), PHP_URL_PATH));
        $this->assertSame('/quan-ly/nhan-vien/create', parse_url(route('manager.employees.create'), PHP_URL_PATH));
        $this->assertSame('/quan-ly/nhan-vien/12/edit', parse_url(route('manager.employees.edit', 12), PHP_URL_PATH));
    }

    public function test_employee_form_displays_validation_error_at_its_blade_input(): void
    {
        $this->withSession(['_old_input' => ['username' => 'existing-user']]);
        $this->withViewErrors(['username' => 'Tên đăng nhập đã được sử dụng.']);

        $this->view('manager.employees.form', [
            'employee' => new User(['username' => 'existing-user']),
            'isEditing' => false,
        ])
            ->assertSee('value="existing-user"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('id="username-error"', false)
            ->assertSee('Tên đăng nhập đã được sử dụng.');
    }
}
