<?php
namespace Modules\Member\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Organization;
use Modules\Member\Enums\MemberPaymentObligationStatus;
class MemberPaymentObligation extends Model
{
    protected $fillable=['organization_id','membership_application_id','membership_id','member_id','member_payment_item_id','member_payment_item_rate_id','item_title','occurrence_key','amount','discount_amount','payable_amount','paid_amount','currency','status','due_at','paid_at','metadata'];
    protected function casts(): array { return ['status'=>MemberPaymentObligationStatus::class,'amount'=>'decimal:2','discount_amount'=>'decimal:2','payable_amount'=>'decimal:2','paid_amount'=>'decimal:2','due_at'=>'date','paid_at'=>'datetime','metadata'=>'array']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function application(): BelongsTo { return $this->belongsTo(MembershipApplication::class,'membership_application_id'); }
    public function membership(): BelongsTo { return $this->belongsTo(Membership::class); }
    public function member(): BelongsTo { return $this->belongsTo(Member::class); }
    public function paymentItem(): BelongsTo { return $this->belongsTo(MemberPaymentItem::class,'member_payment_item_id'); }
    public function paymentItemRate(): BelongsTo { return $this->belongsTo(MemberPaymentItemRate::class,'member_payment_item_rate_id'); }
    public function allocations(): HasMany { return $this->hasMany(MemberPaymentObligationAllocation::class); }
    public function discounts(): HasMany { return $this->hasMany(MemberPaymentObligationDiscount::class); }
    public function paymentLines(): HasMany { return $this->hasMany(MemberPaymentLine::class); }
    public function isPending(): bool { return in_array($this->status,[MemberPaymentObligationStatus::PENDING,MemberPaymentObligationStatus::PARTIALLY_PAID],true); }
    public function isSettled(): bool { return in_array($this->status,[MemberPaymentObligationStatus::PAID,MemberPaymentObligationStatus::WAIVED,MemberPaymentObligationStatus::CANCELLED,MemberPaymentObligationStatus::COVERED_BY_FAMILY],true); }
}
