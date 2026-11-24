<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['actor_id', 'action', 'entity_type', 'entity_id', 'before', 'after', 'reason', 'occurred_at'])]
class AuditEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $keyType = 'int';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'entity_id' => 'string',
            'before' => 'array',
            'after' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
