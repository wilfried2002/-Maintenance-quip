<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrganisationController extends Controller
{
    public function index(): Response
    {
        $organisations = Organisation::withCount('users')
            ->orderBy('name')
            ->get();

        return Inertia::render('Organisations/Index', [
            'organisations' => $organisations,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:organisations,code'],
            'devise' => ['required', Rule::in(array_keys(Organisation::DEVISES))],
        ]);

        $organisation = Organisation::create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'devise' => $data['devise'],
            'is_active' => true,
        ]);

        return back()->with('status', "Organisation « {$organisation->name} » créée.");
    }

    public function update(Request $request, Organisation $organisation): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('organisations', 'code')->ignore($organisation->id)],
            'devise' => ['required', Rule::in(array_keys(Organisation::DEVISES))],
            'is_active' => ['boolean'],
        ]);

        $organisation->update([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'devise' => $data['devise'],
            'is_active' => $data['is_active'] ?? $organisation->is_active,
        ]);

        return back()->with('status', 'Organisation mise à jour.');
    }
}
