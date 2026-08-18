<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PieceController extends Controller
{
    /**
     * Le stock est cloisonné par module (voir HandlesPieces) : cette page n'est plus
     * qu'un point d'entrée avec des tuiles vers le stock de chaque module équipement.
     */
    public function index(): Response
    {
        return Inertia::render('Pieces/Index');
    }
}
