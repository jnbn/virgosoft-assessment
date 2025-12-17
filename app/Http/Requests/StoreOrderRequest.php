<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Order::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'symbol' => ['required', 'string', 'max:10'],
            'side' => ['required', 'in:buy,sell'],
            'price' => ['required', 'numeric', 'min:0', 'decimal:0,8'],
            'amount' => ['required', 'numeric', 'min:0', 'decimal:0,8'],
        ];
    }
}
