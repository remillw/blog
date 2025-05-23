<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::with(['sites' => function ($query) {
            $query->where('user_id', Auth::id());
        }])
            ->whereHas('sites', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->get();

        $userSites = Site::where('user_id', Auth::id())->get(['id', 'name']);

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'availableSites' => $userSites,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sites' => 'required|array|min:1',
            'sites.*' => 'exists:sites,id',
        ]);

        // Vérifier que tous les sites appartiennent à l'utilisateur
        $userSiteIds = Site::where('user_id', Auth::id())->pluck('id')->toArray();
        $requestedSiteIds = $request->sites;
        
        if (array_diff($requestedSiteIds, $userSiteIds)) {
            return back()->withErrors(['sites' => 'You can only assign categories to your own sites.']);
        }

        $category = Category::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        $category->sites()->sync($request->sites);

        return back()->with('success', 'Category created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        // Vérifier que la catégorie appartient à l'utilisateur (via ses sites)
        $userSiteIds = Site::where('user_id', Auth::id())->pluck('id')->toArray();
        $categorySiteIds = $category->sites()->pluck('sites.id')->toArray();
        
        if (!array_intersect($categorySiteIds, $userSiteIds)) {
            return back()->withErrors(['error' => 'You can only update your own categories.']);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sites' => 'required|array|min:1',
            'sites.*' => 'exists:sites,id',
        ]);

        // Vérifier que tous les sites appartiennent à l'utilisateur
        $requestedSiteIds = $request->sites;
        
        if (array_diff($requestedSiteIds, $userSiteIds)) {
            return back()->withErrors(['sites' => 'You can only assign categories to your own sites.']);
        }

        $category->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        $category->sites()->sync($request->sites);

        return back()->with('success', 'Category updated successfully.');
    }

    /**
     * Get categories for a specific site.
     */
    public function getBySite($siteId)
    {
        // Vérifier que le site appartient à l'utilisateur
        $site = Site::where('id', $siteId)
            ->where('user_id', Auth::id())
            ->first();

        if (!$site) {
            return response()->json(['error' => 'Site not found or unauthorized'], 404);
        }

        $categories = $site->categories()->get(['categories.id', 'categories.name']);

        return response()->json($categories);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        // Vérifier que la catégorie appartient à l'utilisateur (via ses sites)
        $userSiteIds = Site::where('user_id', Auth::id())->pluck('id')->toArray();
        $categorySiteIds = $category->sites()->pluck('sites.id')->toArray();
        
        if (!array_intersect($categorySiteIds, $userSiteIds)) {
            return back()->withErrors(['error' => 'You can only delete your own categories.']);
        }

        $category->delete();

        return back()->with('success', 'Category deleted successfully.');
    }
}
