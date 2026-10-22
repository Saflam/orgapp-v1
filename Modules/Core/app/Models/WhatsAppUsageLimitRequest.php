<?php

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppUsageLimitRequest extends Model
{
    protected $table = 'whatsapp_usage_limit_requests';
    protected $fillable = ['organization_id', 'requested_by', 'current_limit', 'requested_limit', 'reason', 'status', 'granted_limit', 'reviewed_by', 'review_note', 'reviewed_at'];
    protected function casts(): array { return ['reviewed_at' => 'datetime']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
