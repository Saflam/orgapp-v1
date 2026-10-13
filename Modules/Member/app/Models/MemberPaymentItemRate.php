<?php
namespace Modules\Member\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class MemberPaymentItemRate extends Model
{
    protected $fillable=['member_payment_item_id','amount','currency','effective_from','effective_to'];
    protected function casts(): array { return ['amount'=>'decimal:2','effective_from'=>'date','effective_to'=>'date']; }
    public function paymentItem(): BelongsTo { return $this->belongsTo(MemberPaymentItem::class,'member_payment_item_id'); }
    public function allocations(): HasMany { return $this->hasMany(MemberPaymentItemAllocation::class); }
}
