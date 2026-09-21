<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentHotel;
use App\Http\Controllers\Controller;
use App\Models\HotelKnowledgeItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KnowledgeItemController extends Controller
{
    use ResolvesCurrentHotel;

    public function index(Request $request): View
    {
        $hotel = $this->currentHotel($request);

        $items = $hotel->knowledgeItems()->orderBy('category')->orderBy('sort_order')->get();

        return view('admin.knowledge-items.index', compact('hotel', 'items'));
    }

    public function create(Request $request): View
    {
        $hotel = $this->currentHotel($request);

        return view('admin.knowledge-items.form', [
            'hotel' => $hotel,
            'item' => new HotelKnowledgeItem(['category' => HotelKnowledgeItem::CATEGORY_GENERAL, 'is_active' => true]),
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $hotel = $this->currentHotel($request);

        $item = $hotel->knowledgeItems()->create($this->validated($request));

        return redirect()->route('admin.knowledge-items.index')->with('status', "Knowledge item \"{$item->title}\" created.");
    }

    public function edit(Request $request, HotelKnowledgeItem $knowledgeItem): View
    {
        $hotel = $this->currentHotel($request);
        abort_if($knowledgeItem->hotel_id !== $hotel->id, 404);

        return view('admin.knowledge-items.form', [
            'hotel' => $hotel,
            'item' => $knowledgeItem,
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, HotelKnowledgeItem $knowledgeItem): RedirectResponse
    {
        $hotel = $this->currentHotel($request);
        abort_if($knowledgeItem->hotel_id !== $hotel->id, 404);

        $knowledgeItem->update($this->validated($request));

        return redirect()->route('admin.knowledge-items.index')->with('status', "Knowledge item \"{$knowledgeItem->title}\" updated.");
    }

    public function destroy(Request $request, HotelKnowledgeItem $knowledgeItem): RedirectResponse
    {
        $hotel = $this->currentHotel($request);
        abort_if($knowledgeItem->hotel_id !== $hotel->id, 404);

        $knowledgeItem->delete();

        return redirect()->route('admin.knowledge-items.index')->with('status', 'Knowledge item deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'in:'.implode(',', $this->categories())],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'translations.en.title' => ['nullable', 'string'],
            'translations.en.body' => ['nullable', 'string'],
            'translations.ja.title' => ['nullable', 'string'],
            'translations.ja.body' => ['nullable', 'string'],
            'tags' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url:http,https', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'category' => $data['category'],
            'title' => $data['title'],
            'body' => $data['body'],
            'translations' => [
                'en' => ['title' => $data['translations']['en']['title'] ?? null, 'body' => $data['translations']['en']['body'] ?? null],
                'ja' => ['title' => $data['translations']['ja']['title'] ?? null, 'body' => $data['translations']['ja']['body'] ?? null],
            ],
            'tags' => collect(explode(',', (string) ($data['tags'] ?? '')))->map(fn ($t) => trim($t))->filter()->values()->all(),
            'image_url' => $data['image_url'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    private function categories(): array
    {
        return [
            HotelKnowledgeItem::CATEGORY_GENERAL,
            HotelKnowledgeItem::CATEGORY_FACILITIES,
            HotelKnowledgeItem::CATEGORY_POLICIES,
            HotelKnowledgeItem::CATEGORY_DINING,
            HotelKnowledgeItem::CATEGORY_TRANSPORT,
            HotelKnowledgeItem::CATEGORY_FAQ,
        ];
    }
}
