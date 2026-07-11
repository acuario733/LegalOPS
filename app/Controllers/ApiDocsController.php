<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

final class ApiDocsController extends Controller
{
    public function index(Request $request): Response
    {
        return Response::html(<<<'HTML'
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>LegalOPS API v1</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
</head>
<body>
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
<script>SwaggerUIBundle({url:'/api/docs/openapi.json',dom_id:'#swagger-ui',deepLinking:true,persistAuthorization:true});</script>
</body>
</html>
HTML);
    }

    public function spec(Request $request): Response
    {
        return Response::file(dirname(__DIR__, 2) . '/public/api/openapi.json', 'application/json; charset=UTF-8');
    }
}
