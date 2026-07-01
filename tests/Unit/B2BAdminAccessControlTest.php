<?php

namespace Tests\Unit;

use Tests\TestCase;
use VanguardLTE\B2B\Services\B2BAdminAccessControl;

class B2BAdminAccessControlTest extends TestCase
{
    public function testPrivilegedActionsDenyByDefaultForUnknownAction()
    {
        $result = app(B2BAdminAccessControl::class)->authorizePrivilegedAction('unknown.action', [
            'actor' => 'ops_user',
            'reason' => 'Case reference',
            'permission' => 'b2b.credentials.rotate',
            'confirm' => 'ROTATE_API_KEY',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('unknown_action', $result['code']);
    }

    public function testPrivilegedActionsRequireExactPermissionAndStepUpConfirmation()
    {
        $access = app(B2BAdminAccessControl::class);

        $missingPermission = $access->authorizePrivilegedAction('api_key.rotate', [
            'actor' => 'ops_user',
            'reason' => 'Case reference',
            'permission' => 'b2b.credentials.revoke',
            'confirm' => 'ROTATE_API_KEY',
        ]);

        $this->assertFalse($missingPermission['ok']);
        $this->assertSame('permission_required', $missingPermission['code']);
        $this->assertSame('b2b.credentials.rotate', $missingPermission['required_permission']);

        $missingStepUp = $access->authorizePrivilegedAction('api_key.rotate', [
            'actor' => 'ops_user',
            'reason' => 'Case reference',
            'permission' => 'b2b.credentials.rotate',
        ]);

        $this->assertFalse($missingStepUp['ok']);
        $this->assertSame('step_up_required', $missingStepUp['code']);
        $this->assertSame('ROTATE_API_KEY', $missingStepUp['required_confirmation']);

        $allowed = $access->authorizePrivilegedAction('api_key.rotate', [
            'actor' => 'ops_user',
            'reason' => 'Case reference',
            'permission' => 'b2b.credentials.rotate',
            'confirm' => 'ROTATE_API_KEY',
        ]);

        $this->assertTrue($allowed['ok']);
        $this->assertSame('b2b.credentials.rotate', $allowed['permission']);
        $this->assertTrue($allowed['step_up']);

        $operatorUpdate = $access->authorizePrivilegedAction('operator.update', [
            'actor' => 'ops_user',
            'reason' => 'Operator settings change.',
            'permission' => 'b2b.operators.update',
            'confirm' => 'UPDATE_OPERATOR',
        ]);

        $this->assertTrue($operatorUpdate['ok']);
        $this->assertSame('b2b.operators.update', $operatorUpdate['permission']);

        $operatorSuspend = $access->authorizePrivilegedAction('operator.suspend', [
            'actor' => 'ops_user',
            'reason' => 'Operator incident.',
            'permission' => 'b2b.operators.suspend',
            'confirm' => 'SUSPEND_OPERATOR',
        ]);

        $this->assertTrue($operatorSuspend['ok']);
        $this->assertSame('b2b.operators.suspend', $operatorSuspend['permission']);

        $rawPayload = $access->authorizePrivilegedAction('payload.view_raw', [
            'actor' => 'ops_user',
            'reason' => 'Wallet incident investigation.',
            'permission' => 'b2b.payloads.view_raw',
            'confirm' => 'VIEW_RAW_PAYLOAD',
        ]);

        $this->assertTrue($rawPayload['ok']);
        $this->assertSame('b2b.payloads.view_raw', $rawPayload['permission']);

        $caseClaim = $access->authorizePrivilegedAction('case.claim', [
            'actor' => 'ops_user',
            'reason' => 'Taking ownership of a reconciliation case.',
            'permission' => 'b2b.cases.manage',
            'confirm' => 'CLAIM_CASE',
        ]);

        $this->assertTrue($caseClaim['ok']);
        $this->assertSame('b2b.cases.manage', $caseClaim['permission']);
    }

    public function testConfiguredRolesDenyByDefaultAndGrantKnownPermissions()
    {
        $access = app(B2BAdminAccessControl::class);

        $this->assertFalse($access->roleGrantsPermission('read_only', 'b2b.credentials.rotate'));
        $this->assertTrue($access->roleGrantsPermission('integration_manager', 'b2b.credentials.rotate'));
        $this->assertTrue($access->roleGrantsPermission('super_admin', 'b2b.payloads.view_raw'));
        $this->assertTrue($access->roleGrantsPermission('support', 'b2b.cases.view'));
        $this->assertFalse($access->roleGrantsPermission('support', 'b2b.cases.manage'));
        $this->assertTrue($access->roleGrantsPermission('finance', 'b2b.cases.manage'));
        $this->assertFalse($access->roleGrantsPermission('missing_role', 'b2b.audit.view'));
    }

    public function testUserPermissionChecksUseRoleMapWhenExistingPermissionTraitDenies()
    {
        $user = new class {
            public $role;

            public function __construct()
            {
                $this->role = (object) ['slug' => 'finance', 'name' => 'Finance'];
            }

            public function hasPermission($permission)
            {
                return false;
            }
        };

        $access = app(B2BAdminAccessControl::class);

        $this->assertTrue($access->userHasPermission($user, 'b2b.wallet.manual_action'));
        $this->assertFalse($access->userHasPermission($user, 'b2b.credentials.revoke'));
    }
}
