<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminActionRequest;
use App\Models\Category;
use App\Models\MarketplaceSetting;
use App\Models\ServiceArea;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CatalogController extends Controller
{
    private function model(string $kind): string
    {
        return $kind === 'categories' ? Category::class : ServiceArea::class;
    }

    public function store(AdminActionRequest $request, string $kind, AuditService $audit)
    {
        $data = $request->validated();
        $model = $this->model($kind);
        DB::transaction(function () use ($data, $model, $audit) {
            MarketplaceSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            $name = trim($data['name']);
            $normalized = Str::lower($name);
            if ($model::where('normalized_name', $normalized)->exists()) {
                throw ValidationException::withMessages(['name' => 'This name already exists.']);
            }
            $item = $model::create(['name' => $name, 'normalized_name' => $normalized, 'active' => true]);
            $audit->record('Added '.class_basename($model), $item->name);
        }, 3);

        return back()->with('status', 'Added successfully.');
    }

    public function toggle(AdminActionRequest $request, string $kind, int $id, AuditService $audit)
    {
        $data = $request->validated();
        $model = $this->model($kind);
        DB::transaction(function () use ($model, $id, $data, $audit) {
            MarketplaceSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            $item = $model::whereKey($id)->lockForUpdate()->firstOrFail();
            $item->active = (bool) $data['active'];
            $item->save();
            $audit->record($item->active ? 'Enabled catalog item' : 'Disabled catalog item', $item->name);
        }, 3);

        return back()->with('status','Catalog updated.');
    }
}
