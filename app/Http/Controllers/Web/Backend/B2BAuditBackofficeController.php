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

    public function export(Request $request, B2BAuditTrailService $audit)
    {
        $filters = $this->filters($request);
        $limit = $this->limit($request, 1000);
        $events = $audit->events($filters, $limit, 1000);
        $csv = $this->csv($events);
        $filename = 'b2b-audit-trail-' . now()->format('Ymd-His') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
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

    private function limit(Request $request, $max = 200)
    {
        $limit = (int) $request->query('limit', 100);
        if ($limit < 1) {
            return 100;
        }

        return min($limit, (int) $max);
    }

    private function csv($events)
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [
            'id',
            'created_at',
            'operator_uid',
            'operator_name',
            'operator_id',
            'event_type',
            'subject_type',
            'subject_id',
            'actor',
            'reason',
            'ip_address',
            'user_agent',
            'metadata',
        ]);

        foreach ($events as $event) {
            fputcsv($handle, [
                isset($event->id) ? $event->id : '',
                isset($event->created_at) ? $event->created_at : '',
                isset($event->operator_uid) ? $event->operator_uid : '',
                isset($event->operator_name) ? $event->operator_name : '',
                isset($event->operator_id) ? $event->operator_id : '',
                isset($event->event_type) ? $event->event_type : '',
                isset($event->subject_type) ? $event->subject_type : '',
                isset($event->subject_id) ? $event->subject_id : '',
                isset($event->actor) ? $event->actor : '',
                isset($event->reason_display) ? $event->reason_display : '',
                isset($event->ip_address) ? $event->ip_address : '',
                isset($event->user_agent) ? $event->user_agent : '',
                isset($event->metadata_display) ? $event->metadata_display : '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }
}
