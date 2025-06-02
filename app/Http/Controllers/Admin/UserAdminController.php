<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserAdminController extends Controller
{
    /**
     * Liste des utilisateurs
     */
    public function index(Request $request)
    {
        $users = User::with(['roles', 'permissions'])
            ->withCount('articles') // Compter les articles créés
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Formulaire de création
     */
    public function create()
    {
        $roles = Role::all();
        $permissions = Permission::all();

        return Inertia::render('Admin/Users/Create', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Créer un utilisateur
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'points' => 'integer|min:0|max:10000',
            'roles' => 'array',
            'permissions' => 'array',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'points' => $request->points ?? 0,
            'email_verified_at' => now(),
        ]);

        // Assigner les rôles et permissions
        if ($request->roles) {
            $user->assignRole($request->roles);
        }

        if ($request->permissions) {
            $user->givePermissionTo($request->permissions);
        }

        return redirect()->route('admin.users')->with('success', 'Utilisateur créé avec succès');
    }

    /**
     * Formulaire de modification
     */
    public function edit(User $user)
    {
        $roles = Role::all();
        $permissions = Permission::all();
        
        $user->load(['roles', 'permissions']);

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Mettre à jour un utilisateur
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'roles' => 'array',
            'permissions' => 'array',
        ]);

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        // Synchroniser les rôles et permissions
        $user->syncRoles($request->roles ?? []);
        $user->syncPermissions($request->permissions ?? []);

        return redirect()->route('admin.users')->with('success', 'Utilisateur mis à jour avec succès');
    }

    /**
     * Supprimer un utilisateur
     */
    public function destroy(User $user)
    {
        // Empêcher la suppression de son propre compte
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte');
        }

        $user->delete();

        return back()->with('success', 'Utilisateur supprimé avec succès');
    }

    /**
     * Ajouter des points à un utilisateur
     */
    public function addPoints(Request $request, User $user)
    {
        $request->validate([
            'points' => 'required|integer|min:1|max:1000',
        ]);

        $user->increment('points', $request->points);

        return back()->with('success', "Points ajoutés à {$user->name} avec succès");
    }

    /**
     * Retirer des points à un utilisateur
     */
    public function removePoints(Request $request, User $user)
    {
        $request->validate([
            'points' => 'required|integer|min:1',
        ]);

        // S'assurer que l'utilisateur garde au moins 0 points
        $newPoints = max(0, $user->points - $request->points);
        $user->update(['points' => $newPoints]);

        return back()->with('success', "Points retirés à {$user->name} avec succès");
    }
} 