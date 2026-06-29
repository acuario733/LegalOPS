# LegalOPS Cloud V2 — Framework Core

Documentación técnica del microframework PHP 8.2 propio de LegalOPS Cloud V2.

---

## 1. Request Object

El objeto `App\Core\Request` encapsula toda la información de la petición HTTP entrante.

### Métodos disponibles

```php
// Método HTTP y URI
$request->method()        // 'GET', 'POST', 'PUT', 'PATCH', 'DELETE'
$request->uri()           // '/clientes/1'

// Datos de entrada (body POST + JSON unificados)
$request->input()                     // array completo (POST + JSON)
$request->input('nombre')             // campo específico, null si no existe
$request->input('nombre', 'default')  // con valor por defecto

// Query string (GET params)
$request->query()                     // array completo
$request->query('page', 1)           // campo específico con default

// Body POST clásico
$request->post('campo')

// Body JSON (Content-Type: application/json)
$request->json('campo')

// Archivos subidos
$request->file('foto')    // equivalente a $_FILES['foto']
$request->files()         // todos los archivos

// Headers
$request->header('authorization')        // valor o null
$request->header('content-type')
$request->headers()                       // array completo

// Metadatos de la petición
$request->ip()            // IP del cliente (considera X-Forwarded-For)
$request->userAgent()     // User-Agent string
$request->isAjax()        // true si X-Requested-With: XMLHttpRequest
$request->wantsJson()     // true si Accept: application/json
$request->isSafeMethod()  // true para GET, HEAD, OPTIONS

// Parámetros de ruta (ej: /clientes/{id})
$request->route('id')          // '42' (siempre string)
$request->routeParameters()    // array completo de params de ruta
```

### Uso típico por tipo de método del controller

```php
// index() — listado con filtros y paginación
public function index(Request $request): Response
{
    $page    = (int) $request->query('page', 1);
    $filtros = $request->query(); // todos los query params
    // ...
}

// store() — creación desde form o JSON
public function store(Request $request): Response
{
    $data = $request->input(); // array con todos los campos del body
    $nombre = $request->input('nombre'); // campo específico
    // ...
}

// update() — actualización por ID
public function update(Request $request): Response
{
    $id   = (int) $request->route('id');
    $data = $request->input();
    // ...
}
```

---

## 2. Response Object y helpers

### Respuesta JSON

Todos los controllers extienden `App\Core\Controller` que expone `$this->json()`:

```php
// Éxito
return $this->json($data, 'Operación exitosa', 200);

// Creación
return $this->json(['id' => $newId], 'Cliente creado', 201);

// Error con campo de errores
return $this->json(null, 'Datos inválidos', 422, $errors, ok: false);

// No encontrado
return $this->json(null, 'No encontrado', 404, ok: false);
```

**Formato exacto de la respuesta JSON:**

```json
// Éxito
{ "ok": true, "message": "Operación exitosa", "data": { ... } }

// Error de validación
{ "ok": false, "message": "Datos inválidos", "errors": { "email": ["Email inválido"] } }
```

### Vista HTML

```php
// Renderiza resources/views/{view}.php dentro del layout resources/layouts/app.php
return $this->view('clientes/index', ['clientes' => $lista]);

// Con layout diferente
return $this->view('auth/login', [], layout: 'guest');

// Con status code
return $this->view('errors/404', [], status: 404);
```

### Redirección

```php
return $this->redirect('/clientes');
return $this->redirect('/clientes', status: 301);

// Usar Response directamente para headers custom
return Response::redirect('/clientes')->withHeader('X-Custom', 'value');
```

---

## 3. DI Container

### Registro de clases

```php
// Singleton (misma instancia en toda la petición)
$container->singleton(MiServicio::class, static fn (): MiServicio => new MiServicio($pdo));

// Transient (nueva instancia cada vez que se solicita)
$container->bind(MiServicio::class, static fn (): MiServicio => new MiServicio());

// Instancia ya construida
$container->instance(Config::class, $configInstance);
```

### Obtener dependencias

```php
// En un Controller (desde el constructor vía autowiring)
$miServicio = $this->container->get(MiServicio::class);

// El container hace autowiring automático de constructores
// siempre que los parámetros sean tipos no primitivos ya registrados
```

### Ejemplo: registrar un Service nuevo

En `app/Core/App.php`, dentro de `bootstrap()`:

```php
// Singleton con dependencias
$container->singleton(
    HonorarioService::class,
    static fn (Container $c): HonorarioService =>
        new HonorarioService($c->get(PDO::class))
);
```

---

## 4. firmaId() — Multi-tenancy

### De dónde viene

`firmaId()` es provisto por `App\Core\Auth`, que lee la sesión PHP activa.
Al hacer login, el sistema guarda el ID de la firma en `$_SESSION['firma_id']`.

```php
// En el Controller (heredado de Controller.php)
$firmaId = $this->currentFirma(); // int|string|null

// Equivalente directo
$firmaId = $this->auth->firmaId(); // mismo resultado
```

### Qué pasa si el usuario no está autenticado

`firmaId()` devuelve `null`. El middleware `auth` intercepta antes de llegar al controller, devolviendo 401/redirección a login.

### Por qué es crítico

**TODOS los queries de base de datos DEBEN filtrar por `firma_id`.**
Sin este filtro, un usuario de la Firma A podría leer datos de la Firma B.

```php
// CORRECTO — siempre filtrar por firma_id
$stmt = $this->pdo->prepare('SELECT * FROM clientes WHERE firma_id = ? AND id = ?');
$stmt->execute([$firmaId, $id]);

// INCORRECTO — bug de seguridad grave (acceso cross-tenant)
$stmt = $this->pdo->prepare('SELECT * FROM clientes WHERE id = ?');
```

---

## 5. Middleware

### Interfaz

```php
interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
```

### Crear un middleware nuevo

```php
// app/Middleware/MiMiddleware.php
final class MiMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        // Código ANTES del controller
        if (!$condition) {
            return Response::error('No permitido', 403);
        }

        $response = $next($request); // Ejecuta el controller

        // Código DESPUÉS del controller (modificar response, headers, etc.)
        return $response->withHeader('X-Custom-Header', 'value');
    }
}
```

### Registrar en el router

**Global** (todas las rutas):
```php
// En App::bootstrap(), antes de loadRoutes()
$router->middleware(new MiMiddleware());
```

**Por ruta o grupo:**
```php
// Nombre corto: registrar en el switch del middlewareResolver en App.php
'mi_middleware' => new MiMiddleware(),

// Uso en rutas:
$router->get('/ruta', [Controller::class, 'metodo'], ['mi_middleware']);
$router->group('/admin', ['auth', 'mi_middleware'], function (Router $r) {
    $r->get('/dashboard', [AdminController::class, 'index']);
});
```

---

## 6. Routing

### Definir rutas

Las rutas se definen en `routes/web.php` (y `api.php`, `portal.php`, `superadmin.php`).
Cada archivo retorna un callable que recibe el Router:

```php
// routes/web.php
return static function (Router $router): void {

    // Métodos disponibles: get, post, put, patch, delete
    $router->get('/clientes', [ClienteController::class, 'index']);
    $router->post('/clientes', [ClienteController::class, 'store'], ['plan:clientes']);
    $router->patch('/clientes/{id}', [ClienteController::class, 'update']);
    $router->delete('/clientes/{id}', [ClienteController::class, 'destroy']);

    // Grupos con middleware compartido
    $router->group('', ['auth', 'firma'], static function (Router $router): void {
        $router->get('/dashboard', [DashboardController::class, 'index']);
    });
};
```

### Parámetros de ruta

```php
// Definición
$router->get('/clientes/{id}', [ClienteController::class, 'show']);

// En el controller
public function show(Request $request): Response
{
    $id = (int) $request->route('id'); // siempre string, castear según necesidad
    // ...
}
```

### Middlewares disponibles (registrados en App.php)

| Alias | Descripción |
|---|---|
| `auth` | Requiere sesión autenticada |
| `guest` | Solo para usuarios NO autenticados |
| `firma` | Requiere firma activa en sesión |
| `csrf` | Valida token CSRF |
| `commercial` | Valida estado comercial de la firma |
| `legal_pending` | Redirige si hay términos pendientes de aceptar |
| `permission:codigo` | Valida permiso específico (ej: `permission:clientes.ver`) |
| `plan:recurso` | Valida límite del plan (ej: `plan:clientes`) |
| `rate_limit` | Rate limiting por IP (login) |
| `reveal_limit` | Rate limiting para revelar datos sensibles |

---

## 7. Convenciones del proyecto

### Nomenclatura

| Elemento | Convención | Ejemplo |
|---|---|---|
| Clases PHP | PascalCase | `ClienteController`, `HonorarioService` |
| Métodos PHP | camelCase | `findByFirma()`, `getRetryAfter()` |
| Archivos PHP | PascalCase.php | `ClienteController.php` |
| Columnas BD | snake_case | `nombre_razon_social`, `firma_id` |
| Rutas URL | kebab-case | `/mis-casos`, `/portal-cliente` |
| Vistas | snake_case/ | `clientes/form.php` |

### Template de Controller completo

```php
// app/Controllers/ExampleController.php
final class ExampleController extends Controller
{
    public function index(Request $request): Response
    {
        $firmaId = (int) $this->currentFirma();
        $items = $this->container->get(ExampleService::class)->list($firmaId);
        return $this->view('example/index', compact('items'));
    }

    public function store(Request $request): Response
    {
        $firmaId = (int) $this->currentFirma();
        $data = $request->input();

        // Validar
        $errors = $this->container->get(\App\Core\Validator::class)->validate($data, [
            'titulo' => 'required|maxLength:255',
        ]);
        if (!empty($errors)) {
            return $this->json(null, 'Datos inválidos', 422, $errors, ok: false);
        }

        $id = $this->container->get(ExampleService::class)->create($firmaId, $data);
        return $this->json(['id' => $id], 'Creado exitosamente', 201);
    }
}
```

### Cómo agregar un módulo nuevo (Honorarios)

1. **Repository** → `app/Repositories/HonorarioRepository.php` extiende `BaseRepository`
2. **Controller** → `app/Controllers/HonorarioController.php` extiende `Controller`
3. **Registrar en DI** → En `App::bootstrap()`, `$container->singleton(HonorarioRepository::class, ...)`
4. **Rutas** → Agregar en `routes/web.php` dentro del grupo `['auth', 'firma']`
5. **Vistas** → `resources/views/honorarios/index.php`, `form.php`
6. **Migración** → Crear `database/migrations/XXXX_create_honorarios.sql` con `-- @up` y `-- @down`
7. **Tests** → `tests/Unit/Controllers/HonorarioControllerTest.php`
