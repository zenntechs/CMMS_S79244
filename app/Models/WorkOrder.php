<?php

namespace App\Models;

use App\Enums\WorkOrderState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['reference', 'plan_id', 'state', 'assigned_to', 'created_by', 'started_at', 'submitted_at', 'closed_at', 'actual_completion_date', 'actual_completion_hours', 'completion_notes', 'evidence_ref', 'approved_by'])]
class WorkOrder extends Model
{
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'state' => WorkOrderState::class,
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'closed_at' => 'datetime',
            'actual_completion_date' => 'date',
            'actual_completion_hours' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<MaintenancePlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'plan_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<WorkOrderEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(WorkOrderEvent::class, 'work_order_id');
    }

    /**
     * @return HasMany<SpareMovement, $this>
     */
    public function spareMovements(): HasMany
    {
        return $this->hasMany(SpareMovement::class, 'work_order_id');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('state', [WorkOrderState::Open, WorkOrderState::InProgress, WorkOrderState::Submitted]);
    }

    public function isActive(): bool
    {
        return $this->state instanceof WorkOrderState ? $this->state->isActive() : false;
    }
}
