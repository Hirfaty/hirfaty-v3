<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $fillable = [
        'mediable_id',    // ← not mediatable_id
        'mediable_type',  // ← not mediatable_type
        'type',
        'path',
        'url',
        'disk',
        'mime_type',
        'size',
        'order',
    ];

    protected $casts = [
        // Written only by MediaHealthService — see its migration.
        'broken_at' => 'datetime',
    ];

    public function mediable()
    {
        return $this->morphTo();
    }
}
