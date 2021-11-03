<?php

namespace Database\Factories;

use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfficeFactory extends Factory
{
    protected $model = Office::class;
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'lat' => $this->faker->latitude,
            'lng' => $this->faker->longitude,
            'address_line_1' => $this->faker->address,
            'approval_status' => Office::APPROVED_STATUS,
            'hidden' => false,
            'price_per_day' => $this->faker->numberBetween(1_000, 2_000),
            'monthly_discount' => 0
        ];
    }

    public function pending()
    {
        return $this->state([
            'approval_status' => Office::PENDING_STATUS
        ]);
    }

    public function hidden()
    {
        return $this->state([
            'hidden' => true
        ]);
    }
}
