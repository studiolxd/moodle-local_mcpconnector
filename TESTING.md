# Runbook de pruebas — local_mcpconnector contra el panel lmsmcp

Checklist para cada ronda de pruebas en el Moodle local. El panel dev corre en
`http://localhost:3000` (`pnpm dev`) y el server MCP en `http://localhost:3005`
(`pnpm mcp:dev`); ambos contra la BD `lmsmcp` local.

## Preparación (una vez)

1. Symlink del plugin (iteración instantánea) — la carpeta destino DEBE
   llamarse `mcpconnector` (frankenstyle `local_mcpconnector`) y el origen es
   la raíz de este repo (ahí está `version.php`). La ruta depende de la
   versión de Moodle (el plugin soporta **4.2–5.1**): Moodle **5.0** movió el
   dirroot a `public/`, así que en 5.x los plugins van bajo `public/local/`.
   - **Moodle 4.x** (dirroot = raíz del checkout):
     `ln -s ~/Dev/studiolxd/moodle-local_mcpconnector <moodle>/local/mcpconnector`
   - **Moodle 5.x** (dirroot = `public/`):
     `ln -s ~/Dev/studiolxd/moodle-local_mcpconnector <moodle>/public/local/mcpconnector`
   Ej. real en este Mac (Moodle 5.2):
   `ln -s ~/Dev/studiolxd/moodle-local_mcpconnector ~/Dev/studiolxd/learn/moodle/public/local/mcpconnector`
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
3. **Alta de clave**: pestaña Users → asignar un usuario a un servicio → la
   página redirige SIN esperar al correo (avisa de que se envía en segundo
   plano); el correo sale al ejecutar el cron. Comprobar:
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

## Identidad del chat (pestaña Chat)

1. Entra en la pestaña **Chat** con la licencia ya validada. Estado inicial:
   "todavía no se ha creado". Nada debe haberse creado al instalar ni al
   emparejar.
2. Elige un rol del desplegable (solo salen roles que existen en el sitio) y
   pulsa **Crear la identidad del chat** → página de confirmación con el
   aviso de alcance → confirmar. Comprobar:
   - Usuario nuevo en Moodle: `mcpconnector_chat`, auth **`manual`** (NO
     `nologin`: Moodle lo trata como cuenta deshabilitada y los servicios web
     rechazan su token con `wsaccessusernologin`), contraseña `not cached`,
     correo `@mcpconnector.invalid`, sin poder iniciar sesión.
   - Rol elegido asignado **a nivel de sistema** (Usuarios → Permisos →
     Asignar roles de sistema).
   - Token de servicios web para el servicio de ese rol.
   - En el panel: clave nueva marcada como **de servicio** y designada como
     identidad del chat (si la conexión no tenía ninguna designada a mano).
   - El chat del panel responde sin que nadie haya pegado nada.
3. **Comprobar ahora** → el estado se verifica de verdad contra Moodle y
   contra el panel; la fecha de comprobación se actualiza. Incluye una
   **llamada real de servicio web** con el token del chat
   (`core_webservice_get_site_info` contra el propio sitio): debe decir que el
   token funciona, y si no, enseñar el código de error de Moodle.
4. **Cuenta rota de la 1.3.0** (quien venga de esa versión, o simulándolo con
   `UPDATE mdl_user SET auth='nologin' WHERE username='mcpconnector_chat'`):
   Comprobar ahora debe marcar el método de autenticación como deshabilitado y
   el token como no funcional; **Regenerar** debe arreglar *esa misma* cuenta
   (mismo id, mismo rol) y no crear una segunda.
5. **Regenerar** — probar cada rotura por separado, y después de cada una
   pulsar Comprobar (el estado debe decir qué falta) y Regenerar (debe
   volver a verde, y el chat volver a funcionar):
   - Borrar el usuario de servicio en Moodle.
   - Quitarle el rol a nivel de sistema.
   - Borrar su token (Servicios web → Gestionar tokens).
   - Borrar o revocar la clave en el panel. Tras regenerar, la clave anterior
     debe quedar revocada en el panel, no huérfana.
6. **Cambiar de rol**: elegir otro rol y regenerar → el rol anterior se
   retira, el nuevo se asigna, y la clave del panel se sustituye.
7. **Que no se cruce con los usuarios normales**: el usuario de servicio NO
   debe aparecer en los selectores de la pestaña Users, ni salir en Keys, ni
   recibir correos, ni cambiar tras ejecutar el cron (`php admin/cli/cron.php`
   con auto-sync activado).

## Cambio de panel (migración de claves)

1. Con claves ya emitidas contra el panel A, cambia en **License** la URL del
   panel (o la licencia) por las del panel B y valida.
2. El mensaje de validación avisa del cambio, y **License** y **Keys** enseñan
   el aviso persistente con el número de claves afectadas. En la tabla de Keys
   esas claves salen marcadas como de "otro panel".
3. "Refresh from panel" NO debe darlas por revocadas.
4. **Regenerar todas las claves y reenviar por correo** → confirmación →
   cada usuario afectado recibe una clave nueva y el aviso desaparece.
   - Con más de 10 usuarios afectados la página dice que se hace en segundo
     plano: ejecuta el cron (`php admin/cli/cron.php`) y comprueba los correos.
5. La clave vieja deja de funcionar contra el server MCP; la nueva sí.

## PHPUnit

Hay un Moodle de integración aparte en `~/Dev/studiolxd/learn/ci-moodle` (el de
`~/Dev/studiolxd/learn/moodle` es el de trabajo: no se toca).

**La trampa**: el PHP por defecto del Mac es la **8.5** y las dependencias de
Moodle llegan a la **8.4**, así que sin anteponer la 8.4 el entorno de pruebas
ni siquiera arranca y parece roto. Hay que ponerla delante en el `PATH` en cada
comando:

```sh
cd ~/Dev/studiolxd/learn/ci-moodle
ln -sfn ~/Dev/studiolxd/moodle-local_mcpconnector public/local/mcpconnector
PATH=/opt/homebrew/opt/php@8.4/bin:$PATH php public/admin/tool/phpunit/cli/util.php --buildconfig
PATH=/opt/homebrew/opt/php@8.4/bin:$PATH php vendor/bin/phpunit --testsuite local_mcpconnector_testsuite
rm public/local/mcpconnector   # retirar el symlink al terminar
```

El `--buildconfig` hay que repetirlo cada vez que se añade o quita un fichero
de `tests/`: el `phpunit.xml` lista las suites.

## Cierre de ronda

- Anotar cualquier error PHP (Site administration → Reports → Logs o
  `error_log`) y el comportamiento observado; reportarlo en la sesión de
  trabajo para la siguiente iteración.
