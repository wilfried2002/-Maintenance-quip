<?php

namespace App\Http\Controllers;

use App\Models\Fournisseur;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FournisseurController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Fournisseurs/Index', [
            'fournisseurs' => Fournisseur::orderBy('nom')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'contact_nom' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        Fournisseur::create($data);

        return back()->with('status', 'Fournisseur enregistré.');
    }

    public function update(Request $request, Fournisseur $fournisseur): RedirectResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'contact_nom' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $fournisseur->update($data);

        return back()->with('status', 'Fournisseur mis à jour.');
    }

    public function destroy(Fournisseur $fournisseur): RedirectResponse
    {
        $fournisseur->delete();

        return back()->with('status', 'Fournisseur supprimé.');
    }
}
