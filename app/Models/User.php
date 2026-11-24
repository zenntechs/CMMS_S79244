<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /**
     * Determine whether the user holds one of the supplied roles.
     */
    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isChiefEngineer(): bool
    {
        return $this->hasRole(UserRole::ChiefEngineer);
    }

    public function isTechnician(): bool
    {
        return $this->hasRole(UserRole::Technician);
    }

    public function isViewer(): bool
    {
        return $this->hasRole(UserRole::Viewer);
    }

    /**
     * @return HasMany<MeterReading, $this>
     */
    public function recordedMeterReadings(): HasMany
    {
        return $this->hasMany(MeterReading::class, 'recorded_by');
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function createdWorkOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'created_by');
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function assignedWorkOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'assigned_to');
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function approvedWorkOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'approved_by');
    }

    /**
     * @return HasMany<WorkOrderEvent, $this>
     */
    public function workOrderEvents(): HasMany
    {
        return $this->hasMany(WorkOrderEvent::class, 'actor_id');
    }

    /**
     * @return HasMany<SpareMovement, $this>
     */
    public function spareMovements(): HasMany
    {
        return $this->hasMany(SpareMovement::class, 'actor_id');
    }

    /**
     * @return HasMany<AuditEvent, $this>
     */
    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class, 'actor_id');
    }

    /**
     * @return HasMany<PlanBaselineHistory, $this>
     */
    public function baselineChanges(): HasMany
    {
        return $this->hasMany(PlanBaselineHistory::class, 'changed_by');
    }
}
