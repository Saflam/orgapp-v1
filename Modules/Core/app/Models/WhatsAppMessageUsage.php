<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessageUsage extends Model
{
    /** Explicit table name: Eloquent would infer `whats_app_message_usages`. */
    protected $table = 'whatsapp_message_usages';

    protected $fillable = [
        'organization_id', 'user_id', 'notification_delivery_id', 'otp_challenge_id',
        'service_category', 'channel_source', 'provider_message_id', 'status', 'delivery_status',
        'meta_pricing_category', 'meta_billable', 'meta_cost_amount', 'meta_cost_currency',
        'metadata', 'accepted_at', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'meta_billable' => 'boolean',
            'accepted_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function user(): BelongsTo { return $this->belongsTo(\App\Models\User::class); }
    public function delivery(): BelongsTo { return $this->belongsTo(NotificationDelivery::class, 'notification_delivery_id'); }
    public function otpChallenge(): BelongsTo { return $this->belongsTo(OtpChallenge::class); }
}
