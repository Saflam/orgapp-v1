<?php
namespace Modules\Member\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class MemberPaymentLineAllocation extends Model
{
    protected $fillable=['member_payment_line_id','scope_type','scope_id','percentage','amount'];
    protected function casts(): array { return ['percentage'=>'decimal:4','amount'=>'decimal:2']; }
    public function line(): BelongsTo { return $this->belongsTo(MemberPaymentLine::class,'member_payment_line_id'); }
}
