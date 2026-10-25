<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Tests\Fixtures\Controllers;

use ChakraCoder\QueryBudget\Tests\Fixtures\Models\Comment;
use ChakraCoder\QueryBudget\Tests\Fixtures\Models\Post;
use Illuminate\Http\JsonResponse;

class PostController
{
    /**
     * N+1: one query for the posts, then one author query per post.
     */
    public function lazy(): JsonResponse
    {
        $posts = Post::all();

        return response()->json($posts->map(fn (Post $post): array => [
            'title' => $post->title,
            'author' => $post->author->name,
        ]));
    }

    /**
     * Two queries no matter how many posts exist.
     */
    public function eager(): JsonResponse
    {
        $posts = Post::with('author')->get();

        return response()->json($posts->map(fn (Post $post): array => [
            'title' => $post->title,
            'author' => $post->author->name,
        ]));
    }

    /**
     * The same count executed repeatedly: a duplicate, but not lazy loading.
     */
    public function duplicates(): JsonResponse
    {
        $totals = [];

        foreach (['first', 'second', 'third'] as $label) {
            $totals[$label] = Comment::count();
        }

        return response()->json($totals);
    }

    /**
     * No database access at all.
     */
    public function quiet(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }
}
