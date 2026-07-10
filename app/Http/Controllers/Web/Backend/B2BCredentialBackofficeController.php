<?php

namespace VanguardLTE\Http\Controllers\Web\Backend;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use VanguardLTE\B2B\Services\B2BApiCredentialLifecycleService;
use VanguardLTE\B2B\Services\B2BWebStepUpGuard;
use VanguardLTE\Http\Controllers\Controller;

class B2BCredentialBackofficeController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', '2fa', 'only_for_admin']);
        $this->middleware('permission:access.admin.panel');
    }

    public function index()
    {
        return $this->view();
    }

    public function redirectToIndex()
    {
        return redirect()->route('backend.b2b.credentials.index');
    }

    public function rotate(Request $request, B2BApiCredentialLifecycleService $credentials, B2BWebStepUpGuard $stepUp)
    {
        $this->validate($request, [
            'operator_uid' => 'required|string|max:80',
            'key_id' => 'nullable|string|max:80',
            'max_rps' => 'nullable|integer|min:1',
            'scopes' => 'nullable|string|max:1000',
            'reason' => 'required|string|max:1000',
            'revoke_existing' => 'nullable|boolean',
        ]);

        try {
            $rotated = $credentials->rotate(
                $request->input('operator_uid'),
                $this->actor($request),
                $request->input('reason'),
                $this->context($request, 'b2b.credentials.rotate'),
                $request->input('key_id'),
                $request->input('max_rps'),
                (bool) $request->input('revoke_existing'),
                $request->input('scopes')
            );
        } catch (InvalidArgumentException $e) {
            return $this->failed($e->getMessage());
        } catch (RuntimeException $e) {
            return $this->failed($e->getMessage());
        }

        $stepUp->forget($request, 'api_key.rotate');

        return $this->view([
            'success' => 'B2B API key rotated. Save this secret now; it is not stored in plaintext.',
            'rotatedCredential' => $rotated,
        ]);
    }

    public function revoke(Request $request, B2BApiCredentialLifecycleService $credentials, B2BWebStepUpGuard $stepUp)
    {
        $this->validate($request, [
            'operator_uid' => 'required|string|max:80',
            'key_id' => 'required|string|max:80',
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $revoked = $credentials->revoke(
                $request->input('operator_uid'),
                $request->input('key_id'),
                $this->actor($request),
                $request->input('reason'),
                $this->context($request, 'b2b.credentials.revoke')
            );
        } catch (InvalidArgumentException $e) {
            return $this->failed($e->getMessage());
        } catch (RuntimeException $e) {
            return $this->failed($e->getMessage());
        }

        $stepUp->forget($request, 'api_key.revoke');

        return redirect()
            ->route('backend.b2b.credentials.index')
            ->with('success', 'B2B API key ' . $revoked['key_id'] . ' revoked for ' . $revoked['operator_uid'] . '.');
    }

    private function view(array $data = [])
    {
        return view('backend.b2b.credentials', array_merge([
            'operators' => $this->operators(),
            'apiKeys' => $this->apiKeys(),
            'success' => null,
            'rotatedCredential' => null,
        ], $data));
    }

    private function failed($message)
    {
        return redirect()
            ->route('backend.b2b.credentials.index')
            ->withErrors(['credential_workflow' => $message])
            ->withInput();
    }

    private function operators()
    {
        if (!Schema::hasTable('b2b_operators')) {
            return collect();
        }

        return DB::table('b2b_operators')
            ->select('id', 'operator_uid', 'name', 'status', 'default_currency', 'max_rps')
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get();
    }

    private function apiKeys()
    {
        if (!Schema::hasTable('b2b_operator_api_keys')) {
            return collect();
        }

        $query = DB::table('b2b_operator_api_keys')
            ->select('operator_id', 'key_id', 'status', 'max_rps', 'last_used_at', 'created_at')
            ->orderBy('id', 'desc')
            ->limit(100);

        if (Schema::hasColumn('b2b_operator_api_keys', 'scopes')) {
            $query->addSelect('scopes');
        }

        return $query->get()->map(function ($apiKey) {
            $apiKey->scope_list = $this->formatScopes(isset($apiKey->scopes) ? $apiKey->scopes : null);

            return $apiKey;
        });
    }

    private function formatScopes($scopes)
    {
        if ($scopes === null || $scopes === '') {
            return 'none';
        }

        if (is_string($scopes)) {
            $decoded = json_decode($scopes, true);
            $scopes = is_array($decoded) ? $decoded : preg_split('/[\s,]+/', $scopes);
        }

        if (!is_array($scopes) || count($scopes) === 0) {
            return 'none';
        }

        return implode(', ', array_filter(array_map('strval', $scopes)));
    }

    private function context(Request $request, $permission)
    {
        return [
            'permission' => $permission,
            'step_up' => true,
            'source' => 'web_backoffice',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ];
    }

    private function actor(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return 'web:unknown';
        }

        foreach (['username', 'email'] as $field) {
            if (isset($user->{$field}) && trim((string) $user->{$field}) !== '') {
                return 'web:' . trim((string) $user->{$field});
            }
        }

        if (method_exists($user, 'getAuthIdentifier')) {
            return 'web:user:' . $user->getAuthIdentifier();
        }

        return 'web:user';
    }
}
