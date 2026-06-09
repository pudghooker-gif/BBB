<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use VanguardLTE\B2B\Services\B2BContext;
use VanguardLTE\B2B\Services\SandboxWalletService;

class SandboxController extends Controller
{
    protected $wallet;

    public function __construct(SandboxWalletService $wallet)
    {
        $this->wallet = $wallet;
    }

    public function wallet(Request $request, $playerId)
    {
        $operator = B2BContext::operator($request);
        $currency = strtoupper((string) $request->query('currency', isset($operator->default_currency) ? $operator->default_currency : 'USD'));
        $wallet = $this->wallet->getWallet($operator->id, $playerId, $currency);

        if (!$wallet) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SANDBOX_WALLET_NOT_FOUND',
                    'message' => 'Create it with POST /api/b2b/v1/sandbox/wallet/{player_id}/credit or php artisan b2b:sandbox-wallet.',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'player_id' => $wallet->external_player_id,
                'currency' => $wallet->currency,
                'balance' => (float) $wallet->balance,
                'status' => $wallet->status,
                'last_transaction_at' => $wallet->last_transaction_at ? $wallet->last_transaction_at->toIso8601String() : null,
            ],
        ]);
    }

    public function credit(Request $request, $playerId)
    {
        return $this->manualAction($request, $playerId, 'credit');
    }

    public function debit(Request $request, $playerId)
    {
        return $this->manualAction($request, $playerId, 'debit');
    }

    public function entries(Request $request, $playerId)
    {
        $operator = B2BContext::operator($request);
        $currency = strtoupper((string) $request->query('currency', isset($operator->default_currency) ? $operator->default_currency : 'USD'));
        $wallet = $this->wallet->getWallet($operator->id, $playerId, $currency);

        if (!$wallet) {
            return response()->json(['success' => false, 'error' => ['code' => 'SANDBOX_WALLET_NOT_FOUND']], 404);
        }

        $limit = (int) $request->query('limit', 50);
        if ($limit < 1) {
            $limit = 50;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $entries = DB::table('b2b_sandbox_wallet_entries')
            ->where('wallet_id', $wallet->id)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'wallet' => [
                    'player_id' => $wallet->external_player_id,
                    'currency' => $wallet->currency,
                    'balance' => (float) $wallet->balance,
                ],
                'entries' => $entries,
            ],
        ]);
    }

    protected function manualAction(Request $request, $playerId, $action)
    {
        $operator = B2BContext::operator($request);
        $currency = strtoupper((string) $request->input('currency', isset($operator->default_currency) ? $operator->default_currency : 'USD'));
        $amount = (float) $request->input('amount', 0);

        if ($amount <= 0) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'AMOUNT_REQUIRED', 'message' => 'amount must be greater than zero.'],
            ], 422);
        }

        $this->wallet->ensureWallet($operator->id, $playerId, $currency, 0, ['created_by' => 'sandbox_api']);

        $payload = [
            'player_id' => $playerId,
            'currency' => $currency,
            'amount' => $amount,
            'transaction_id' => $request->input('transaction_id', 'manual_'.time().'_'.mt_rand(1000, 9999)),
            'round_id' => $request->input('round_id', 'manual'),
        ];

        $result = $this->wallet->process($operator, $action, $payload);
        return response()->json(['success' => $result['ok'], 'data' => $result['body']], $result['http_status']);
    }
}
