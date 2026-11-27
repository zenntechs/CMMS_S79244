<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $this->authorize('viewAny', AuditEvent::class);

        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:50'],
            'actor_id' => ['nullable', 'uuid', 'exists:users,id'],
            'entity_type' => ['nullable', 'string', 'max:50'],
            'entity_id' => ['nullable', 'uuid'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if (! empty($filters['date_from']) && ! empty($filters['date_to']) && $filters['date_from'] > $filters['date_to']) {
            return redirect()->back()->withErrors(['date_to' => 'The end date must be on or after the start date.'])->withInput();
        }

        $query = AuditEvent::query()
            ->with('actor')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['actor_id'])) {
            $query->where('actor_id', $filters['actor_id']);
        }

        if (! empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        if (! empty($filters['entity_id'])) {
            $query->where('entity_id', $filters['entity_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('occurred_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('occurred_at', '<=', $filters['date_to']);
        }

        $actions = AuditEvent::query()->distinct()->orderBy('action')->pluck('action');
        $entityTypes = AuditEvent::query()->distinct()->orderBy('entity_type')->pluck('entity_type');
        $actors = User::orderBy('name')->get();

        return view('audit.index', [
            'events' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
            'actions' => $actions,
            'entityTypes' => $entityTypes,
            'actors' => $actors,
        ]);
    }

    public function show(AuditEvent $auditEvent): View
    {
        $this->authorize('view', $auditEvent);

        $auditEvent->load('actor');

        return view('audit.show', [
            'event' => $auditEvent,
            'before' => $this->sanitizedPayload($auditEvent->before),
            'after' => $this->sanitizedPayload($auditEvent->after),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function sanitizedPayload(?array $payload): string
    {
        if ($payload === null) {
            return 'null';
        }

        $sensitiveKeys = ['password', 'password_hash', 'remember_token', 'token', 'session', 'secret', 'api_key'];

        array_walk_recursive($payload, function (&$value, $key) use ($sensitiveKeys): void {
            if (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                $value = '[redacted]';
            }
        });

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
