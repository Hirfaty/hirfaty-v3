<?php

use App\Models\Blog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * The journal row below the brochure on the home page.
 */
function homePost(string $title, array $attributes = []): Blog
{
    return Blog::create(array_merge([
        'title' => $title,
        'slug' => str($title)->slug()->value(),
        'content' => '<p>x</p>',
        'status' => Blog::STATUS_PUBLISHED,
        'published_at' => now()->subDay(),
    ], $attributes));
}

it('sends the three newest published articles, newest first', function () {
    homePost('Oldest', ['published_at' => now()->subDays(4)]);
    homePost('Third', ['published_at' => now()->subDays(3)]);
    homePost('Second', ['published_at' => now()->subDays(2)]);
    homePost('Newest', ['published_at' => now()->subDay()]);

    $this->get('/fr')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('latestPosts', 3)
        ->where('latestPosts.0.title', 'Newest')
        ->where('latestPosts.1.title', 'Second')
        ->where('latestPosts.2.title', 'Third')
        ->missing('latestPosts.0.content')
    );
});

it('leaves drafts and archived articles off the home page', function () {
    homePost('Live');
    homePost('Draft', ['status' => Blog::STATUS_DRAFT, 'published_at' => null]);
    homePost('Archived', ['status' => Blog::STATUS_ARCHIVED]);

    $this->get('/fr')->assertInertia(fn (Assert $page) => $page
        ->has('latestPosts', 1)
        ->where('latestPosts.0.title', 'Live')
    );
});

it('sends an empty row when nothing is published', function () {
    $this->get('/fr')->assertInertia(fn (Assert $page) => $page->has('latestPosts', 0));
});
