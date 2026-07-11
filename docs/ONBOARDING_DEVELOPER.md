# LegalOPS Cloud V2 — Onboarding para Developers

## Setup del entorno en 10 minutos

```bash
# 1. Clonar y entrar al proyecto
git clone <repo> legalops && cd legalops

# 2. Instalar dependencias PHP
composer install

# 3. Copiar .env y ajustar credenciales de BD
cp .env.example .env
# Editar DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD

# 4. Crear la base de datos y correr migraciones
mysql -u root -p -e "CREATE DATABASE legalops CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate  # o bien: usar el MigrationRunner integrado
# En realidad: el propio sistema carga migraciones desde database/migrations/

# 5. Instalar dependencias JS (opcional para desarrollo)
npm install

# 6. Levantar el servidor de desarrollo
php -S localhost:8000 -t public/

# 7. Correr los tests para verificar
vendor/bin/phpunit
```

Abrir: http://localhost:8000

---

## Primer feature: agregar campo `notas` a Clientes (end-to-end)

### Paso 1: Migración SQL

Crear `database/migrations/XXXX_add_notas_to_clientes.sql`:

```sql
-- @up
ALTER TABLE clientes ADD COLUMN notas TEXT NULL AFTER telefono;

-- @down
ALTER TABLE clientes DROP COLUMN notas;
```

### Paso 2: Repository — agregar al SELECT

En `app/Repositories/ClienteRepository.php`, incluir `notas` en los campos del SELECT.

### Paso 3: Vista del formulario

En `resources/views/clientes/form.php`:

```php
<textarea name="notas"><?= h($cliente['notas'] ?? '') ?></textarea>
```

**Siempre usar `h()` para escapar output. Nunca `<?= $variable ?>` sin escapar.**

### Paso 4: Guardar el campo

En el Controller (`store` y `update`), `$request->input()` ya incluye `notas` automáticamente.
Solo asegurarse de que el Service lo pase al Repository.

### Paso 5: Test

Agregar en `tests/Unit/Controllers/ClienteControllerTest.php`:

```php
public function test_store_saves_notas(): void
{
    // ...
}
```

---

## Cómo correr los tests

```bash
# Todos los tests
vendor/bin/phpunit

# Solo una suite
vendor/bin/phpunit tests/Unit/Controllers/

# Con cobertura (requiere Xdebug)
vendor/bin/phpunit --coverage-text

# Tests JavaScript
npx jest

# E2E (requiere la app corriendo en localhost:8000)
npx cypress run
```

---

## Dónde encontrar qué

| ¿Qué necesito? | ¿Dónde está? |
|---|---|
| Lógica de negocio | `app/Repositories/` |
| Respuestas HTTP, validación | `app/Controllers/` |
| Configuración del sistema | `config/` + `.env` |
| Rutas | `routes/web.php`, `routes/api.php` |
| Vistas HTML | `resources/views/` |
| Layout principal | `resources/layouts/app.php` |
| Assets JS/CSS | `public/assets/` |
| Migraciones SQL | `database/migrations/` |
| Tests PHPUnit | `tests/Unit/` |
| Tests E2E | `cypress/e2e/` |
| Documentación técnica | `docs/FRAMEWORK_CORE.md` |
| Variables de entorno | `.env` (nunca commitear) |

---

## Multi-tenancy — la regla más importante

**Cada query DEBE filtrar por `firma_id`.** Sin esto, los datos de una firma son visibles para otras.

```php
// CORRECTO
$stmt = $pdo->prepare('SELECT * FROM clientes WHERE firma_id = ? AND id = ?');
$stmt->execute([$firmaId, $id]);

// INCORRECTO — bug de seguridad crítico
$stmt = $pdo->prepare('SELECT * FROM clientes WHERE id = ?');
```

Obtener el `firma_id` en un controller: `$this->currentFirma()`.
