<?php
namespace Modules\Member\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Organization;

class MemberPaymentItem extends Model
{
    protected $fillable = ['organization_id','membership_type_id','title','slug','description','is_mandatory','is_recurring','recurring_period','recurring_by','recurring_date','is_active','reminder_days_before_due','reminder_days_after_due','metadata'];
    protected function casts(): array { return ['is_mandatory'=>'boolean','is_recurring'=>'boolean','is_active'=>'boolean','reminder_days_before_due'=>'integer','reminder_days_after_due'=>'integer','metadata'=>'array']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function membershipType(): BelongsTo { return $this->belongsTo(MembershipType::class); }
    public function rates(): HasMany { return $this->hasMany(MemberPaymentItemRate::class); }
    public function paymentObligations(): HasMany { return $this->hasMany(MemberPaymentObligation::class); }
}
