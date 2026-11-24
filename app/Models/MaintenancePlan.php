<?php

namespace App\Models;

use App\Enums\PlanTrigger;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['asset_id', 'code', 'task_description', 'trigger_type', 'interval_days', 'interval_hours', 'last_completion_date', 'last_completion_hours', 'last_completion_work_order_id', 'source_label', 'is_active'])]
class MaintenancePlan extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'trigger_type' => PlanTrigger::class,
            'interval_days' => 'integer',
            'interval_hours' => 'decimal:2',
            'last_completion_date' => 'date',
            'last_completion_hours' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'plan_id');
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function lastCompletionWorkOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'last_completion_work_order_id');
    }

    /**
     * @return HasMany<PlanBaselineHistory, $this>
     */
    public function baselineHistory(): HasMany
    {
        return $this->hasMany(PlanBaselineHistory::class, 'plan_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function usesCalendar(): bool
    {
        return $this->trigger_type->usesCalendar();
    }

    public function usesHours(): bool
    {
        return $this->trigger_type->usesHours();
    }
}
