<?php

declare(strict_types=1);

namespace YagnangVyas\QueryBudget\Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use YagnangVyas\QueryBudget\Tests\Fixtures\Models\Comment;

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
