<?php
namespace Modules\Member\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class MemberPaymentLine extends Model
{
    protected $fillable=['member_payment_id','member_payment_obligation_id','amount'];
    protected function casts(): array { return ['amount'=>'decimal:2']; }
    public function payment(): BelongsTo { return $this->belongsTo(MemberPayment::class,'member_payment_id'); }
    public function obligation(): BelongsTo { return $this->belongsTo(MemberPaymentObligation::class,'member_payment_obligation_id'); }
    public function allocations(): HasMany { return $this->hasMany(MemberPaymentLineAllocation::class); }
}
