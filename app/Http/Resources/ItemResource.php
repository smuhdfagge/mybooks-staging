<?php

namespace App\Http\Resources;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Item */
class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'type' => $this->type,
            'unit' => $this->unit,
            'selling_price' => (float) $this->selling_price,
            'cost_price' => (float) $this->cost_price,
            'tax_rate' => (float) $this->tax_rate,
            'is_taxable' => $this->is_taxable,
            'track_inventory' => $this->track_inventory,
            'reorder_level' => $this->reorder_level,
            'is_active' => $this->is_active,
            'category' => new ItemCategoryResource($this->whenLoaded('category')),
            'inventory' => new InventoryResource($this->whenLoaded('inventory')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
