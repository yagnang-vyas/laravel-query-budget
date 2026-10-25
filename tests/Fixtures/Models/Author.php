<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Tests\Fixtures\Models;

use ChakraCoder\QueryBudget\Tests\Fixtures\Factories\AuthorFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Author extends Model
{
    /** @use HasFactory<AuthorFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    protected static function newFactory(): Factory
    {
        return AuthorFactory::new();
    }
}
