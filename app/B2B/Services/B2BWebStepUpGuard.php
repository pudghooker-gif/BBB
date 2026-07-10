<?php

namespace VanguardLTE\B2B\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class B2BWebStepUpGuard
{
    private $access;

    public function __construct(B2BAdminAccessControl $access)
    {
        $this->access = $access;
    }

    public function requirement(Request $request, $action)
    {
        return $this->baseAuthorization($request->user(), $action);
    }

    public function confirm(Request $request, $action, $confirmation, $currentPassword = null)
    {
        $result = $this->baseAuthorization($request->user(), $action);
        if (!$result['ok']) {
            return $result;
        }

        if (empty($result['step_up'])) {
            return $result;
        }

        $requiredConfirmation = isset($result['confirm']) ? (string) $result['confirm'] : '';
        $providedConfirmation = trim((string) $confirmation);
        if ($requiredConfirmation === '' || !hash_equals($requiredConfirmation, $providedConfirmation)) {
            return $this->deny('step_up_required', 'B2B web action requires confirmation: ' . $requiredConfirmation, [
                'required_confirmation' => $requiredConfirmation,
                'required_permission' => $result['permission'],
            ]);
        }

        if ($this->passwordRequired() && !$this->passwordMatches($request->user(), $currentPassword)) {
            return $this->deny('current_password_required', 'B2B web step-up requires the current account password.', [
                'required_confirmation' => $requiredConfirmation,
                'required_permission' => $result['permission'],
            ]);
        }

        $session = $this->session($request);
        if (!$session) {
            return $this->deny('session_required', 'B2B web step-up requires an authenticated session.');
        }

        $userId = $this->userIdentifier($request->user());
        if ($userId === null || $userId === '') {
            return $this->deny('user_identifier_required', 'B2B web step-up requires a persistent authenticated user id.');
        }

        $verifiedAt = time();
        $payload = [
            'user_id' => $userId,
            'verified_at' => $verifiedAt,
        ];
        if ($this->passwordRequired()) {
            $payload['password_verified_at'] = $verifiedAt;
        }

        $session->put($this->sessionKey($action), $payload);

        return array_merge($result, [
            'verified_at' => $verifiedAt,
            'password_verified_at' => isset($payload['password_verified_at']) ? $payload['password_verified_at'] : null,
            'expires_at' => $verifiedAt + $this->ttlSeconds(),
        ]);
    }

    public function authorize(Request $request, $action)
    {
        $result = $this->baseAuthorization($request->user(), $action);
        if (!$result['ok']) {
            return $result;
        }

        if (empty($result['step_up'])) {
            return $result;
        }

        $session = $this->session($request);
        if (!$session) {
            return $this->deny('session_required', 'B2B web step-up requires an authenticated session.');
        }

        $payload = $session->get($this->sessionKey($action));
        if (!is_array($payload) || empty($payload['verified_at'])) {
            return $this->deny('step_up_required', 'B2B web action requires a fresh step-up confirmation.', [
                'required_confirmation' => $result['confirm'],
                'required_permission' => $result['permission'],
            ]);
        }

        $userId = $this->userIdentifier($request->user());
        $payloadUserId = isset($payload['user_id']) ? (string) $payload['user_id'] : null;
        if ($userId === null || $userId === '' || $payloadUserId !== $userId) {
            return $this->deny('step_up_user_mismatch', 'B2B web step-up confirmation belongs to another user.');
        }

        if ($this->passwordRequired() && empty($payload['password_verified_at'])) {
            $session->forget($this->sessionKey($action));

            return $this->deny('step_up_password_required', 'B2B web step-up requires a current-password verification.', [
                'required_confirmation' => $result['confirm'],
                'required_permission' => $result['permission'],
            ]);
        }

        $verifiedAt = (int) $payload['verified_at'];
        $expiresAt = $verifiedAt + $this->ttlSeconds();
        if ($expiresAt < time()) {
            $session->forget($this->sessionKey($action));

            return $this->deny('step_up_expired', 'B2B web step-up confirmation has expired.', [
                'required_confirmation' => $result['confirm'],
                'required_permission' => $result['permission'],
            ]);
        }

        return array_merge($result, [
            'verified_at' => $verifiedAt,
            'password_verified_at' => isset($payload['password_verified_at']) ? (int) $payload['password_verified_at'] : null,
            'expires_at' => $expiresAt,
        ]);
    }

    public function forget(Request $request, $action = null)
    {
        $session = $this->session($request);
        if (!$session) {
            return;
        }

        if ($action === null) {
            $session->forget('b2b_web_step_up');
            return;
        }

        $session->forget($this->sessionKey($action));
    }

    public function sessionKey($action)
    {
        return 'b2b_web_step_up.' . str_replace(['/', '\\'], '_', (string) $action);
    }

    public function ttlSeconds()
    {
        $ttl = (int) config('b2b_admin.web_step_up_ttl_seconds', 300);

        return $ttl > 0 ? $ttl : 300;
    }

    public function passwordRequired()
    {
        return (bool) config('b2b_admin.web_step_up_requires_password', true);
    }

    private function baseAuthorization($user, $action)
    {
        $definition = $this->access->action($action);
        if (!$definition) {
            return $this->deny('unknown_action', 'Unknown B2B privileged action.');
        }

        if (!$user) {
            return $this->deny('user_required', 'B2B web privileged actions require an authenticated user.');
        }

        $requiredPermission = isset($definition['permission']) ? $definition['permission'] : null;
        if (!$this->access->userHasPermission($user, $requiredPermission)) {
            return $this->deny('permission_required', 'B2B web privileged action requires permission: ' . $requiredPermission, [
                'required_permission' => $requiredPermission,
            ]);
        }

        return [
            'ok' => true,
            'permission' => $requiredPermission,
            'step_up' => !empty($definition['step_up']),
            'confirm' => isset($definition['confirm']) ? $definition['confirm'] : null,
        ];
    }

    private function session(Request $request)
    {
        try {
            return $request->session();
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    private function userIdentifier($user)
    {
        if (!$user) {
            return null;
        }

        if (method_exists($user, 'getAuthIdentifier')) {
            return (string) $user->getAuthIdentifier();
        }

        if (isset($user->id)) {
            return (string) $user->id;
        }

        return null;
    }

    private function passwordMatches($user, $currentPassword)
    {
        $currentPassword = (string) $currentPassword;
        if (!$user || $currentPassword === '') {
            return false;
        }

        $hash = null;
        if (method_exists($user, 'getAuthPassword')) {
            $hash = $user->getAuthPassword();
        } elseif (isset($user->password)) {
            $hash = $user->password;
        }

        if (!is_string($hash) || $hash === '') {
            return false;
        }

        return Hash::check($currentPassword, $hash);
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
