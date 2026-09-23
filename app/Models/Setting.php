<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One site-wide setting, keyed by name — see the `settings` migration.
 *
 * Deliberately untyped: the value is text, and the service that owns a key
 * (DeliverySettingsService for `delivery.*`) is what casts it and supplies
 * the default when the row does not exist.
 *
 * @property string $key
 * @property string|null $value
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];
}
