# Runbook de pruebas — local_mcpconnector 2.0 contra el panel v2

Checklist para cada ronda de pruebas en el Moodle local. El panel dev corre en
`http://localhost:3000` (`pnpm dev`) y el server MCP en `http://localhost:3005`
(`pnpm mcp:dev`); ambos contra la BD `moodlemcp` local.

## Preparación (una vez)

1. Symlink del plugin (iteración instantánea) — la carpeta destino DEBE
   llamarse `mcpconnector` (frankenstyle `local_mcpconnector`) y el origen es la
   carpeta `plugin/` (ahí está `version.php`). La ruta depende de la versión de
   Moodle (el plugin soporta **4.2–5.1**): Moodle **5.0** movió el dirroot a
   `public/`, así que en 5.x los plugins van bajo `public/local/`.
   - **Moodle 4.x** (dirroot = raíz del checkout):
     `ln -s /Users/suvi/Dev/moodlemcp/plugin <moodle>/local/mcpconnector`
   - **Moodle 5.x** (dirroot = `public/`):
     `ln -s /Users/suvi/Dev/moodlemcp/plugin <moodle>/public/local/mcpconnector`
   Ej. real en este Mac (Moodle 5.2):
   `ln -s /Users/suvi/Dev/moodlemcp/plugin ~/Dev/studiolxd/learn/moodle/public/local/mcpconnector`
2. Panel dev arriba (`pnpm dev`) y sesión iniciada como owner/admin de una
   organización compartida.
3. En el panel: **Organización → Moodle** → conectar con la URL del Moodle
   local (p. ej. `http://localhost:8888`) → copiar la **clave de licencia** y
   el **secreto de panel** (se muestran UNA vez).

## Cada iteración

1. Purga cachés de Moodle (Site administration → Development → Purge caches).
   Si el cambio toca `db/` o `version.php`: visita Notifications y ejecuta el
   upgrade.
2. **Licencia**: pestaña License del plugin → pegar URL del panel
   (`http://localhost:3000`), licencia y secreto → Validate → estado `ok`.
   - Negativos: secreto incorrecto → `invalid_credentials`; URL de Moodle que
     no coincide con la conexión → `url_mismatch`.
3. **Alta de clave**: pestaña Users → asignar un usuario a un servicio →
   comprobar:
   - En el panel (`/organization/moodle/keys`): clave nueva con
     `createdBy: moodle`, last4 correcto.
   - Email al usuario con el valor `mcpk_...` (si auto-email activo).
   - Tabla local del plugin (pestaña Keys) muestra la clave con su estado.
4. **Cliente MCP real**: con el valor emailado,
   `claude mcp add --transport http moodle http://localhost:3005/mcp --header "Authorization: Bearer mcpk_..."`
   (o el inspector MCP) → lista tools → ejecuta `core_webservice_get_site_info`
   contra el Moodle real.
5. **Ciclo de vida desde el plugin** (pestaña Keys) — revoke y regenerate
   muestran ahora una **página de confirmación** antes de actuar:
   - Suspend → la siguiente llamada MCP falla (`suspended`); Activate la
     restaura. (Sin confirmación — es reversible.)
   - Revoke → confirmar → corte definitivo; el panel muestra `Revoked`.
   - Regenerate → confirmar → clave vieja revocada + clave nueva emailada; la
     vieja no funciona, la nueva sí. Si el panel falla al revocar, se aborta
     con el error real (no deja al usuario sin clave).
   - Refresh from panel → revocar una clave DESDE el panel y refrescar: el
     estado local se reconcilia.
   - Tras cualquier acción, la página redirige (PRG): refrescar el navegador
     NO repite la acción.
6. **Flujos automáticos**: borrar un usuario de Moodle (o quitarle el rol) →
   el adhoc task revoca sus claves en el panel (verificar en
   `/organization/moodle/keys` y en el audit log de la organización).

## Cierre de ronda

- Anotar cualquier error PHP (Site administration → Reports → Logs o
  `error_log`) y el comportamiento observado; reportarlo en la sesión de
  trabajo para la siguiente iteración.
