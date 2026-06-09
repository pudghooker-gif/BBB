<?php

namespace VanguardLTE\B2B\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class B2BContext
{
    public static function operator(Request $request)
    {
        $operator = $request->attributes->get('b2b_operator');
        if ($operator) {
            return $operator;
        }

        $operatorId = $request->header('X-Operator-Id');
        if (!$operatorId) {
            return null;
        }

        if (is_numeric($operatorId)) {
            $operator = DB::table('b2b_operators')->where('id', (int) $operatorId)->first();
        } else {
            $operator = DB::table('b2b_operators')
                ->where('operator_uid', $operatorId)
                ->orWhere('uid', $operatorId)
                ->orWhere('code', $operatorId)
                ->first();
        }

        if ($operator) {
            $request->attributes->set('b2b_operator', $operator);
        }

        return $operator;
    }

    public static function operatorId($operator)
    {
        return $operator && isset($operator->id) ? (int) $operator->id : null;
    }
}
