<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A document DHL returned for a shipment — its label, commercial invoice,
 * waybill or proof of delivery — kept as the base64 DHL sent.
 *
 * @property int $id
 * @property int $dhl_shipment_id
 * @property string $type
 * @property string $format
 * @property string $content
 */
class DhlShipmentDocument extends Model
{
    protected $fillable = ['dhl_shipment_id', 'type', 'format', 'content'];

    /** Never serialised by accident: it is the whole PDF. */
    protected $hidden = ['content'];

    /** @return BelongsTo<DhlShipment, $this> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(DhlShipment::class, 'dhl_shipment_id');
    }

    /** The document's bytes. */
    public function bytes(): string
    {
        return (string) base64_decode($this->content, true);
    }
}
