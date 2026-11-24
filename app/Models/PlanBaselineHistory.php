<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['plan_id', 'work_order_id', 'previous_date', 'new_date', 'previous_hours', 'new_hours', 'changed_by', 'changed_at'])]
class PlanBaselineHistory extends Model
{
    use HasFactory;

    protected $table = 'plan_baseline_history';

    public $timestamps = false;

    protected $keyType = 'int';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'previous_date' => 'date',
            'new_date' => 'date',
            'previous_hours' => 'decimal:2',
            'new_hours' => 'decimal:2',
            'changed_at' => 'datetime',
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
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
