<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuyerProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'base_price' => $this->base_price,
            'sale_price' => $this->sale_price,
            'stock' => $this->stock,

            'category' => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
            ],

            'store' => [
                'id' => $this->store?->id,
                'slug' => $this->store?->slug,
                'description' => $this->store?->description,
                'store_name' => $this->store?->store_name,
                "city_name" => $this->store?->city->name,
            ],

            'images' => $this->images,
            'videos' => $this->videos,

            'wishlists_count' => $this->wishlists_count,
        ];
    }
}
