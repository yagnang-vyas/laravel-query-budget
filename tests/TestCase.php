<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Tests;

use ChakraCoder\QueryBudget\QueryBudgetServiceProvider;
use ChakraCoder\QueryBudget\Tests\Fixtures\Models\Comment;
use ChakraCoder\QueryBudget\Tests\Fixtures\Models\Post;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [QueryBudgetServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        /** @var Repository $config */
        $config = $app['config'];

        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $config->set('database.connections.secondary', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/database/migrations');
    }

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        require __DIR__.'/Fixtures/routes.php';
    }

    /**
     * Three posts, each with its own author and two comments.
     */
    protected function seedBlog(int $posts = 3, int $commentsPerPost = 2): void
    {
        Post::factory()->count($posts)->create()->each(function (Post $post) use ($commentsPerPost): void {
            Comment::factory()->count($commentsPerPost)->create(['post_id' => $post->id]);
        });
    }
}
