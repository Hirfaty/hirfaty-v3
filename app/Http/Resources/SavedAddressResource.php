<?php

namespace App\Http\Resources;

use App\Models\Address;
use App\Services\CountryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One address out of the buyer's book, as checkout lists it: city and country
 * named in the visitor's language. Expects `city` loaded.
 *
 * @mixin Address
 */
class SavedAddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // An address with no country is a Moroccan one (the column's default).
        $country = $this->country ?: Address::DEFAULT_COUNTRY;

        return [
            'id' => $this->id,
            'label' => $this->label,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'address_line' => $this->address_line,
            'city_id' => $this->city_id,
            'city_name' => $this->city?->translated_name,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'country_name' => app(CountryService::class)->name($country),
            'is_default' => $this->is_default,
        ];
    }
}
