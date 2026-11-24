<?php

namespace App\Http\Controllers;

use App\Models\Catalogue;
use App\Models\Category;
use App\Support\HomeContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ECatalogueController extends Controller
{
    public function __invoke(): View
    {
        $categories = Category::query()
            ->withCount(['products as published_products_count' => fn (Builder $query): Builder => $query->published()])
            ->orderBy('position')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->reject(fn (Category $category): bool => in_array($category->slug, HomeContent::EXCLUDED_COLLECTIONS, true))
            ->values();

        return view('e-catalogue', [
            'categories' => $categories,
            'catalogues' => $this->visibleCatalogues(),
            'active' => '',
            'title' => 'E-catalogue | Royal Indian Stones',
            'description' => 'Browse the Royal Indian Stones e-catalogue: sandstone, limestone, marble, granite, slate and the other stones we export from India.',
        ]);
    }

    public function preview(Request $request, Catalogue $catalogue): View|string
    {
        abort_unless($catalogue->pdfFileExists(), 404);

        $view = view('e-catalogue-preview', [
            'catalogue' => $catalogue,
            'pdfUrl' => asset((string) $catalogue->pdf_path),
            'pdfName' => $catalogue->pdf_original_name ?: $catalogue->slug.'.pdf',
            'active' => '',
            'title' => $catalogue->title.' | E-catalogue | Royal Indian Stones',
            'description' => filled($catalogue->description)
                ? (string) $catalogue->description
                : 'Preview the '.$catalogue->title.' from Royal Indian Stones, then download the PDF.',
        ]);

        if ($request->boolean('embed')) {
            return $view->fragments(['preview', 'viewer']);
        }

        return $view;
    }

    /**
     * @return Collection<int, Catalogue>
     */
    private function visibleCatalogues(): Collection
    {
        return Catalogue::query()
            ->orderBy('position')
            ->orderBy('title')
            ->orderBy('id')
            ->get()
            ->filter(fn (Catalogue $catalogue): bool => $catalogue->pdfFileExists())
            ->values();
    }
}
