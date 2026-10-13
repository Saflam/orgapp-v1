<?php
namespace Modules\Member\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Organization;
class MemberPayment extends Model
{
    protected $fillable=['organization_id','membership_application_id','membership_id','member_id','receipt_number','total_amount','currency','payment_method','reference','collector_scope_type','collector_scope_id','received_at','received_by','metadata'];
    protected function casts(): array { return ['total_amount'=>'decimal:2','received_at'=>'datetime','metadata'=>'array']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function application(): BelongsTo { return $this->belongsTo(MembershipApplication::class,'membership_application_id'); }
    public function membership(): BelongsTo { return $this->belongsTo(Membership::class); }
    public function member(): BelongsTo { return $this->belongsTo(Member::class); }
    public function receivedBy(): BelongsTo { return $this->belongsTo(User::class,'received_by'); }
    public function lines(): HasMany { return $this->hasMany(MemberPaymentLine::class); }
}
