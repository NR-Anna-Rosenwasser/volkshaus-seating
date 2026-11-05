<?php

namespace App\Models;

use App\Enums\MailType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'type', 'sent_at'])]
class MailLog extends Model
{
    public $timestamps = false;

    protected $table = 'mail_log';

    protected function casts(): array
    {
        return [
            'type' => MailType::class,
            'sent_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
