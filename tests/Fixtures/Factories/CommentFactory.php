<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Tests\Fixtures\Factories;

use ChakraCoder\QueryBudget\Tests\Fixtures\Models\Comment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => PostFactory::new(),
            'body' => fake()->sentence(),
        ];
    }
}
