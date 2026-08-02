<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClothingItemRequest;
use App\Models\ClothesCategory;
use App\Models\ClothingItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class ClothingItemController extends Controller
{
    public function store(StoreClothingItemRequest $request, ClothesCategory $category): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $data['image_path'] = $file->store('clothing-items', 'public');
            $data['image_mime'] = $file->getMimeType();
            $data['image_size'] = $file->getSize();
        }

        $category->clothingItems()->create($data);

        return back()->with('status', 'Clothing item added.');
    }

    /**
     * Standalone entry point (category chosen via a picker in the form itself,
     * not implied by the URL) -- e.g. the "New item" card on the categories
     * index page, for adding an item without first drilling into a category.
     */
    public function storeStandalone(StoreClothingItemRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $category = ClothesCategory::findOrFail($data['clothes_category_id']);
        unset($data['clothes_category_id']);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $data['image_path'] = $file->store('clothing-items', 'public');
            $data['image_mime'] = $file->getMimeType();
            $data['image_size'] = $file->getSize();
        }

        $category->clothingItems()->create($data);

        return back()->with('status', "Clothing item added to {$category->name}.");
    }

    public function update(StoreClothingItemRequest $request, ClothesCategory $category, ClothingItem $item): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }

            $file = $request->file('image');
            $data['image_path'] = $file->store('clothing-items', 'public');
            $data['image_mime'] = $file->getMimeType();
            $data['image_size'] = $file->getSize();
        }

        $item->update($data);

        return back()->with('status', 'Clothing item updated.');
    }

    public function destroy(ClothesCategory $category, ClothingItem $item): RedirectResponse
    {
        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }

        $item->delete();

        return back()->with('status', 'Clothing item removed.');
    }
}
