<?php

namespace VanguardLTE\B2B\Services;

class B2BPrivilegedActionGuard
{
    private $access;
    private $audit;

    public function __construct(B2BAdminAccessControl $access, B2BOperatorAuditLogger $audit)
    {
        $this->access = $access;
        $this->audit = $audit;
    }

    public function authorize($operator, $action, $actor, $reason, $permission, $confirm)
    {
        $result = $this->access->authorizePrivilegedAction($action, [
            'actor' => $actor,
            'reason' => $reason,
            'permission' => $permission,
            'confirm' => $confirm,
        ]);

        if (!$result['ok']) {
            $this->audit->record($operator, 'privileged_action.denied', 'privileged_action', $action, $actor ?: 'unknown', $reason ?: null, [
                'action' => $action,
                'code' => $result['code'],
                'required_permission' => isset($result['required_permission']) ? $result['required_permission'] : null,
                'required_confirmation' => isset($result['required_confirmation']) ? $result['required_confirmation'] : null,
                'provided_permission' => $permission,
            ]);
        }

        return $result;
    }
}
