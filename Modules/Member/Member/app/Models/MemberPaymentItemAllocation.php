<?php
namespace Modules\Member\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class MemberPaymentItemAllocation extends Model
{
    protected $fillable=['member_payment_item_rate_id','scope_type','percentage'];
    protected function casts(): array { return ['percentage'=>'decimal:4']; }
    public function rate(): BelongsTo { return $this->belongsTo(MemberPaymentItemRate::class,'member_payment_item_rate_id'); }
}
