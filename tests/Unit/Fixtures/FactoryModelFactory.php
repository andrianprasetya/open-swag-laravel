<?php

namespace OpenSwag\Laravel\Tests\Unit\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

class FactoryModelFactory extends Factory
{
    protected $model = FactoryModel::class;

    public function definition(): array
    {
        return [
            'name' => 'Factory Name',
            'email' => 'factory@example.com',
            'age' => 30,
        ];
    }
}
