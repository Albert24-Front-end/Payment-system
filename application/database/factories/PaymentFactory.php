<?php

namespace Database\Factories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'terminal_id' => $this->faker->randomNumber(),
            'order_id' => $this->faker->word(),
            'amount' => $this->faker->randomNumber(),
            'description' => $this->faker->text(),
            'status' => $this->faker->randomNumber(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
