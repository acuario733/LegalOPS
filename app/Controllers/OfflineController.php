<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * Página de fallback offline — ruta pública, sin autenticación, sin layout.
 */
final class OfflineController extends Controller
{
    public function index(Request $request): Response
    {
        // Renderiza offline.php como página completa (sin layout app.php)
        $html = $this->views->render('offline', [], null);

        return Response::html($html, 200);
    }
}
