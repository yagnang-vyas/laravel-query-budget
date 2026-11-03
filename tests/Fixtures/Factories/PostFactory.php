<?php

declare(strict_types=1);

namespace YagnangVyas\QueryBudget\Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use YagnangVyas\QueryBudget\Tests\Fixtures\Models\Post;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author_id' => AuthorFactory::new(),
            'title' => fake()->sentence(),
        ];
    }
}
