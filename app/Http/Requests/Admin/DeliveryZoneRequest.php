<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DeliveryZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        $id = $this->route('delivery_zone') ? $this->route('delivery_zone')->id : null;

        return [
            'name' => 'required|string|max:100|unique:delivery_zones,name,'.$id,
            'price_currency' => 'sometimes|in:PKR,USD,EUR,GBP,AUD,CAD,AED,SAR,INR',
            'delivery_fee' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'display_order' => 'integer|min:0',
        ];
    }
}
