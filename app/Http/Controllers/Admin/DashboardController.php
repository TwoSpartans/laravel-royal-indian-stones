<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\NewsletterSubscriber;
use App\Models\Post;
use App\Models\Product;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'productCount' => Product::query()->count(),
            'enquiryCount' => Enquiry::query()->count(),
            'subscriberCount' => NewsletterSubscriber::query()->count(),
            'postCount' => Post::query()->count(),
            'recentEnquiries' => Enquiry::query()->latest()->limit(8)->get(),
            'recentPosts' => Post::query()->latest('published_on')->latest()->limit(6)->get(),
        ]);
    }
}
