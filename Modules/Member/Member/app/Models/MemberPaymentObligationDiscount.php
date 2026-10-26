<?php
namespace Modules\Member\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class MemberPaymentObligationDiscount extends Model
{
    protected $fillable=['member_payment_obligation_id','original_amount','discount_amount','payable_amount','reason','actor_user_id','metadata'];
    protected function casts(): array { return ['original_amount'=>'decimal:2','discount_amount'=>'decimal:2','payable_amount'=>'decimal:2','metadata'=>'array']; }
    public function obligation(): BelongsTo { return $this->belongsTo(MemberPaymentObligation::class); }
    public function actor(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'actor_user_id'); }
}
