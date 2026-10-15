<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function __invoke(Request $request): View
    {
        $productId = (string) $request->query('product', '');
        $selected = preg_match('/^[A-Za-z0-9_-]+$/', $productId)
            ? Product::query()->where('slug', $productId)->first()
            : null;

        $project = trim((string) $request->query('project', ''));
        if (preg_match('/[\r\n]/', $project) || strlen($project) > 180) {
            $project = '';
        }

        $subject = 'Natural stone quotation';
        if ($project !== '') {
            $subject = 'Project enquiry: '.$project;
        }
        if ($selected) {
            $subject = 'Quotation: '.$selected->name;
        }

        return view('contact', [
            'selected' => $selected,
            'subject' => $subject,
            'active' => 'contact',
            'title' => 'Request a Quote | Royal Indian Stones',
            'description' => 'Send your stone requirements for a quotation. Share sizes, finish, quantity and destination.',
        ]);
    }
}
