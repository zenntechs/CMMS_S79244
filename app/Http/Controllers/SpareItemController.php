<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdjustSpareRequest;
use App\Http\Requests\ReceiptSpareRequest;
use App\Http\Requests\StoreSpareItemRequest;
use App\Http\Requests\UpdateSpareItemRequest;
use App\Models\AuditEvent;
use App\Models\SpareItem;
use App\Services\SpareMovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SpareItemController extends Controller
{
    public function index(): View
    {
        $items = SpareItem::query()
            ->when(request('search'), fn ($q, $term) => $q->where(function ($inner) use ($term): void {
                $inner->where('code', 'ilike', "%{$term}%")
                    ->orWhere('description', 'ilike', "%{$term}%")
                    ->orWhere('unit', 'ilike', "%{$term}%");
            }))
            ->when(request('status') === 'low', fn ($q) => $q->whereColumn('quantity_on_hand', '<', 'reorder_level'))
            ->when(request('status') === 'ok', fn ($q) => $q->whereColumn('quantity_on_hand', '>=', 'reorder_level'))
            ->when(request('active') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when(request('active') === 'active', fn ($q) => $q->where('is_active', true))
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        $lowStockCount = SpareItem::whereColumn('quantity_on_hand', '<', 'reorder_level')
            ->where('is_active', true)
            ->count();

        return view('spares.index', compact('items', 'lowStockCount'));
    }

    public function show(SpareItem $spareItem): View
    {
        $spareItem->load([
            'movements' => fn ($query) => $query
                ->with(['workOrder', 'actor'])
                ->orderByDesc('occurred_at')
                ->orderByDesc('id'),
        ]);

        return view('spares.show', compact('spareItem'));
    }

    public function create(): View
    {
        $this->authorize('create', SpareItem::class);

        return view('spares.create');
    }

    public function store(StoreSpareItemRequest $request): RedirectResponse
    {
        $item = DB::transaction(function () use ($request): SpareItem {
            $item = SpareItem::create([
                'code' => $request->validated()['code'],
                'description' => $request->validated()['description'],
                'unit' => $request->validated()['unit'],
                'reorder_level' => $request->validated()['reorder_level'],
                'is_active' => $request->boolean('is_active', true),
                'quantity_on_hand' => 0,
            ]);

            AuditEvent::create([
                'actor_id' => $request->user()->id,
                'action' => 'spare_item.created',
                'entity_type' => 'spare_item',
                'entity_id' => $item->id,
                'before' => null,
                'after' => $request->validated() + ['quantity_on_hand' => '0.00'],
                'reason' => null,
                'occurred_at' => now(),
            ]);

            return $item;
        });

        return redirect()->route('spares.index')->with('status', 'Spare item created.');
    }

    public function edit(SpareItem $spareItem): View
    {
        $this->authorize('update', $spareItem);

        return view('spares.edit', compact('spareItem'));
    }

    public function update(UpdateSpareItemRequest $request, SpareItem $spareItem): RedirectResponse
    {
        DB::transaction(function () use ($spareItem, $request): void {
            $before = $spareItem->getAttributes();
            $spareItem->update([
                'code' => $request->validated()['code'],
                'description' => $request->validated()['description'],
                'unit' => $request->validated()['unit'],
                'reorder_level' => $request->validated()['reorder_level'],
                'is_active' => $request->boolean('is_active'),
            ]);

            AuditEvent::create([
                'actor_id' => $request->user()->id,
                'action' => 'spare_item.updated',
                'entity_type' => 'spare_item',
                'entity_id' => $spareItem->id,
                'before' => $before,
                'after' => $spareItem->getChanges() ?: $request->validated(),
                'reason' => null,
                'occurred_at' => now(),
            ]);
        });

        return redirect()->route('spares.show', $spareItem)->with('status', 'Spare item updated.');
    }

    public function receive(ReceiptSpareRequest $request, SpareItem $spareItem, SpareMovementService $service): RedirectResponse
    {
        $this->authorize('receive', $spareItem);

        try {
            $service->receipt($spareItem, $request->validated()['quantity'], $request->user(), $request->validated()['reason'] ?? null);
        } catch (ValidationException $exception) {
            return redirect()->back()->withInput()->withErrors($exception->errors());
        }

        return redirect()->route('spares.show', $spareItem)->with('status', 'Stock received.');
    }

    public function adjust(AdjustSpareRequest $request, SpareItem $spareItem, SpareMovementService $service): RedirectResponse
    {
        $this->authorize('adjust', $spareItem);

        try {
            $service->adjust($spareItem, $request->validated()['quantity_delta'], $request->user(), $request->validated()['reason']);
        } catch (ValidationException $exception) {
            return redirect()->back()->withInput()->withErrors($exception->errors());
        }

        return redirect()->route('spares.show', $spareItem)->with('status', 'Stock adjusted.');
    }
}
