<?php

use App\Http\Controllers\Admin\BlogStudioController;
use App\Http\Controllers\Admin\CommentController as AdminCommentController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EnquiryController as AdminEnquiryController;
use App\Http\Controllers\Admin\HomePageController;
use App\Http\Controllers\Admin\JournalSettingController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\NewsletterController as AdminNewsletterController;
use App\Http\Controllers\Admin\OutreachController;
use App\Http\Controllers\Admin\OutreachMentionController;
use App\Http\Controllers\Admin\OutreachProfileController;
use App\Http\Controllers\Admin\OutreachTopicController;
use App\Http\Controllers\Admin\PostCategoryController;
use App\Http\Controllers\Admin\PostController as AdminPostController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SitemapXmlController;
use App\Http\Middleware\NoindexAdminResponses;
use Illuminate\Support\Facades\Route;

Route::middleware([NoindexAdminResponses::class, 'guest'])->group(function () {
    Route::get('/admin/login', [LoginController::class, 'create'])->name('admin.login');
    Route::post('/admin/login', [LoginController::class, 'store'])->middleware('throttle:admin-login')->name('admin.login.store');
});

Route::middleware([NoindexAdminResponses::class, 'auth', 'site.admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.dashboard'));
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/enquiries', [AdminEnquiryController::class, 'index'])->name('enquiries');
    Route::delete('/enquiries/{enquiry}', [AdminEnquiryController::class, 'destroy'])->name('enquiries.destroy');
    Route::get('/newsletter', [AdminNewsletterController::class, 'index'])->name('newsletter');
    Route::delete('/newsletter/{subscriber}', [AdminNewsletterController::class, 'destroy'])->name('newsletter.destroy');
    Route::get('/posts', [AdminPostController::class, 'index'])->name('posts.index');
    Route::get('/posts/studio/create', [BlogStudioController::class, 'create'])->name('posts.studio.create');
    Route::get('/posts/studio/products', [BlogStudioController::class, 'products'])->name('posts.studio.products');
    Route::get('/posts/create', [AdminPostController::class, 'create'])->name('posts.create');
    Route::post('/posts', [AdminPostController::class, 'store'])->name('posts.store');
    Route::get('/posts/{post}/studio', [BlogStudioController::class, 'show'])->name('posts.studio');
    Route::put('/posts/{post}/studio', [BlogStudioController::class, 'save'])->name('posts.studio.save');
    Route::post('/posts/{post}/studio/preview', [BlogStudioController::class, 'preview'])->name('posts.studio.preview');
    Route::post('/posts/{post}/studio/publish', [BlogStudioController::class, 'publish'])->name('posts.studio.publish');
    Route::post('/posts/{post}/studio/schedule', [BlogStudioController::class, 'schedule'])->name('posts.studio.schedule');
    Route::delete('/posts/{post}/studio/schedule', [BlogStudioController::class, 'cancelSchedule'])->name('posts.studio.schedule.cancel');
    Route::post('/posts/{post}/studio/convert', [BlogStudioController::class, 'convert'])->name('posts.studio.convert');
    Route::post('/posts/{post}/studio/media', [BlogStudioController::class, 'media'])->name('posts.studio.media');
    Route::get('/posts/{post}/edit', [AdminPostController::class, 'edit'])->name('posts.edit');
    Route::put('/posts/{post}', [AdminPostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{post}', [AdminPostController::class, 'destroy'])->name('posts.destroy');
    Route::get('/post-categories', [PostCategoryController::class, 'index'])->name('post-categories.index');
    Route::post('/post-categories', [PostCategoryController::class, 'store'])->name('post-categories.store');
    Route::put('/post-categories/{postCategory}', [PostCategoryController::class, 'update'])->name('post-categories.update');
    Route::delete('/post-categories/{postCategory}', [PostCategoryController::class, 'destroy'])->name('post-categories.destroy');
    Route::get('/comments', [AdminCommentController::class, 'index'])->name('comments.index');
    Route::post('/comments/{comment}/approve', [AdminCommentController::class, 'approve'])->name('comments.approve');
    Route::delete('/comments/{comment}', [AdminCommentController::class, 'destroy'])->name('comments.destroy');
    Route::get('/journal', [JournalSettingController::class, 'edit'])->name('journal.edit');
    Route::put('/journal', [JournalSettingController::class, 'update'])->name('journal.update');
    Route::get('/home', [HomePageController::class, 'edit'])->name('home.edit');
    Route::put('/home/{section}', [HomePageController::class, 'update'])->where('section', 'hero|supplier|explore|stones|journal|enquiry')->name('home.update');
    Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
    Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
    Route::post('/products/{product}/duplicate', [AdminProductController::class, 'duplicate'])->name('products.duplicate');
    Route::get('/outreach', [OutreachController::class, 'index'])->name('outreach.index');
    Route::get('/outreach/mentions', [OutreachMentionController::class, 'index'])->name('outreach.mentions.index');
    Route::post('/outreach/mentions', [OutreachMentionController::class, 'store'])->name('outreach.mentions.store');
    Route::put('/outreach/mentions/{mention}', [OutreachMentionController::class, 'update'])->name('outreach.mentions.update');
    Route::delete('/outreach/mentions/{mention}', [OutreachMentionController::class, 'destroy'])->name('outreach.mentions.destroy');
    Route::get('/outreach/profiles', [OutreachProfileController::class, 'index'])->name('outreach.profiles.index');
    Route::post('/outreach/profiles', [OutreachProfileController::class, 'store'])->name('outreach.profiles.store');
    Route::put('/outreach/profiles/{profile}', [OutreachProfileController::class, 'update'])->name('outreach.profiles.update');
    Route::delete('/outreach/profiles/{profile}', [OutreachProfileController::class, 'destroy'])->name('outreach.profiles.destroy');
    Route::get('/outreach/topics', [OutreachTopicController::class, 'index'])->name('outreach.topics.index');
    Route::post('/outreach/topics', [OutreachTopicController::class, 'store'])->name('outreach.topics.store');
    Route::put('/outreach/topics/{topic}', [OutreachTopicController::class, 'update'])->name('outreach.topics.update');
    Route::delete('/outreach/topics/{topic}', [OutreachTopicController::class, 'destroy'])->name('outreach.topics.destroy');
});

Route::get('/', HomeController::class)->name('home');
Route::get('/products', CatalogueController::class)->name('catalogue');
Route::get('/contact', ContactController::class)->name('contact');
Route::post('/contact', EnquiryController::class)->name('enquiry.store');
Route::post('/newsletter', NewsletterController::class)->name('newsletter.store');
Route::get('/sitemap.xml', SitemapXmlController::class)->name('sitemap.xml');
Route::get('/blogs', function () {
    $query = request()->getQueryString();

    return redirect('/blog'.($query ? '?'.$query : ''), 301);
});
Route::post('/{slug}/comments', [CommentController::class, 'store'])->middleware('throttle:comments')->where('slug', '[A-Za-z0-9_-]+')->name('comments.store');

Route::redirect('/index.html', '/', 301);
Route::redirect('/index', '/', 301);
Route::redirect('/about', '/aboutus', 301);
Route::redirect(
    '/blog-the-tranquil-beauty-and-creative-applications of-natural-pebbles-240204',
    '/blog-the-tranquil-beauty-and-creative-applications-of-natural-pebbles-240204',
    301,
);
Route::get('/{slug}.html', function (string $slug) {
    return redirect('/'.$slug, 301);
})->where('slug', '[A-Za-z0-9_-]+');

require __DIR__.'/portal.php';

Route::get('/{slug}', [SiteController::class, 'show'])
    ->where('slug', '[A-Za-z0-9_-]+')
    ->name('site.show');
