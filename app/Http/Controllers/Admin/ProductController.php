<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductSpec;
use App\Services\CatalogueIndex;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('admin.products.index', [
            'products' => Product::query()->with('category')->orderBy('name')->get(),
        ]);
    }

    public function edit(Product $product): View
    {
        $product->load('specs');

        return view('admin.products.form', ['product' => $product]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'summary' => ['nullable', 'string', 'max:180'],
            'color' => ['nullable', 'string', 'max:120'],
            'is_published' => ['nullable', 'boolean'],
            'specs' => ['array'],
            'specs.*.label' => ['nullable', 'string', 'max:120'],
            'specs.*.value' => ['nullable', 'string', 'max:1000'],
        ]);

        $product->update([
            'name' => $data['name'],
            'summary' => $data['summary'] ?? '',
            'color' => $data['color'] ?? '',
            'is_published' => $request->boolean('is_published'),
        ]);

        $product->specs()->delete();
        $position = 0;
        foreach ($data['specs'] ?? [] as $spec) {
            $label = trim((string) ($spec['label'] ?? ''));
            $value = trim((string) ($spec['value'] ?? ''));
            if ($label === '' || $value === '') {
                continue;
            }
            ProductSpec::query()->create([
                'product_id' => $product->id,
                'label' => $label,
                'value' => $value,
                'position' => $position++,
            ]);
        }

        CatalogueIndex::write();

        return redirect()->route('admin.products.index')->with('status', 'Product updated.');
    }
}
