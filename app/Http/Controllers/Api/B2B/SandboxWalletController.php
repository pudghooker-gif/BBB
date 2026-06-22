<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use VanguardLTE\B2B\Support\B2BApiResponse;
use VanguardLTE\B2B\Services\SandboxWalletService;

class SandboxWalletController extends Controller
{
    protected $wallet;

    public function __construct(SandboxWalletService $wallet)
    {
        $this->wallet = $wallet;
    }

    public function health(Request $request)
    {
        return B2BApiResponse::success($request, [
            'service' => 'b2b-sandbox-wallet',
            'enabled' => $this->wallet->isEnabled(),
            'tables' => $this->wallet->tableStatus(),
            'time' => now()->toIso8601String(),
        ]);
    }

    public function handle(Request $request)
    {
        $payload = $request->all();
        $action = isset($payload['action']) ? $payload['action'] : 'balance';
        return $this->respond($request, $action, $payload);
    }

    public function action(Request $request, $action)
    {
        return $this->respond($request, $action, $request->all());
    }

    protected function respond(Request $request, $action, array $payload)
    {
        $operator = $this->resolveOperator($request, $payload);
        $result = $this->wallet->process($operator, $action, $payload);

        if (!$result['ok']) {
            $body = isset($result['body']) && is_array($result['body']) ? $result['body'] : [];
            return B2BApiResponse::error(
                $request,
                isset($body['code']) ? $body['code'] : 'SANDBOX_WALLET_FAILED',
                isset($body['message']) ? $body['message'] : null,
                $result['http_status'],
                isset($body['details']) ? $body['details'] : null
            );
        }

        return B2BApiResponse::success($request, $result['body'], $result['http_status']);
    }

    protected function resolveOperator(Request $request, array $payload)
    {
        if (!Schema::hasTable('b2b_operators')) {
            return null;
        }

        $operatorUid = $request->query('operator_uid');
        if (!$operatorUid) {
            $operatorUid = $request->header('X-Operator-Id');
        }
        if (!$operatorUid && isset($payload['operator_uid'])) {
            $operatorUid = $payload['operator_uid'];
        }

        if ($operatorUid) {
            return DB::table('b2b_operators')->where('operator_uid', $operatorUid)->first();
        }

        if (isset($payload['operator_id'])) {
            return DB::table('b2b_operators')->where('id', (int) $payload['operator_id'])->first();
        }

        return null;
    }
}
