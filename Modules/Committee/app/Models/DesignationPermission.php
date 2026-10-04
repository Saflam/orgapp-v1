<?php

namespace Modules\Committee\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Permission;

class DesignationPermission extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'designation_id',
        'permission_id',
    ];

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}