<?php

namespace App\Http\Resources;

use App\Http\Resources\BuyerCartItemResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuyerCartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'count' => $this->items->sum('quantity'),
            'subtotal' => $this->items->sum(function ($item) {
                return ($item->product->sale_price ?? $item->product->base_price) * $item->quantity;
            }),
            'items' => BuyerCartItemResource::collection($this->items),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
