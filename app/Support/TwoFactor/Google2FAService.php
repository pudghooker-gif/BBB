<?php

namespace VanguardLTE\Support\TwoFactor;

use BaconQrCode\Renderer\Image\EpsImageBackEnd;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PragmaRX\Google2FAQRCode\Google2FA as BaseGoogle2FA;
use Throwable;

class Google2FAService extends BaseGoogle2FA
{
    public const OTP_MISSING = 'missing';
    public const OTP_EMPTY = 'empty';
    public const OTP_VALID = 'valid';
    public const OTP_INVALID = 'invalid';

    private const SESSION_AUTH_PASSED = 'auth_passed';
    private const SESSION_AUTH_TIME = 'auth_time';
    private const SESSION_OTP_TIMESTAMP = 'otp_timestamp';

    public function __construct()
    {
        parent::__construct($this->makeImageBackend());
    }

    public function getQRCodeInline($company, $holder, $secret, $size = 200, $encoding = 'utf-8')
    {
        $inline = parent::getQRCodeInline($company, $holder, $secret, $size, $encoding);

        return $this->asDataUri($inline);
    }

    public function verifyGoogle2FA($secret, $oneTimePassword)
    {
        try {
            return $this->verifyKey(
                $secret,
                $oneTimePassword,
                config('google2fa.window', 1),
                null,
                $this->getOldOtpTimestamp()
            );
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public function canPassWithoutChallenge(Request $request)
    {
        return !config('google2fa.enabled', true)
            || !$this->currentUser()
            || !$this->userHasEnabledTwoFactor($this->currentUser())
            || $this->sessionStillValid($request);
    }

    public function checkRequest(Request $request)
    {
        $input = config('google2fa.otp_input', 'one_time_password');

        if (!$request->has($input)) {
            return self::OTP_MISSING;
        }

        $oneTimePassword = (string) $request->input($input, '');

        if ($oneTimePassword === '') {
            return self::OTP_EMPTY;
        }

        $user = $this->currentUser();
        $secretColumn = config('google2fa.otp_secret_column', 'google2fa_secret');
        $verifiedAt = $this->verifyGoogle2FA($user->{$secretColumn}, $oneTimePassword);

        if (!$verifiedAt) {
            return self::OTP_INVALID;
        }

        $this->storeOldOtpTimestamp($request, $verifiedAt);
        $this->login($request);

        return self::OTP_VALID;
    }

    public function makeChallengeResponse(Request $request, $status)
    {
        $statusCode = $this->statusCodeFor($request, $status);

        if ($request->expectsJson()) {
            return response()->json($this->errorBagFor($status), $statusCode);
        }

        $view = view(config('google2fa.view', 'backend.google2fa.index'));

        if ($statusCode !== 200) {
            $view->withErrors($this->errorBagFor($status));
        }

        return response($view, $statusCode);
    }

    public function login(Request $request = null)
    {
        $session = $this->session($request);

        $session->put($this->sessionKey(self::SESSION_AUTH_PASSED), true);
        $session->put($this->sessionKey(self::SESSION_AUTH_TIME), Carbon::now()->toIso8601String());
    }

    public function logout(Request $request = null)
    {
        $this->session($request)->forget($this->sessionRoot());
    }

    private function makeImageBackend()
    {
        $backend = strtolower((string) config('google2fa.qrcode_image_backend', 'imagemagick'));

        try {
            if ($backend === 'svg') {
                return new SvgImageBackEnd();
            }

            if ($backend === 'eps') {
                return new EpsImageBackEnd();
            }

            if (class_exists(\Imagick::class)) {
                return new ImagickImageBackEnd();
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return new SvgImageBackEnd();
    }

    private function asDataUri($inline)
    {
        if (strpos($inline, 'data:image/') === 0) {
            return $inline;
        }

        $trimmed = ltrim($inline);

        if (strpos($trimmed, '<svg') === 0 || strpos($trimmed, '<?xml') === 0) {
            return 'data:image/svg+xml;base64,' . base64_encode($inline);
        }

        return $inline;
    }

    private function currentUser()
    {
        $auth = app(config('google2fa.auth', 'auth'));
        $guard = config('google2fa.guard', '');

        if ($guard !== '') {
            $auth = $auth->guard($guard);
        }

        return $auth->user();
    }

    private function userHasEnabledTwoFactor($user)
    {
        $secretColumn = config('google2fa.otp_secret_column', 'google2fa_secret');
        $secret = $user->{$secretColumn} ?? null;

        if ($secret === null || $secret === '') {
            return false;
        }

        if (isset($user->google2fa_enable) && !$user->google2fa_enable) {
            return false;
        }

        return true;
    }

    private function sessionStillValid(Request $request)
    {
        $session = $this->session($request);

        if (!$session->get($this->sessionKey(self::SESSION_AUTH_PASSED), false)) {
            return false;
        }

        if ($this->sessionExpired($request)) {
            $this->logout($request);

            return false;
        }

        if (config('google2fa.keep_alive', true)) {
            $session->put($this->sessionKey(self::SESSION_AUTH_TIME), Carbon::now()->toIso8601String());
        }

        return true;
    }

    private function sessionExpired(Request $request)
    {
        $lifetime = (int) config('google2fa.lifetime', 0);

        if ($lifetime === 0) {
            return false;
        }

        $authenticatedAt = $this->session($request)->get($this->sessionKey(self::SESSION_AUTH_TIME));

        if (!$authenticatedAt) {
            return true;
        }

        return Carbon::now()->diffInMinutes(Carbon::parse($authenticatedAt)) > $lifetime;
    }

    private function storeOldOtpTimestamp(Request $request, $verifiedAt)
    {
        if (config('google2fa.forbid_old_passwords', false) === true) {
            $timestamp = is_int($verifiedAt) ? $verifiedAt : $this->getTimestamp();

            $this->session($request)->put($this->sessionKey(self::SESSION_OTP_TIMESTAMP), $timestamp);
        }
    }

    private function getOldOtpTimestamp()
    {
        if (config('google2fa.forbid_old_passwords', false) !== true) {
            return null;
        }

        return $this->session()->get($this->sessionKey(self::SESSION_OTP_TIMESTAMP));
    }

    private function statusCodeFor(Request $request, $status)
    {
        if ($request->isMethod('get')) {
            return 200;
        }

        if ($status === self::OTP_EMPTY || $status === self::OTP_MISSING) {
            return 400;
        }

        return 422;
    }

    private function errorBagFor($status)
    {
        $messages = config('google2fa.error_messages', []);

        if ($status === self::OTP_EMPTY || $status === self::OTP_MISSING) {
            return ['one_time_password' => $messages['cannot_be_empty'] ?? 'One Time Password cannot be empty.'];
        }

        if ($status === self::OTP_INVALID) {
            return ['one_time_password' => $messages['wrong_otp'] ?? "The 'One Time Password' typed was wrong."];
        }

        return ['one_time_password' => $messages['unknown'] ?? 'An unknown error has occurred. Please try again.'];
    }

    private function session(Request $request = null)
    {
        $request = $request ?: request();

        if ($request->hasSession()) {
            return $request->session();
        }

        return app('session.store');
    }

    private function sessionRoot()
    {
        return config('google2fa.session_var', 'google2fa');
    }

    private function sessionKey($key)
    {
        return $this->sessionRoot() . '.' . $key;
    }
}
