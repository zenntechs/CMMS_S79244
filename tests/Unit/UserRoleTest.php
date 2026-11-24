<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

class UserRoleTest extends TestCase
{
    public function test_roles_have_the_expected_values_and_labels(): void
    {
        $this->assertSame('chief_engineer', UserRole::ChiefEngineer->value);
        $this->assertSame('technician', UserRole::Technician->value);
        $this->assertSame('viewer', UserRole::Viewer->value);

        $this->assertSame('Chief Engineer', UserRole::ChiefEngineer->label());
        $this->assertSame('Technician', UserRole::Technician->label());
        $this->assertSame('Viewer', UserRole::Viewer->label());
    }
}
