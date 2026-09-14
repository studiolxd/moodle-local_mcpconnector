# Nota de entrega — el plugin manda su versión al validar la licencia

## Qué cambié

- **`lib.php`**: `local_mcpconnector_validate_license()` ahora manda
  `pluginRelease` y `pluginVersion` en el body de `POST /api/moodle/verify`,
  igual que ya hacía `local_mcpconnector_send_telemetry()` en
  `/api/moodle/telemetry`. Extraje ese cálculo (`core_plugin_manager::instance()
  ->get_plugin_info('local_mcpconnector')` → `release`/`versiondb`) a una
  función nueva, `local_mcpconnector_plugin_version_fields()`, y la reutilizan
  las dos llamadas — no hay dos caminos que puedan desincronizarse.
  - Se manda siempre que se valida: `index.php` llama a
    `local_mcpconnector_validate_license()` tanto en la validación automática
    (línea 75) como al pulsar el botón (línea 136), y las dos pasan por la
    misma función, así que las dos quedan cubiertas sin tocar `index.php`.
  - Los dos campos son opcionales para el panel: si no responde nada nuevo,
    la validación sigue funcionando igual que antes (no cambié la lectura de
    `$result`).
- **`version.php`**: `1.3.2` (release y entero de fecha
  `2026091403`).
- **`CHANGELOG.md`**: entrada `## 1.3.2 — 2026-09-14` con la forma de las
  anteriores.

## Tests

Symlink temporal en `~/Dev/studiolxd/learn/ci-moodle/public/local/mcpconnector`
apuntando a este worktree (retirado al terminar). Hizo falta reinicializar el
entorno PHPUnit (`init.php`) antes de `--buildconfig`, porque estaba
inicializado para otra versión de Moodle.

```
PATH=/opt/homebrew/opt/php@8.4/bin:$PATH php vendor/bin/phpunit --testsuite local_mcpconnector_testsuite
```

Resultado: **OK — 99 tests, 282 assertions** (17 PHPUnit Deprecations, sin
relación con el cambio; ningún test falló). No hay tests directos de
`local_mcpconnector_validate_license()` ni de `local_mcpconnector_send_telemetry()`
en el repo, así que no hubo que actualizar ninguno.

`phpcs --standard=moodle` sobre `lib.php`, `version.php` y `CHANGELOG.md`:
limpio.

## LISTO
