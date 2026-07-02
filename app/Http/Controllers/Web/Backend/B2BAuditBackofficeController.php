<?php

namespace VanguardLTE\Http\Controllers\Web\Backend;

use Illuminate\Http\Request;
use VanguardLTE\B2B\Services\B2BAuditTrailService;
use VanguardLTE\Http\Controllers\Controller;

class B2BAuditBackofficeController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', '2fa', 'only_for_admin']);
        $this->middleware('permission:access.admin.panel');
    }

    public function index(Request $request, B2BAuditTrailService $audit)
    {
        $filters = $this->filters($request);
        $limit = $this->limit($request);

        return view('backend.b2b.audit', [
            'events' => $audit->events($filters, $limit),
            'filters' => $filters,
            'limit' => $limit,
        ]);
    }

    private function filters(Request $request)
    {
        $filters = [];
        foreach (['operator_uid', 'operator_id', 'event_type', 'subject_type', 'subject_id', 'actor', 'from', 'to'] as $field) {
            $value = trim((string) $request->query($field, ''));
            $filters[$field] = substr($value, 0, 200);
        }

        return $filters;
    }

    private function limit(Request $request)
    {
        $limit = (int) $request->query('limit', 100);
        if ($limit < 1) {
            return 100;
        }

        return min($limit, 200);
    }
}
