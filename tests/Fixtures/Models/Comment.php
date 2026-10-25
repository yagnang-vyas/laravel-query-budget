<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Tests\Fixtures\Models;

use ChakraCoder\QueryBudget\Tests\Fixtures\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    protected static function newFactory(): Factory
    {
        return CommentFactory::new();
    }
}
