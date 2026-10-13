<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(): View
    {
        $posts = Post::with(['category', 'tags'])->withCount('tags')->latest()->paginate(10);

        return view('posts.index', compact('posts'));
    }

    public function create(): View
    {
        return view('posts.create', [
            'categories' => Category::orderBy('name')->get(),
            'tags' => Tag::orderBy('name')->get(),
        ]);
    }

    public function store(StorePostRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['slug'] = ($validated['slug'] ?? null) ?: Str::slug($validated['title']).'-'.Str::random(6);
        $validated['is_published'] = $request->boolean('is_published', true);

        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = $request->file('thumbnail')->store('thumbnails', 'public');
        }

        $tags = $validated['tags'] ?? [];
        unset($validated['tags']);

        $post = Post::create($validated);
        $post->tags()->sync($tags);

        return redirect()->route('posts.show', $post)->with('status', 'Post berhasil dibuat.');
    }

    public function show(Post $post): View
    {
        $post->load(['category', 'tags']);

        $related = Post::with('category')->where('id', '!=', $post->id)
            ->when($post->category_id !== null, fn ($query) => $query->where('category_id', $post->category_id))
            ->latest()->take(3)->get();

        return view('posts.show', compact('post', 'related'));
    }

    public function edit(Post $post): View
    {
        return view('posts.edit', [
            'post' => $post->load('tags'),
            'categories' => Category::orderBy('name')->get(),
            'tags' => Tag::orderBy('name')->get(),
        ]);
    }

    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        $validated = $request->validated();
        $validated['slug'] = ($validated['slug'] ?? null) ?: $post->slug;
        $validated['is_published'] = $request->boolean('is_published');

        if ($request->boolean('remove_thumbnail')) {
            $this->deleteThumbnail($post);
            $validated['thumbnail'] = null;
        } elseif ($request->hasFile('thumbnail')) {
            $this->deleteThumbnail($post);
            $validated['thumbnail'] = $request->file('thumbnail')->store('thumbnails', 'public');
        } else {
            unset($validated['thumbnail']);
        }

        unset($validated['remove_thumbnail']);
        $tags = $validated['tags'] ?? [];
        unset($validated['tags']);

        $post->update($validated);
        $post->tags()->sync($tags);

        return redirect()->route('posts.show', $post)->with('status', 'Post berhasil diperbarui.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $this->deleteThumbnail($post);
        $post->tags()->detach();
        $post->delete();

        return redirect()->route('posts.index')->with('status', 'Post berhasil dihapus.');
    }

    private function deleteThumbnail(Post $post): void
    {
        if ($post->thumbnail !== null && $post->thumbnail !== '') {
            Storage::disk('public')->delete($post->thumbnail);
        }
    }
}
