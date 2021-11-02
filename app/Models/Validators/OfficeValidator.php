<?php 

namespace App\Models\Validators;

use App\Models\Office;
use Illuminate\Validation\Rule;

class OfficeValidator 
{
    public function validate(Office $office, $attributes)
    {
        return validator($attributes, [
            //this rule means that if there is an office already exists and we are updating,
            //then run this rule and add sometimes, else do not run it.
            'name' => [Rule::when($office->exists, 'sometimes'), 'required', 'string'],
            'description' => [Rule::when($office->exists, 'sometimes'), 'required', 'string'],
            'lat' => [Rule::when($office->exists, 'sometimes'), 'required', 'numeric'],
            'lng' => [Rule::when($office->exists, 'sometimes'), 'required', 'numeric'],
            'address_line_1' => [Rule::when($office->exists, 'sometimes'), 'required', 'string'],
            'hidden' => ['boolean'],
            'price_per_day' => [Rule::when($office->exists, 'sometimes'), 'required', 'integer', 'min:100'],
            'monthly_discount' => ['integer', 'min:0'],
            'tags' => ['array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')]
        ])->validate();
    }
}