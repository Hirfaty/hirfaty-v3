<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A district of a Cathedis city — see the `cathedis_cities` migration. The id
 * is Cathedis's own.
 *
 * @property int $id
 * @property int $cathedis_city_id
 * @property string $name
 */
class CathedisSector extends Model
{
    public $incrementing = false;

    protected $fillable = ['id', 'cathedis_city_id', 'name'];

    public function city()
    {
        return $this->belongsTo(CathedisCity::class, 'cathedis_city_id');
    }
}
