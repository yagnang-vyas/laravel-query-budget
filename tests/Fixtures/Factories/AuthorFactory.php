<?php

declare(strict_types=1);

namespace YagnangVyas\QueryBudget\Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use YagnangVyas\QueryBudget\Tests\Fixtures\Models\Author;

/**
 * @extends Factory<Author>
 */
class AuthorFactory extends Factory
{
    protected $model = Author::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
