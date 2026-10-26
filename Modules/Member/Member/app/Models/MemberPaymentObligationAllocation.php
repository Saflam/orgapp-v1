<?php
namespace Modules\Member\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class MemberPaymentObligationAllocation extends Model
{
    protected $fillable=['member_payment_obligation_id','scope_type','scope_id','percentage','original_amount','payable_amount'];
    protected function casts(): array { return ['percentage'=>'decimal:4','original_amount'=>'decimal:2','payable_amount'=>'decimal:2']; }
    public function obligation(): BelongsTo { return $this->belongsTo(MemberPaymentObligation::class); }
}
