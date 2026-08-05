<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClothesCategoryRequest;
use App\Models\ClothesCategory;
use App\Models\ClothingItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ClothesCategoryController extends Controller
{
    public function index(): View
    {
        $categories = ClothesCategory::withCount('clothingItems')->orderBy('name')->paginate(10, pageName: 'category_page');
        $clothingItems = ClothingItem::with('category')->orderBy('name')->paginate(10, pageName: 'item_page');
        $allCategories = ClothesCategory::orderBy('name')->get();

        return view('catalog.categories.index', compact('categories', 'clothingItems', 'allCategories'));
    }

    public function store(StoreClothesCategoryRequest $request): RedirectResponse
    {
        ClothesCategory::create($request->validated());

        return back()->with('status', 'Category created.');
    }

    public function show(ClothesCategory $category): View
    {
        $category->load('clothingItems');

        return view('catalog.categories.show', compact('category'));
    }

    public function update(StoreClothesCategoryRequest $request, ClothesCategory $category): RedirectResponse
    {
        $category->update($request->validated());

        return back()->with('status', 'Category updated.');
    }

    public function destroy(ClothesCategory $category): RedirectResponse
    {
        if ($category->clothingItems()->exists()) {
            return back()->withErrors(['category' => 'Remove or reassign this category\'s clothing items before deleting it.']);
        }

        $category->delete();

        return redirect()->route('catalog.categories')->with('status', 'Category deleted.');
    }
}
