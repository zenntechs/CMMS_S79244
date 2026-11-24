<?php

namespace App\Models;

use App\Enums\Criticality;
use App\Enums\ServiceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['vessel_id', 'code', 'name', 'subsystem', 'location', 'criticality', 'service_status', 'has_hour_meter', 'meter_unit'])]
class Asset extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'criticality' => Criticality::class,
            'service_status' => ServiceStatus::class,
            'has_hour_meter' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Vessel, $this>
     */
    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    /**
     * @return HasMany<MeterReading, $this>
     */
    public function meterReadings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }

    /**
     * @return HasMany<MaintenancePlan, $this>
     */
    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class);
    }
}
