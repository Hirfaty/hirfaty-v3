<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A city on Cathedis's list — see the `cathedis_cities` migration. The id is
 * Cathedis's own.
 *
 * @property int $id
 * @property string $name
 * @property string|null $zone
 * @property list<string>|null $aliases
 * @property bool $delivers
 * @property bool $archived
 */
class CathedisCity extends Model
{
    public $incrementing = false;

    protected $fillable = ['id', 'name', 'zone', 'aliases', 'delivers', 'archived'];

    protected $casts = [
        'aliases' => 'array',
        'delivers' => 'boolean',
        'archived' => 'boolean',
    ];

    public function sectors()
    {
        return $this->hasMany(CathedisSector::class);
    }

    public function cities()
    {
        return $this->hasMany(City::class);
    }

    /** Whether a parcel can be sent there today. */
    public function isServed(): bool
    {
        return $this->delivers && ! $this->archived;
    }
}
