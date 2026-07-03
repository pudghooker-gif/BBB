<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use VanguardLTE\Http\Controllers\Controller;
use VanguardLTE\B2B\Services\B2BContext;
use VanguardLTE\B2B\Support\B2BApiResponse;

class WalletAttemptController extends Controller
{
    private const DEFAULT_LIMIT = 100;
    private const MAX_LIMIT = 100;

    public function index(Request $request, $transaction_uid)
    {
        if (!Schema::hasTable('b2b_wallet_transaction_attempts')) {
            return B2BApiResponse::error($request, 'ATTEMPTS_TABLE_MISSING');
        }

        $operator = B2BContext::operator($request);
        if (!$operator || !isset($operator->id)) {
            return B2BApiResponse::error($request, 'OPERATOR_CONTEXT_MISSING');
        }

        $filters = $this->validatedFilters($request, $transaction_uid);
        if (isset($filters['response'])) {
            return $filters['response'];
        }

        $query = DB::table('b2b_wallet_transaction_attempts')
            ->where('transaction_uid', $filters['transaction_uid'])
            ->orderBy('id', 'desc');

        $query->where('operator_id', $operator->id);
        $rows = $query->limit($filters['limit'])->get();

        return B2BApiResponse::success($request, $rows, 200, [
            'transaction_uid' => $filters['transaction_uid'],
            'limit' => $filters['limit'],
            'count' => $rows->count(),
        ]);
    }

    private function validatedFilters(Request $request, $transactionUid)
    {
        $validator = Validator::make(array_merge($request->query(), [
            'transaction_uid' => $transactionUid,
        ]), [
            'transaction_uid' => 'required|string|min:1|max:191',
            'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
        ]);

        if ($validator->fails()) {
            return [
                'response' => B2BApiResponse::error(
                    $request,
                    'VALIDATION_FAILED',
                    null,
                    422,
                    $validator->errors()
                ),
            ];
        }

        $limit = $request->query('limit');

        return [
            'transaction_uid' => (string) $transactionUid,
            'limit' => $limit === null || $limit === '' ? self::DEFAULT_LIMIT : (int) $limit,
        ];
    }
}
