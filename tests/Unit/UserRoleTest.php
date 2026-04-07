<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

class UserRoleTest extends TestCase
{
    public function test_super_admin_has_all_permissions(): void
    {
        $this->assertTrue(UserRole::SUPER_ADMIN->hasPermission('anything'));
        $this->assertTrue(UserRole::SUPER_ADMIN->hasPermission('tickets.call'));
        $this->assertTrue(UserRole::SUPER_ADMIN->hasPermission('users.delete'));
    }

    public function test_tenant_admin_has_admin_permissions(): void
    {
        $this->assertTrue(UserRole::TENANT_ADMIN->hasPermission('branches.create'));
        $this->assertTrue(UserRole::TENANT_ADMIN->hasPermission('users.edit'));
        $this->assertTrue(UserRole::TENANT_ADMIN->hasPermission('reports.view'));
        $this->assertTrue(UserRole::TENANT_ADMIN->hasPermission('tickets.call'));
    }

    public function test_operator_can_manage_tickets(): void
    {
        $this->assertTrue(UserRole::OPERATOR->hasPermission('tickets.call'));
        $this->assertTrue(UserRole::OPERATOR->hasPermission('tickets.serve'));
        $this->assertTrue(UserRole::OPERATOR->hasPermission('tickets.complete'));
        $this->assertTrue(UserRole::OPERATOR->hasPermission('tickets.transfer'));
    }

    public function test_operator_cannot_manage_users(): void
    {
        $this->assertFalse(UserRole::OPERATOR->hasPermission('users.create'));
        $this->assertFalse(UserRole::OPERATOR->hasPermission('users.edit'));
        $this->assertFalse(UserRole::OPERATOR->hasPermission('branches.create'));
    }

    public function test_viewer_has_minimal_permissions(): void
    {
        $this->assertTrue(UserRole::VIEWER->hasPermission('reports.view'));
        $this->assertTrue(UserRole::VIEWER->hasPermission('tickets.view'));
        $this->assertFalse(UserRole::VIEWER->hasPermission('tickets.call'));
        $this->assertFalse(UserRole::VIEWER->hasPermission('users.create'));
    }

    public function test_receptionist_can_create_tickets(): void
    {
        $this->assertTrue(UserRole::RECEPTIONIST->hasPermission('tickets.create'));
        $this->assertTrue(UserRole::RECEPTIONIST->hasPermission('tickets.view'));
        $this->assertFalse(UserRole::RECEPTIONIST->hasPermission('tickets.call'));
    }

    public function test_role_levels_are_ordered(): void
    {
        $this->assertGreaterThan(UserRole::TENANT_ADMIN->level(), UserRole::SUPER_ADMIN->level());
        $this->assertGreaterThan(UserRole::BRANCH_MANAGER->level(), UserRole::TENANT_ADMIN->level());
        $this->assertGreaterThan(UserRole::OPERATOR->level(), UserRole::BRANCH_MANAGER->level());
        $this->assertGreaterThan(UserRole::RECEPTIONIST->level(), UserRole::OPERATOR->level());
        $this->assertGreaterThan(UserRole::VIEWER->level(), UserRole::RECEPTIONIST->level());
    }

    public function test_all_roles_have_labels(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertNotEmpty($role->label(), "{$role->value} should have a label");
        }
    }
}
