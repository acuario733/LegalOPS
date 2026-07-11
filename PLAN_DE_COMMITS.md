# Plan de commits — organizar_commits.sh

Contexto: el repo tiene 235 archivos modificados/nuevos sin commitear, acumulados
sin ningún commit desde hace ~11 días. `organizar_commits.sh` los agrupa en 23
commits atómicos, en este orden (las dependencias van antes que lo que las usa):

| # | Commit | Contenido |
|---|--------|-----------|
| 1 | `chore(gitignore)` | Ignora `storage/cache/` (cache de PHPStan) |
| 2 | `chore(tooling)` | Docker, PHPCS, PHPStan, CI (`.github`), composer, phpunit.xml |
| 3 | `refactor(core)` | Núcleo MVC: App, Container, Database, Router, Session, TenantContext, etc. |
| 4 | `feat(observabilidad)` | CORS/timing middleware, logging, métricas (fase 9-10) |
| 5 | `feat(storage)` | Almacenamiento S3 (fase 1) |
| 6 | `feat(jobs)` | Cola de trabajos en segundo plano (fase 1) |
| 7 | `feat(documentos)` | Generación de PDF/DOCX para honorarios (fase 1) |
| 8 | `feat(casos)` | Etapas de caso y numeración automática (fase 2) |
| 9 | `feat(crm)` | Contactos con deduplicación (fase 2 y 7) |
| 10 | `feat(honorarios)` | Líneas de honorario + endpoint API (fase 2) |
| 11 | `feat(webhooks)` | Webhooks salientes (fase 2) |
| 12 | `feat(calendario)` | Calendario jurídico + Google Calendar (fase 2) |
| 13 | `feat(trust)` | Cuentas fiduciarias IOLTA + reportes (fase 3) |
| 14 | `feat(facturacion)` | Billing completo, Stripe, anticipos (fases 4-5) |
| 15 | `feat(api)` | API pública v1 con scopes y rate limiting |
| 16 | `feat(superadmin)` | Roles/usuarios de plataforma y monitoreo |
| 17 | `feat(portal)` | Autenticación propia del portal de clientes |
| 18 | `feat(firma-electronica)` | Integración DocuSign |
| 19 | `feat(gdpr)` | Servicio de cumplimiento GDPR |
| 20 | `feat(ui)` | Sistema de diseño (sidebar, topbar, componentes) |
| 21 | `refactor` | Controladores/servicios/validadores existentes actualizados (incluye migración 0525 documentos) |
| 22 | `test` | Pruebas unitarias faltantes (RBAC, cifrado, plantillas) |
| 23 | `docs` | Actualiza `docs/IMPLEMENTACION_FASES.md` |

## Cómo ejecutarlo

```bash
cd "02_CODIGO_FUENTE/legalops"
bash organizar_commits.sh --dry-run   # 1) revisa qué haría, sin tocar nada
bash organizar_commits.sh             # 2) ejecuta de verdad
git log --oneline -25                 # 3) confirma los 23 commits
git status                            # 4) debería quedar limpio (salvo .phpunit.result.cache)
```

## Advertencias

- El script primero revisa si existe `.git/index.lock` (el que detectamos hoy) y
  te pregunta antes de borrarlo. **Antes de confirmar, cierra cualquier otra
  terminal, IDE o proceso git que pueda estar corriendo** — borrar el lock
  mientras otro proceso escribe puede corromper el índice.
- Usa `set -e`: si un `git commit` falla, el script se detiene ahí mismo para
  que revises manualmente en vez de seguir a ciegas.
- `.phpunit.result.cache` se deja fuera a propósito (es un archivo de caché que
  no debería estar versionado). Si quieres sacarlo del repo por completo:
  ```bash
  git rm --cached .phpunit.result.cache
  echo "/.phpunit.result.cache" >> .gitignore
  git commit -m "chore: deja de versionar cache de phpunit"
  ```
- Los mensajes de commit agrupan por módulo/fase basándome en nombres de
  archivo y migraciones (`phase1_`, `phase2_`, etc.). Si algún grupo no
  corresponde con lo que realmente hiciste, es más fácil corregirlo con
  `git commit --amend` o `git reset` justo después de ese commit que rehacer
  todo el script.
- Nota de alcance: el commit 18 (`firma-electronica` / DocuSign) contradice lo
  que dice el Documento Maestro (marca firma electrónica como "fuera de
  alcance V1"). Vale la pena decidir conscientemente si ese código se queda o
  se pospone antes de comitearlo.
