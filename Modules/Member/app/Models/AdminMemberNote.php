<?php

namespace Modules\Member\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminMemberNote extends Model
{
    use HasFactory;

    protected $table = 'member_admin_notes';

    protected $fillable = [
        'organization_id',
        'member_id',
        'author_user_id',
        'body',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(\Modules\Core\Models\Organization::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
