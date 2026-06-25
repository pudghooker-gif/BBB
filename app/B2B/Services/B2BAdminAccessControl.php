<?php

namespace VanguardLTE\B2B\Services;

class B2BAdminAccessControl
{
    public function permissionCatalog()
    {
        return config('b2b_admin.permissions', []);
    }

    public function action($action)
    {
        $actions = config('b2b_admin.privileged_actions', []);

        return isset($actions[$action]) ? $actions[$action] : null;
    }

    public function authorizePrivilegedAction($action, array $context)
    {
        $definition = $this->action($action);
        if (!$definition) {
            return $this->deny('unknown_action', 'Unknown B2B privileged action.');
        }

        $actor = trim((string) (isset($context['actor']) ? $context['actor'] : ''));
        if ($actor === '') {
            return $this->deny('actor_required', 'B2B privileged actions require --actor.');
        }

        $reason = trim((string) (isset($context['reason']) ? $context['reason'] : ''));
        if ($reason === '') {
            return $this->deny('reason_required', 'B2B privileged actions require --reason.');
        }

        $requiredPermission = isset($definition['permission']) ? $definition['permission'] : null;
        $providedPermission = trim((string) (isset($context['permission']) ? $context['permission'] : ''));
        if (!$requiredPermission || $providedPermission !== $requiredPermission) {
            return $this->deny('permission_required', 'B2B privileged action requires permission: ' . $requiredPermission, [
                'required_permission' => $requiredPermission,
            ]);
        }

        if (!empty($definition['step_up'])) {
            $requiredConfirmation = isset($definition['confirm']) ? $definition['confirm'] : null;
            $providedConfirmation = trim((string) (isset($context['confirm']) ? $context['confirm'] : ''));
            if (!$requiredConfirmation || $providedConfirmation !== $requiredConfirmation) {
                return $this->deny('step_up_required', 'B2B privileged action requires confirmation: ' . $requiredConfirmation, [
                    'required_confirmation' => $requiredConfirmation,
                    'required_permission' => $requiredPermission,
                ]);
            }
        }

        return [
            'ok' => true,
            'permission' => $requiredPermission,
            'step_up' => !empty($definition['step_up']),
            'confirm' => isset($definition['confirm']) ? $definition['confirm'] : null,
        ];
    }

    public function userHasPermission($user, $permission)
    {
        if (!$user || !$permission) {
            return false;
        }

        if (method_exists($user, 'hasPermission')) {
            try {
                if ($user->hasPermission($permission)) {
                    return true;
                }
            } catch (\Exception $e) {
                // Fall through to role-map checks below.
            }
        }

        foreach ($this->userRoleSlugs($user) as $roleSlug) {
            if ($this->roleGrantsPermission($roleSlug, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function roleGrantsPermission($roleSlug, $permission)
    {
        $roles = config('b2b_admin.roles', []);
        $roleSlug = strtolower((string) $roleSlug);

        if (!isset($roles[$roleSlug])) {
            return false;
        }

        $permissions = isset($roles[$roleSlug]['permissions']) ? $roles[$roleSlug]['permissions'] : [];

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    private function userRoleSlugs($user)
    {
        $slugs = [];

        if (isset($user->role) && $user->role) {
            if (isset($user->role->slug)) {
                $slugs[] = $user->role->slug;
            }
            if (isset($user->role->name)) {
                $slugs[] = $user->role->name;
            }
        }

        if (isset($user->roles) && is_iterable($user->roles)) {
            foreach ($user->roles as $role) {
                if (isset($role->slug)) {
                    $slugs[] = $role->slug;
                }
                if (isset($role->name)) {
                    $slugs[] = $role->name;
                }
            }
        }

        $normalized = [];
        foreach ($slugs as $slug) {
            $normalized[] = strtolower(str_replace([' ', '-'], '_', (string) $slug));
        }

        return array_values(array_unique($normalized));
    }

    private function deny($code, $message, array $meta = [])
    {
        return array_merge([
            'ok' => false,
            'code' => $code,
            'message' => $message,
        ], $meta);
    }
}
