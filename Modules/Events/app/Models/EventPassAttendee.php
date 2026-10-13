<?php
namespace Modules\Events\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Organization;
class EventPassAttendee extends Model {
    protected $fillable = ['organization_id','event_id','event_pass_id','name','email','category','status','first_entered_at','last_entered_at','last_exited_at','entry_count','created_by'];
    protected function casts(): array { return ['first_entered_at'=>'immutable_datetime','last_entered_at'=>'immutable_datetime','last_exited_at'=>'immutable_datetime','entry_count'=>'integer']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function pass(): BelongsTo { return $this->belongsTo(EventPass::class,'event_pass_id'); }
    public function scans(): HasMany { return $this->hasMany(EventAttendanceScan::class,'event_pass_attendee_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'created_by'); }
}
