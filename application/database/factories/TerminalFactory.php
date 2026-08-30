<?php

namespace Database\Factories;

use App\Models\Terminal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class TerminalFactory extends Factory
{
    protected $model = Terminal::class;

    public function definition(): array
    {
        return [
            'user_id' => $this->faker->randomNumber(),
            'name' => $this->faker->name(),
            'success_url' => $this->faker->url(),
            'fail_url' => $this->faker->url(),
            'webhook_url' => $this->faker->url(),
            'secret_key' => $this->faker->word(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
