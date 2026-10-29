<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Support\HomeContent;
use App\Support\ResponsivePicture;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(HomeContent $content, ResponsivePicture $pictures): View
    {
        $categories = Category::query()->orderBy('position')->get();
        $slides = $content->hero();
        $heroImage = $slides[0]['image'] ?? 'assets/img/slide/slide-1.jpg';

        return view('home', [
            'slides' => $slides,
            'heroAvif' => $pictures->sources($heroImage)['avif'],
            'supplier' => $content->copy('supplier'),
            'exploreCopy' => $content->copy('explore'),
            'explore' => $content->explore($categories),
            'stonesCopy' => $content->copy('stones'),
            'stones' => $content->stones(),
            'journalCopy' => $content->copy('journal'),
            'enquiry' => $content->copy('enquiry'),
            'posts' => Post::query()->published()->with('postCategory')->orderByDesc('published_on')->orderBy('title')->limit(3)->get(),
            'active' => 'home',
            'title' => 'Indian natural stone | Royal Indian Stones',
            'description' => 'Discover sandstone, granite, marble and more for architecture, interiors and outdoor projects.',
            'shareImage' => asset($heroImage),
        ]);
    }
}
