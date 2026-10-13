<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard.index', [
            'postCount' => Post::count(),
            'categoryCount' => Category::count(),
            'tagCount' => Tag::count(),
            'contactCount' => Contact::count(),
            'latestPosts' => Post::with(['category', 'tags'])->latest()->take(5)->get(),
        ]);
    }
}
