<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller; use App\Models\ServiceCategory; use Illuminate\Http\{JsonResponse,Request}; use Illuminate\Support\Str;
class ServiceCategoryController extends Controller {
 public function index():JsonResponse{return response()->json(['data'=>ServiceCategory::where('is_active',true)->orderBy('sort_order')->get()]);}
 public function store(Request $r):JsonResponse{$this->admin($r);$d=$r->validate(['name'=>'required|string|max:100|unique:service_categories','description'=>'nullable|string|max:1000','icon'=>'nullable|string|max:100','sort_order'=>'nullable|integer|min:0']);$d['slug']=Str::slug($d['name']);return response()->json(['data'=>ServiceCategory::create($d)],201);}
 public function update(Request $r,ServiceCategory $category):JsonResponse{$this->admin($r);$d=$r->validate(['name'=>'sometimes|string|max:100|unique:service_categories,name,'.$category->id,'description'=>'nullable|string|max:1000','icon'=>'nullable|string|max:100','sort_order'=>'nullable|integer|min:0','is_active'=>'sometimes|boolean']);if(isset($d['name']))$d['slug']=Str::slug($d['name']);$category->update($d);return response()->json(['data'=>$category->fresh()]);}
 private function admin(Request $r):void{abort_unless($r->user()->role->value==='admin',403);}
}
