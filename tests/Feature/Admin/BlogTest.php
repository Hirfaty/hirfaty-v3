<?php

use App\Models\Blog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function admin(): User
{
    return User::factory()->create(['role' => 'super_admin']);
}

test('an admin creates an article and the slug comes from the title', function () {
    $this->actingAs(admin())
        ->post(route('admin.blogs.store'), [
            'title' => 'La poterie de Safi',
            'excerpt' => 'De la terre au four.',
            'content' => '<p>Un premier paragraphe.</p>',
            'status' => 'archived',
        ])
        ->assertRedirect();

    $blog = Blog::firstWhere('slug', 'la-poterie-de-safi');

    expect($blog)->not->toBeNull()
        ->and($blog->title)->toBe('La poterie de Safi')
        ->and($blog->status)->toBe('archived')
        // Saved straight into the archive: no parution date until it goes live.
        ->and($blog->published_at)->toBeNull();
});

test('draft is no longer a status an article can take', function () {
    $this->actingAs(admin())
        ->post(route('admin.blogs.store'), [
            'title' => 'Ancien brouillon',
            'status' => 'draft',
        ])
        ->assertSessionHasErrors('status');

    expect(Blog::count())->toBe(0);
});

test('archiving an article keeps its parution date', function () {
    $published = now()->subWeek()->startOfSecond();
    $blog = Blog::create([
        'title' => 'Tadelakt', 'slug' => 'tadelakt',
        'status' => 'published', 'published_at' => $published,
    ]);

    $this->actingAs(admin())->patch(route('admin.blogs.archive', $blog));

    expect($blog->refresh()->status)->toBe('archived')
        ->and($blog->published_at->equalTo($published))->toBeTrue();
});

test('unarchiving puts an article straight back online', function () {
    $blog = Blog::create(['title' => 'Cuivre', 'slug' => 'cuivre', 'status' => 'archived']);

    $this->actingAs(admin())->patch(route('admin.blogs.archive', $blog));

    // Never live before, so it is stamped like a first publish.
    expect($blog->refresh()->status)->toBe('published')
        ->and($blog->published_at)->not->toBeNull();

    $this->get(route('blog.show', 'cuivre'))->assertOk();
});

test('publishing stamps published_at automatically', function () {
    $this->actingAs(admin())
        ->post(route('admin.blogs.store'), [
            'title' => 'Les tapis du Moyen Atlas',
            'status' => 'published',
        ])
        ->assertRedirect();

    expect(Blog::first()->published_at)->not->toBeNull();
});

test('a second article with the same title gets a distinct slug', function () {
    $admin = admin();

    foreach ([1, 2] as $_) {
        $this->actingAs($admin)->post(route('admin.blogs.store'), [
            'title' => 'Zellige',
            'status' => 'archived',
        ]);
    }

    expect(Blog::pluck('slug')->all())->toBe(['zellige', 'zellige-2']);
});

test('script tags and event handlers are stripped from the body', function () {
    $this->actingAs(admin())->post(route('admin.blogs.store'), [
        'title' => 'Article piégé',
        'content' => '<p onclick="steal()">Bonjour</p><script>alert(1)</script>'
            .'<a href="javascript:alert(1)">lien</a>',
        'status' => 'archived',
    ]);

    $content = Blog::first()->content;

    expect($content)->toContain('<p>Bonjour</p>')
        ->and($content)->not->toContain('script')
        ->and($content)->not->toContain('onclick')
        ->and($content)->not->toContain('javascript:');
});

test('accented and arabic copy survives sanitising intact', function () {
    $this->actingAs(admin())->post(route('admin.blogs.store'), [
        'title' => 'Héritage',
        'content' => '<p>Poterie de Safi — çàéù</p><p>الفخار</p>',
        'status' => 'archived',
    ]);

    expect(Blog::first()->content)
        ->toContain('Poterie de Safi — çàéù')
        ->toContain('الفخار');
});

test('an arabic title is transliterated into a usable slug', function () {
    $this->actingAs(admin())->post(route('admin.blogs.store'), [
        'title' => 'الفخار المغربي',
        'status' => 'archived',
    ]);

    expect(Blog::first()->slug)->toBe('alfkhar-almghrby');
});

test('a title with no sluggable characters still gets a URL', function () {
    $this->actingAs(admin())->post(route('admin.blogs.store'), [
        'title' => '!!! ???',
        'status' => 'archived',
    ]);

    // Str::slug() returns '' here, and an article with no slug would have no
    // reachable URL at all.
    expect(Blog::first()->slug)->toBe('article');
});

test('renaming a published article keeps its original slug', function () {
    $blog = Blog::create([
        'title' => 'Titre initial',
        'slug' => 'titre-initial',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    $this->actingAs(admin())->post(route('admin.blogs.update', $blog), [
        'title' => 'Titre corrigé',
        'status' => 'published',
    ]);

    expect($blog->refresh()->slug)->toBe('titre-initial')
        ->and($blog->title)->toBe('Titre corrigé');
});

test('the public listing shows published articles and hides archived ones', function () {
    Blog::create([
        'title' => 'En ligne', 'slug' => 'en-ligne',
        'status' => 'published', 'published_at' => now()->subHour(),
    ]);
    Blog::create(['title' => 'Rangé', 'slug' => 'range', 'status' => 'archived']);

    $this->get(route('blog.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('blogs.data', 1)
            ->where('blogs.data.0.title', 'En ligne')
        );
});

test('a guest cannot reach an archived article by guessing its slug', function () {
    Blog::create(['title' => 'Secret', 'slug' => 'secret', 'status' => 'archived']);

    $this->get(route('blog.show', 'secret'))->assertNotFound();
});

test('an admin previews an archived article that the public cannot see', function () {
    $blog = Blog::create([
        'title' => 'Archivé', 'slug' => 'archive',
        'content' => '<p>Encore en cours.</p>', 'status' => 'archived',
    ]);

    $this->actingAs(admin())
        ->get(route('admin.blogs.preview', $blog))
        ->assertOk()
        ->assertSee('Encore en cours.', false);
});

test('non-admins are kept out of the blog admin', function () {
    // RoleMiddleware bounces a wrong-role user to the storefront rather than
    // 403ing, so this asserts the redirect, not a status code.
    $this->actingAs(User::factory()->create(['role' => 'buyer']))
        ->get(route('admin.blogs.index'))
        ->assertRedirect(route('products.index'));
});

test('a guest cannot write an article', function () {
    $this->post(route('admin.blogs.store'), [
        'title' => 'Intrusion',
        'status' => 'published',
    ])->assertRedirect(route('login'));

    expect(Blog::count())->toBe(0);
});

test('deleting an article removes its cover file', function () {
    Storage::fake('public');
    $admin = admin();

    $this->actingAs($admin)->post(route('admin.blogs.store'), [
        'title' => 'Avec couverture',
        'status' => 'archived',
        'cover' => UploadedFile::fake()->create('cover.jpg', 40, 'image/jpeg'),
    ]);

    $blog = Blog::first();
    $path = $blog->media()->first()->path;

    Storage::disk('public')->assertExists($path);

    $this->actingAs($admin)->delete(route('admin.blogs.destroy', $blog));

    Storage::disk('public')->assertMissing($path);
    expect(Blog::count())->toBe(0);
});

test('replacing the cover discards the previous file', function () {
    Storage::fake('public');
    $admin = admin();

    $this->actingAs($admin)->post(route('admin.blogs.store'), [
        'title' => 'Couverture',
        'status' => 'archived',
        'cover' => UploadedFile::fake()->create('first.jpg', 40, 'image/jpeg'),
    ]);

    $blog = Blog::first();
    $first = $blog->media()->first()->path;

    $this->actingAs($admin)->post(route('admin.blogs.update', $blog), [
        'title' => 'Couverture',
        'status' => 'archived',
        'cover' => UploadedFile::fake()->create('second.jpg', 40, 'image/jpeg'),
    ]);

    Storage::disk('public')->assertMissing($first);
    // Exactly one cover, never an accumulating gallery.
    expect($blog->media()->count())->toBe(1);
});

test('the inline image endpoint answers with a usable url', function () {
    Storage::fake('public');

    $this->actingAs(admin())
        ->post(route('admin.blogs.images.store'), [
            'image' => UploadedFile::fake()->create('inline.png', 40, 'image/png'),
        ])
        ->assertOk()
        ->assertJsonStructure(['url']);

    expect(Storage::disk('public')->files('blogs/inline'))->toHaveCount(1);
});

test('an invalid status is rejected', function () {
    $this->actingAs(admin())
        ->post(route('admin.blogs.store'), [
            'title' => 'Statut inventé',
            'status' => 'whatever',
        ])
        ->assertSessionHasErrors('status');

    expect(Blog::count())->toBe(0);
});
