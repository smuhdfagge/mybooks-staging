<?php

namespace App\Http\Resources;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tenant */
class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'postal_code' => $this->postal_code,
            'logo' => $this->logo,
            'website' => $this->website,
            'tax_number' => $this->tax_number,
            'currency' => $this->currency,
            'fiscal_year_start' => $this->fiscal_year_start?->format('Y-m-d'),
            'is_active' => $this->is_active,
            'prices_include_tax' => $this->prices_include_tax,
            'tax_per_line_item' => $this->tax_per_line_item,
            'settings' => $this->settings,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
