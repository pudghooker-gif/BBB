<?php

namespace VanguardLTE\B2B\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class B2BOperatorSupportTicketService
{
    private $redactor;
    private $audit;

    private $priorities = ['low', 'normal', 'high', 'urgent'];
    private $statuses = ['open', 'in_progress', 'closed'];

    public function __construct(B2BPayloadRedactor $redactor, B2BOperatorAuditLogger $audit)
    {
        $this->redactor = $redactor;
        $this->audit = $audit;
    }

    public function create($operator, array $input, array $context = [])
    {
        $this->assertTablesReady();
        $this->assertOperator($operator);

        $subject = $this->safeText(isset($input['subject']) ? $input['subject'] : null, 160);
        $message = $this->safeText(isset($input['message']) ? $input['message'] : null, 2000);
        if ($subject === '' || $message === '') {
            throw new InvalidArgumentException('Support ticket subject and message are required.');
        }

        $priority = $this->enumValue(isset($input['priority']) ? $input['priority'] : null, $this->priorities, 'normal');
        $category = $this->safeNullableText(isset($input['category']) ? $input['category'] : null, 80);
        $externalReference = $this->safeNullableText(isset($input['external_reference']) ? $input['external_reference'] : null, 120);

        return DB::transaction(function () use ($operator, $subject, $message, $priority, $category, $externalReference, $context) {
            $now = Carbon::now();
            $ticketUid = $this->uniqueTicketUid();
            $actor = 'operator:' . $operator->operator_uid;

            $ticketId = DB::table('b2b_operator_support_tickets')->insertGetId($this->filterColumns('b2b_operator_support_tickets', [
                'operator_id' => (int) $operator->id,
                'ticket_uid' => $ticketUid,
                'subject' => $subject,
                'status' => 'open',
                'priority' => $priority,
                'category' => $category,
                'external_reference' => $externalReference,
                'context' => $this->redactor->json($this->ticketContext('created', $actor, $subject, $context, $now)),
                'last_message_at' => $now,
                'closed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            $this->insertMessage($ticketId, (int) $operator->id, $actor, $message, [
                'action' => 'created',
                'request_id' => $this->contextValue($context, 'request_id'),
                'external_reference' => $externalReference,
            ], $now);

            $this->audit->record(
                (int) $operator->id,
                'support_ticket.created',
                'support_ticket',
                $ticketUid,
                $actor,
                $subject,
                [
                    'ticket_uid' => $ticketUid,
                    'status' => 'open',
                    'priority' => $priority,
                    'category' => $category,
                    'external_reference' => $externalReference,
                    'source' => 'operator_portal',
                    'request_id' => $this->contextValue($context, 'request_id'),
                ],
                $this->contextValue($context, 'ip_address'),
                $this->contextValue($context, 'user_agent')
            );

            return $this->ticketPayload($this->ticketById($ticketId));
        });
    }

    public function comment($operator, $ticketUid, $message, array $context = [])
    {
        $this->assertTablesReady();
        $this->assertOperator($operator);

        $message = $this->safeText($message, 2000);
        if ($message === '') {
            throw new InvalidArgumentException('Support ticket comment message is required.');
        }

        return DB::transaction(function () use ($operator, $ticketUid, $message, $context) {
            $ticket = $this->ownedTicket((int) $operator->id, $ticketUid, false);
            $now = Carbon::now();
            $actor = 'operator:' . $operator->operator_uid;
            $externalReference = $this->safeNullableText($this->contextValue($context, 'external_reference'), 120);

            $this->insertMessage($ticket->id, (int) $operator->id, $actor, $message, [
                'action' => 'operator_commented',
                'request_id' => $this->contextValue($context, 'request_id'),
                'external_reference' => $externalReference,
            ], $now);

            $this->updateTicket($ticket, [
                'status' => 'in_progress',
                'external_reference' => $externalReference ?: (isset($ticket->external_reference) ? $ticket->external_reference : null),
                'context' => $this->redactor->json($this->appendTicketEvent($ticket, 'operator_commented', $actor, $message, $context, $now)),
                'last_message_at' => $now,
                'updated_at' => $now,
            ]);

            $fresh = $this->ownedTicket((int) $operator->id, $ticketUid, true);

            $this->audit->record(
                (int) $operator->id,
                'support_ticket.operator_commented',
                'support_ticket',
                $fresh->ticket_uid,
                $actor,
                $message,
                [
                    'ticket_uid' => $fresh->ticket_uid,
                    'previous_status' => isset($ticket->status) ? $ticket->status : null,
                    'new_status' => isset($fresh->status) ? $fresh->status : null,
                    'priority' => isset($fresh->priority) ? $fresh->priority : null,
                    'external_reference' => $externalReference,
                    'source' => 'operator_portal',
                    'request_id' => $this->contextValue($context, 'request_id'),
                ],
                $this->contextValue($context, 'ip_address'),
                $this->contextValue($context, 'user_agent')
            );

            return $this->ticketPayload($fresh);
        });
    }

    public function close($operator, $ticketUid, $reason, array $context = [])
    {
        $this->assertTablesReady();
        $this->assertOperator($operator);

        $reason = $this->safeText($reason, 1000);
        if ($reason === '') {
            throw new InvalidArgumentException('Support ticket close reason is required.');
        }

        return DB::transaction(function () use ($operator, $ticketUid, $reason, $context) {
            $ticket = $this->ownedTicket((int) $operator->id, $ticketUid, false);
            $now = Carbon::now();
            $actor = 'operator:' . $operator->operator_uid;

            $this->insertMessage($ticket->id, (int) $operator->id, $actor, $reason, [
                'action' => 'closed',
                'request_id' => $this->contextValue($context, 'request_id'),
            ], $now);

            $this->updateTicket($ticket, [
                'status' => 'closed',
                'context' => $this->redactor->json($this->appendTicketEvent($ticket, 'closed', $actor, $reason, $context, $now)),
                'last_message_at' => $now,
                'closed_at' => $now,
                'updated_at' => $now,
            ]);

            $fresh = $this->ownedTicket((int) $operator->id, $ticketUid, true);

            $this->audit->record(
                (int) $operator->id,
                'support_ticket.closed',
                'support_ticket',
                $fresh->ticket_uid,
                $actor,
                $reason,
                [
                    'ticket_uid' => $fresh->ticket_uid,
                    'previous_status' => isset($ticket->status) ? $ticket->status : null,
                    'new_status' => 'closed',
                    'source' => 'operator_portal',
                    'request_id' => $this->contextValue($context, 'request_id'),
                ],
                $this->contextValue($context, 'ip_address'),
                $this->contextValue($context, 'user_agent')
            );

            return $this->ticketPayload($fresh);
        });
    }

    public function reopen($operator, $ticketUid, $reason, array $context = [])
    {
        $this->assertTablesReady();
        $this->assertOperator($operator);

        $reason = $this->safeText($reason, 1000);
        if ($reason === '') {
            throw new InvalidArgumentException('Support ticket reopen reason is required.');
        }

        return DB::transaction(function () use ($operator, $ticketUid, $reason, $context) {
            $ticket = $this->ownedTicket((int) $operator->id, $ticketUid, true);
            if ((isset($ticket->status) ? $ticket->status : null) !== 'closed') {
                throw new InvalidArgumentException('Support ticket is not closed.');
            }

            $now = Carbon::now();
            $actor = 'operator:' . $operator->operator_uid;

            $this->insertMessage($ticket->id, (int) $operator->id, $actor, $reason, [
                'action' => 'reopened',
                'request_id' => $this->contextValue($context, 'request_id'),
            ], $now);

            $this->updateTicket($ticket, [
                'status' => 'open',
                'context' => $this->redactor->json($this->appendTicketEvent($ticket, 'reopened', $actor, $reason, $context, $now)),
                'last_message_at' => $now,
                'closed_at' => null,
                'updated_at' => $now,
            ]);

            $fresh = $this->ownedTicket((int) $operator->id, $ticketUid, true);

            $this->audit->record(
                (int) $operator->id,
                'support_ticket.reopened',
                'support_ticket',
                $fresh->ticket_uid,
                $actor,
                $reason,
                [
                    'ticket_uid' => $fresh->ticket_uid,
                    'previous_status' => 'closed',
                    'new_status' => isset($fresh->status) ? $fresh->status : null,
                    'source' => 'operator_portal',
                    'request_id' => $this->contextValue($context, 'request_id'),
                ],
                $this->contextValue($context, 'ip_address'),
                $this->contextValue($context, 'user_agent')
            );

            return $this->ticketPayload($fresh);
        });
    }

    public function show($operator, $ticketUid, $limit = 50)
    {
        $this->assertTablesReady();
        $this->assertOperator($operator);

        $ticket = $this->ownedTicket((int) $operator->id, $ticketUid, true);
        $payload = $this->ticketPayload($ticket);
        $payload['latest_message'] = $this->latestTicketMessage($ticket->id, (int) $operator->id);
        $payload['messages'] = $this->ticketMessages($ticket->id, (int) $operator->id, $limit);

        return $payload;
    }

    public function backofficeTickets($limit = 50)
    {
        if (!Schema::hasTable('b2b_operator_support_tickets')) {
            return collect();
        }

        $messageCountSelect = Schema::hasTable('b2b_operator_support_ticket_messages')
            ? DB::raw('(select count(*) from b2b_operator_support_ticket_messages stm where stm.ticket_id = st.id) as message_count')
            : DB::raw('0 as message_count');

        $query = DB::table('b2b_operator_support_tickets as st')
            ->select(
                'st.id',
                'st.operator_id',
                'st.ticket_uid',
                'st.subject',
                'st.status',
                'st.priority',
                'st.category',
                'st.external_reference',
                'st.context',
                'st.last_message_at',
                'st.closed_at',
                'st.created_at',
                'st.updated_at',
                $messageCountSelect
            );

        if (Schema::hasTable('b2b_operators')) {
            $query->leftJoin('b2b_operators as op', 'op.id', '=', 'st.operator_id')
                ->addSelect('op.operator_uid as operator_uid', 'op.name as operator_name');
        }

        return $query
            ->orderByRaw("CASE st.status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE st.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderBy('st.last_message_at', 'desc')
            ->limit((int) $limit)
            ->get()
            ->map(function ($ticket) {
                $ticket->subject = $this->safeText(isset($ticket->subject) ? $ticket->subject : null, 160);
                $ticket->external_reference = $this->safeNullableText(isset($ticket->external_reference) ? $ticket->external_reference : null, 120);
                $ticket->context_display = $this->formatContext(isset($ticket->context) ? $ticket->context : null);

                return $ticket;
            });
    }

    public function backofficeTicketThread($ticketUid, $limit = 50)
    {
        $this->assertTablesReady();

        $ticket = $this->backofficeTicket($ticketUid);
        $payload = $this->ticketPayload($ticket);
        $payload['operator_id'] = isset($ticket->operator_id) ? (int) $ticket->operator_id : null;
        $payload['operator_uid'] = isset($ticket->operator_uid) ? $this->safeNullableText($ticket->operator_uid, 100) : null;
        $payload['operator_name'] = isset($ticket->operator_name) ? $this->safeNullableText($ticket->operator_name, 160) : null;
        $payload['context_display'] = $this->formatContext(isset($ticket->context) ? $ticket->context : null);
        $payload['latest_message'] = $this->latestTicketMessage($ticket->id, (int) $ticket->operator_id);
        $payload['messages'] = $this->ticketMessages($ticket->id, (int) $ticket->operator_id, $limit);

        return $payload;
    }

    public function staffComment($ticketUid, $actor, $message, array $context = [])
    {
        $this->assertTablesReady();

        $actor = $this->requiredText($actor, 'Support ticket action requires an actor.');
        $message = $this->safeText($message, 2000);
        if ($message === '') {
            throw new InvalidArgumentException('Support ticket staff comment message is required.');
        }

        return DB::transaction(function () use ($ticketUid, $actor, $message, $context) {
            $ticket = $this->staffTicket($ticketUid, ['open', 'in_progress']);
            $now = Carbon::now();

            $this->insertMessage($ticket->id, (int) $ticket->operator_id, $actor, $message, [
                'action' => 'staff_commented',
                'permission' => $this->contextValue($context, 'permission'),
                'step_up' => !empty($context['step_up']),
                'request_id' => $this->contextValue($context, 'request_id'),
            ], $now, 'web_backoffice');

            $this->updateTicket($ticket, [
                'status' => 'in_progress',
                'context' => $this->redactor->json($this->appendStaffTicketEvent($ticket, 'staff_commented', $actor, $message, $context, $now)),
                'last_message_at' => $now,
                'updated_at' => $now,
            ]);

            $fresh = $this->staffTicket($ticketUid, ['open', 'in_progress', 'closed']);
            $this->recordStaffAudit($fresh, 'support_ticket.staff_commented', 'staff_commented', $actor, $message, $context, [
                'previous_status' => isset($ticket->status) ? $ticket->status : null,
                'new_status' => isset($fresh->status) ? $fresh->status : null,
            ]);

            return $fresh;
        });
    }

    public function staffClose($ticketUid, $actor, $reason, array $context = [])
    {
        $this->assertTablesReady();

        $actor = $this->requiredText($actor, 'Support ticket action requires an actor.');
        $reason = $this->safeText($reason, 1000);
        if ($reason === '') {
            throw new InvalidArgumentException('Support ticket close reason is required.');
        }

        return DB::transaction(function () use ($ticketUid, $actor, $reason, $context) {
            $ticket = $this->staffTicket($ticketUid, ['open', 'in_progress']);
            $now = Carbon::now();

            $this->insertMessage($ticket->id, (int) $ticket->operator_id, $actor, $reason, [
                'action' => 'staff_closed',
                'permission' => $this->contextValue($context, 'permission'),
                'step_up' => !empty($context['step_up']),
                'request_id' => $this->contextValue($context, 'request_id'),
            ], $now, 'web_backoffice');

            $this->updateTicket($ticket, [
                'status' => 'closed',
                'context' => $this->redactor->json($this->appendStaffTicketEvent($ticket, 'staff_closed', $actor, $reason, $context, $now)),
                'last_message_at' => $now,
                'closed_at' => $now,
                'updated_at' => $now,
            ]);

            $fresh = $this->staffTicket($ticketUid, ['closed']);
            $this->recordStaffAudit($fresh, 'support_ticket.staff_closed', 'staff_closed', $actor, $reason, $context, [
                'previous_status' => isset($ticket->status) ? $ticket->status : null,
                'new_status' => 'closed',
            ]);

            return $fresh;
        });
    }

    public function staffReopen($ticketUid, $actor, $reason, array $context = [])
    {
        $this->assertTablesReady();

        $actor = $this->requiredText($actor, 'Support ticket action requires an actor.');
        $reason = $this->safeText($reason, 1000);
        if ($reason === '') {
            throw new InvalidArgumentException('Support ticket reopen reason is required.');
        }

        return DB::transaction(function () use ($ticketUid, $actor, $reason, $context) {
            $ticket = $this->staffTicket($ticketUid, ['closed']);
            $now = Carbon::now();

            $this->insertMessage($ticket->id, (int) $ticket->operator_id, $actor, $reason, [
                'action' => 'staff_reopened',
                'permission' => $this->contextValue($context, 'permission'),
                'step_up' => !empty($context['step_up']),
                'request_id' => $this->contextValue($context, 'request_id'),
            ], $now, 'web_backoffice');

            $this->updateTicket($ticket, [
                'status' => 'open',
                'context' => $this->redactor->json($this->appendStaffTicketEvent($ticket, 'staff_reopened', $actor, $reason, $context, $now)),
                'last_message_at' => $now,
                'closed_at' => null,
                'updated_at' => $now,
            ]);

            $fresh = $this->staffTicket($ticketUid, ['open', 'in_progress', 'closed']);
            $this->recordStaffAudit($fresh, 'support_ticket.staff_reopened', 'staff_reopened', $actor, $reason, $context, [
                'previous_status' => 'closed',
                'new_status' => isset($fresh->status) ? $fresh->status : null,
            ]);

            return $fresh;
        });
    }

    private function assertTablesReady()
    {
        foreach (['b2b_operator_support_tickets', 'b2b_operator_support_ticket_messages', 'b2b_operator_audit_events'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException('B2B support ticket tables are missing. Run: php artisan migrate');
            }
        }
    }

    private function assertOperator($operator)
    {
        if (!$operator || !isset($operator->id) || !isset($operator->operator_uid)) {
            throw new InvalidArgumentException('B2B operator context is missing.');
        }
    }

    private function ownedTicket($operatorId, $ticketUid, $includeClosed)
    {
        $ticketUid = trim((string) $ticketUid);
        if ($ticketUid === '') {
            throw new InvalidArgumentException('Support ticket UID is required.');
        }

        $query = DB::table('b2b_operator_support_tickets')
            ->where('operator_id', $operatorId)
            ->where('ticket_uid', $ticketUid);

        if (!$includeClosed) {
            $query->whereIn('status', ['open', 'in_progress']);
        }

        $ticket = $query->first();
        if (!$ticket) {
            throw new InvalidArgumentException('Support ticket was not found for this operator.');
        }

        return $ticket;
    }

    private function ticketById($ticketId)
    {
        return DB::table('b2b_operator_support_tickets')->where('id', (int) $ticketId)->first();
    }

    private function staffTicket($ticketUid, array $allowedStatuses)
    {
        $ticketUid = trim((string) $ticketUid);
        if ($ticketUid === '') {
            throw new InvalidArgumentException('Support ticket UID is required.');
        }

        $ticket = DB::table('b2b_operator_support_tickets')
            ->where('ticket_uid', $ticketUid)
            ->whereIn('status', $allowedStatuses)
            ->first();

        if (!$ticket) {
            throw new InvalidArgumentException('Support ticket was not found or is not in an allowed state.');
        }

        return $ticket;
    }

    private function backofficeTicket($ticketUid)
    {
        $ticketUid = trim((string) $ticketUid);
        if ($ticketUid === '') {
            throw new InvalidArgumentException('Support ticket UID is required.');
        }

        $query = DB::table('b2b_operator_support_tickets as st')
            ->where('st.ticket_uid', $ticketUid)
            ->select('st.*');

        if (Schema::hasTable('b2b_operators')) {
            $query->leftJoin('b2b_operators as op', 'op.id', '=', 'st.operator_id')
                ->addSelect('op.operator_uid as operator_uid', 'op.name as operator_name');
        }

        $ticket = $query->first();
        if (!$ticket) {
            throw new InvalidArgumentException('Support ticket was not found.');
        }

        return $ticket;
    }

    private function ticketPayload($ticket)
    {
        if (!$ticket) {
            return null;
        }

        $detailEndpoint = isset($ticket->ticket_uid) ? $this->supportTicketDetailEndpoint($ticket->ticket_uid) : null;
        $isOpen = in_array(isset($ticket->status) ? $ticket->status : null, ['open', 'in_progress'], true);
        $isClosed = (isset($ticket->status) ? $ticket->status : null) === 'closed';

        return [
            'ticket_uid' => $ticket->ticket_uid,
            'subject' => $this->safeText(isset($ticket->subject) ? $ticket->subject : null, 160),
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'category' => isset($ticket->category) ? $this->safeNullableText($ticket->category, 80) : null,
            'external_reference' => isset($ticket->external_reference) ? $this->safeNullableText($ticket->external_reference, 120) : null,
            'message_count' => $this->messageCount($ticket->id),
            'detail_endpoint' => $detailEndpoint,
            'thread_endpoint' => $detailEndpoint === null ? null : $detailEndpoint . '/thread',
            'comment_endpoint' => $isOpen && $detailEndpoint !== null ? $detailEndpoint . '/comments' : null,
            'close_endpoint' => $isOpen && $detailEndpoint !== null ? $detailEndpoint . '/close' : null,
            'reopen_endpoint' => $isClosed && $detailEndpoint !== null ? $detailEndpoint . '/reopen' : null,
            'last_message_at' => $this->isoTime(isset($ticket->last_message_at) ? $ticket->last_message_at : null),
            'closed_at' => $this->isoTime(isset($ticket->closed_at) ? $ticket->closed_at : null),
            'created_at' => $this->isoTime(isset($ticket->created_at) ? $ticket->created_at : null),
        ];
    }

    private function insertMessage($ticketId, $operatorId, $actor, $message, array $metadata, Carbon $now, $source = 'operator_portal')
    {
        DB::table('b2b_operator_support_ticket_messages')->insert($this->filterColumns('b2b_operator_support_ticket_messages', [
            'ticket_id' => (int) $ticketId,
            'operator_id' => (int) $operatorId,
            'actor' => $actor,
            'source' => $source,
            'message' => $message,
            'metadata' => $this->redactor->json($metadata),
            'created_at' => $now,
            'updated_at' => $now,
        ]));
    }

    private function updateTicket($ticket, array $updates)
    {
        DB::table('b2b_operator_support_tickets')
            ->where('id', $ticket->id)
            ->where('operator_id', $ticket->operator_id)
            ->update($this->filterColumns('b2b_operator_support_tickets', $updates));
    }

    private function ticketContext($action, $actor, $message, array $context, Carbon $now)
    {
        return [
            'ticket_events' => [[
                'action' => $action,
                'actor' => $actor,
                'message' => $message,
                'source' => 'operator_portal',
                'request_id' => $this->contextValue($context, 'request_id'),
                'at' => $now->toIso8601String(),
            ]],
            'ticket_state' => [
                'last_action' => $action,
                'last_actor' => $actor,
                'updated_at' => $now->toIso8601String(),
            ],
        ];
    }

    private function appendTicketEvent($ticket, $action, $actor, $message, array $context, Carbon $now)
    {
        $contextPayload = $this->decodeContext(isset($ticket->context) ? $ticket->context : null);
        $events = isset($contextPayload['ticket_events']) && is_array($contextPayload['ticket_events'])
            ? $contextPayload['ticket_events']
            : [];

        $events[] = [
            'action' => $action,
            'actor' => $actor,
            'message' => $message,
            'source' => 'operator_portal',
            'request_id' => $this->contextValue($context, 'request_id'),
            'at' => $now->toIso8601String(),
        ];

        $contextPayload['ticket_events'] = $events;
        $contextPayload['ticket_state'] = [
            'last_action' => $action,
            'last_actor' => $actor,
            'updated_at' => $now->toIso8601String(),
        ];

        if ($action === 'closed') {
            $contextPayload['ticket_closure'] = [
                'closed_by' => $actor,
                'closed_reason' => $message,
                'closed_at' => $now->toIso8601String(),
            ];
        }

        if ($action === 'reopened') {
            unset($contextPayload['ticket_closure']);
            $contextPayload['ticket_reopened'] = [
                'reopened_by' => $actor,
                'reopened_reason' => $message,
                'reopened_at' => $now->toIso8601String(),
                'source' => 'operator_portal',
            ];
        }

        return $contextPayload;
    }

    private function appendStaffTicketEvent($ticket, $action, $actor, $message, array $context, Carbon $now)
    {
        $contextPayload = $this->appendTicketEvent($ticket, $action, $actor, $message, $context, $now);
        $events = isset($contextPayload['ticket_events']) && is_array($contextPayload['ticket_events'])
            ? $contextPayload['ticket_events']
            : [];

        $lastIndex = count($events) - 1;
        if ($lastIndex >= 0) {
            $events[$lastIndex]['source'] = 'web_backoffice';
            $events[$lastIndex]['permission'] = $this->contextValue($context, 'permission');
            $events[$lastIndex]['step_up'] = !empty($context['step_up']);
        }

        $contextPayload['ticket_events'] = $events;
        $contextPayload['ticket_state']['source'] = 'web_backoffice';

        if ($action === 'staff_closed') {
            $contextPayload['ticket_closure'] = [
                'closed_by' => $actor,
                'closed_reason' => $message,
                'closed_at' => $now->toIso8601String(),
                'source' => 'web_backoffice',
            ];
        }

        if ($action === 'staff_reopened') {
            unset($contextPayload['ticket_closure']);
            $contextPayload['ticket_reopened'] = [
                'reopened_by' => $actor,
                'reopened_reason' => $message,
                'reopened_at' => $now->toIso8601String(),
                'source' => 'web_backoffice',
            ];
        }

        return $contextPayload;
    }

    private function recordStaffAudit($ticket, $eventType, $action, $actor, $reason, array $context, array $metadata = [])
    {
        $this->audit->record(
            isset($ticket->operator_id) ? (int) $ticket->operator_id : null,
            $eventType,
            'support_ticket',
            isset($ticket->ticket_uid) ? $ticket->ticket_uid : null,
            $actor,
            $reason,
            array_merge([
                'ticket_uid' => isset($ticket->ticket_uid) ? $ticket->ticket_uid : null,
                'ticket_action' => $action,
                'priority' => isset($ticket->priority) ? $ticket->priority : null,
                'permission' => $this->contextValue($context, 'permission'),
                'step_up' => !empty($context['step_up']),
                'source' => 'web_backoffice',
                'ip_address' => $this->contextValue($context, 'ip_address'),
                'user_agent' => $this->contextValue($context, 'user_agent'),
                'request_id' => $this->contextValue($context, 'request_id'),
            ], $metadata),
            $this->contextValue($context, 'ip_address'),
            $this->contextValue($context, 'user_agent')
        );
    }

    private function decodeContext($context)
    {
        if ($context === null || $context === '') {
            return [];
        }

        if (is_array($context)) {
            return $context;
        }

        $decoded = json_decode((string) $context, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : ['raw_context' => (string) $context];
    }

    private function formatContext($context)
    {
        if ($context === null || $context === '') {
            return '';
        }

        $redacted = $this->redactor->storageValue($context);
        $decoded = json_decode((string) $redacted, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $encoded = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            return $encoded === false ? '' : $encoded;
        }

        return (string) $redacted;
    }

    private function uniqueTicketUid()
    {
        do {
            $ticketUid = 'sup_' . Str::lower(Str::random(20));
        } while (DB::table('b2b_operator_support_tickets')->where('ticket_uid', $ticketUid)->exists());

        return $ticketUid;
    }

    private function enumValue($value, array $allowed, $default)
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function safeNullableText($value, $limit)
    {
        $value = $this->safeText($value, $limit);

        return $value === '' ? null : $value;
    }

    private function safeText($value, $limit)
    {
        if ($value === null) {
            return '';
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = $this->redactor->storageValue($value);

        return strlen($value) > $limit ? substr($value, 0, $limit) : $value;
    }

    private function requiredText($value, $message)
    {
        $value = trim((string) $value);
        if ($value === '') {
            throw new InvalidArgumentException($message);
        }

        return $this->redactor->storageValue($value);
    }

    private function messageCount($ticketId)
    {
        if (!Schema::hasTable('b2b_operator_support_ticket_messages')) {
            return 0;
        }

        return (int) DB::table('b2b_operator_support_ticket_messages')
            ->where('ticket_id', (int) $ticketId)
            ->count();
    }

    private function ticketMessages($ticketId, $operatorId, $limit)
    {
        if (!Schema::hasTable('b2b_operator_support_ticket_messages')) {
            return [];
        }

        $limit = max(1, min((int) $limit, 100));

        return DB::table('b2b_operator_support_ticket_messages')
            ->where('ticket_id', (int) $ticketId)
            ->where('operator_id', (int) $operatorId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->get($this->selectExisting('b2b_operator_support_ticket_messages', [
                'actor',
                'source',
                'message',
                'metadata',
                'created_at',
            ]))
            ->map(function ($row) {
                return $this->ticketMessagePayload($row);
            })->values()->all();
    }

    private function latestTicketMessage($ticketId, $operatorId)
    {
        if (!Schema::hasTable('b2b_operator_support_ticket_messages')) {
            return null;
        }

        $row = DB::table('b2b_operator_support_ticket_messages')
            ->where('ticket_id', (int) $ticketId)
            ->where('operator_id', (int) $operatorId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first($this->selectExisting('b2b_operator_support_ticket_messages', [
                'actor',
                'source',
                'message',
                'metadata',
                'created_at',
            ]));

        return $row ? $this->ticketMessagePayload($row) : null;
    }

    private function ticketMessagePayload($row)
    {
        return [
            'actor' => isset($row->actor) ? $this->safeNullableText($row->actor, 100) : null,
            'source' => isset($row->source) ? $this->safeNullableText($row->source, 40) : null,
            'message' => isset($row->message) ? $this->safeText($row->message, 2000) : '',
            'metadata' => isset($row->metadata) ? $this->safeMetadata($row->metadata) : null,
            'created_at' => isset($row->created_at) ? $this->isoTime($row->created_at) : null,
        ];
    }

    private function supportTicketDetailEndpoint($ticketUid)
    {
        $ticketUid = trim((string) $ticketUid);

        return $ticketUid === ''
            ? null
            : '/api/b2b/v1/portal/support/tickets/' . rawurlencode($ticketUid);
    }

    private function safeMetadata($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $redacted = $this->redactor->storageValue((string) $value);
        $decoded = json_decode((string) $redacted, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    private function isoTime($value)
    {
        if (!$value) {
            return null;
        }

        try {
            return $value instanceof Carbon
                ? $value->toIso8601String()
                : Carbon::parse($value)->toIso8601String();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function contextValue(array $context, $key)
    {
        return isset($context[$key]) ? $context[$key] : null;
    }

    private function selectExisting($table, array $columns)
    {
        $select = [];
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $select[] = $column;
            } else {
                $select[] = DB::raw('NULL as ' . $column);
            }
        }

        return $select;
    }

    private function filterColumns($table, array $values)
    {
        $filtered = [];
        foreach ($values as $column => $value) {
            if (Schema::hasColumn($table, $column)) {
                $filtered[$column] = $value;
            }
        }

        return $filtered;
    }
}
