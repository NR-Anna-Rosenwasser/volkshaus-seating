<?php

namespace App\Models;

use App\Enums\AuditActor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['actor', 'action', 'seat_id', 'attendee_id', 'before', 'after'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'audit_log';

    protected function casts(): array
    {
        return [
            'actor' => AuditActor::class,
            'before' => 'array',
            'after' => 'array',
        ];
    }
}
