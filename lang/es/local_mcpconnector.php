<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Spanish language pack for Studio LXD
 *
 * @package    local_mcpconnector
 * @category   string
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['adminpage'] = 'Studio LXD';
$string['auto_email'] = 'Enviar claves MCP automáticamente por email';
$string['auto_email_desc'] = 'Cuando está habilitado, Studio LXD envía las claves la primera vez que se crean.';
$string['auto_sync_admin'] = 'Sincronización automática de admins';
$string['auto_sync_admin_desc'] = 'Sincronizar automáticamente cuando se asigna o quita el rol de administrador del sitio.';
$string['auto_sync_editingteacher'] = 'Sincronización automática de editingteachers';
$string['auto_sync_editingteacher_desc'] = 'Sincronizar automáticamente cuando se asigna o quita el rol de profesor con permiso de edición.';
$string['auto_sync_manager'] = 'Sincronización automática de managers';
$string['auto_sync_manager_desc'] = 'Sincronizar automáticamente cuando se asigna o quita el rol de manager.';
$string['auto_sync_section'] = 'Sincronización automática';
$string['auto_sync_student'] = 'Sincronización automática de students';
$string['auto_sync_student_desc'] = 'Sincronizar automáticamente cuando se matricula o desmatricula a un estudiante.';
$string['auto_sync_teacher'] = 'Sincronización automática de teachers';
$string['auto_sync_teacher_desc'] = 'Sincronizar automáticamente cuando se asigna o quita el rol de profesor sin permiso de edición.';
$string['auto_sync_user'] = 'Sincronización automática de usuarios';
$string['auto_sync_user_desc'] = 'Sincronizar automáticamente cuando se crea un nuevo usuario en la plataforma.';
$string['changes_saved'] = 'Cambios guardados.';
$string['chat_auth_broken'] = 'Método de autenticación «{$a}»: Moodle lo lee como una cuenta DESHABILITADA y rechaza su token en TODAS las llamadas a servicios web, así que el chat no puede hablar con Moodle. Regenera la identidad para arreglarlo.';
$string['chat_auth_ok'] = 'Método de autenticación «{$a}»: habilitado, así que los servicios web aceptan su token. Sigue sin poder iniciar sesión: la cuenta no tiene contraseña utilizable.';
$string['chat_check'] = 'Comprobar ahora';
$string['chat_create'] = 'Crear la identidad del chat';
$string['chat_create_confirm'] = 'Se creará la identidad del chat con el rol «{$a}» asignado a nivel de sistema, así que el asistente podrá hacer todo lo que ese rol permita en todo el sitio, y cualquier miembro de tu organización en el panel chateará con ella. ¿Continuar?';
$string['chat_error_auth_disabled'] = 'Este sitio tiene deshabilitado el método de autenticación «manual», así que la cuenta de servicio no se puede crear con él, y toda cuenta que Moodle considere deshabilitada tiene su token de servicios web rechazado. Habilítalo en Administración del sitio > Extensiones > Autenticación > Gestionar autenticación y vuelve a intentarlo.';
$string['chat_error_invalid_license'] = 'La licencia no está validada, así que la clave no se puede registrar en el panel.';
$string['chat_error_missing_service'] = 'El servicio web de ese rol no existe en Moodle. Abre la pestaña Servicios y vuelve a intentarlo.';
$string['chat_error_panel_error'] = 'El panel ha rechazado el registro de la clave.';
$string['chat_error_role_not_available'] = 'Ese rol ya no existe en este sitio.';
$string['chat_error_token_failed'] = 'No se ha podido crear el token de servicios web de Moodle.';
$string['chat_error_user_create_failed'] = 'No se ha podido crear la cuenta de servicio en Moodle. Revisa la salida de depuración del sitio para saber por qué.';
$string['chat_heading'] = 'Identidad del chat';
$string['chat_intro'] = 'El chat del panel habla con Moodle a través de una cuenta de servicio propia: un usuario de Moodle que no puede iniciar sesión, que tiene el rol que elijas aquí y que es el dueño del token que usa el asistente. No se crea nada hasta que lo pidas abajo.';
$string['chat_key_never_shown'] = 'La clave se registra directamente en el panel: no se muestra aquí, no se envía por correo y no se guarda en Moodle.';
$string['chat_missing_license'] = 'Valida antes la licencia en la pestaña Licencia: sin ella no se puede registrar la clave en el panel.';
$string['chat_no_roles'] = 'Este sitio no tiene ninguno de los roles que entiende el panel (gestor, profesor con permiso de edición, profesor sin permiso de edición, estudiante, usuario autenticado), así que no hay nada que asignar.';
$string['chat_panel_checked_at'] = 'Última comprobación contra el panel: {$a}';
$string['chat_panel_foreign'] = 'La clave se registró para OTRO panel y ya no funciona. Regenérala.';
$string['chat_panel_gone'] = 'El panel ya no conoce esta clave: allí se ha borrado o revocado. Regenérala.';
$string['chat_panel_missing'] = 'No hay ninguna clave registrada en el panel.';
$string['chat_panel_ok'] = 'Clave registrada en el panel y viva (termina en {$a}).';
$string['chat_panel_unknown'] = 'Clave registrada en el panel (termina en {$a}); pulsa «Comprobar ahora» para confirmar que sigue viva allí.';
$string['chat_provision_failed'] = 'No se ha podido preparar la identidad del chat: {$a}';
$string['chat_provision_success'] = 'Identidad del chat lista: la cuenta, su rol, su token y la clave del panel están en su sitio.';
$string['chat_regenerate'] = 'Regenerar la identidad del chat';
$string['chat_regenerate_confirm'] = 'Se rehará la identidad del chat con el rol «{$a}» asignado a nivel de sistema: la cuenta se recrea si ya no está, se retira su token anterior y se registra una clave nueva en el panel, que revoca la anterior. El chat deja de funcionar con las credenciales viejas en cuanto se ejecute. ¿Continuar?';
$string['chat_registered_at'] = 'Registrada en el panel: {$a}';
$string['chat_role_label'] = 'Rol de la identidad del chat';
$string['chat_role_missing'] = 'La cuenta ha PERDIDO el rol «{$a}» a nivel de sistema. Regenera para volver a asignárselo.';
$string['chat_role_none'] = 'Todavía no hay ningún rol elegido.';
$string['chat_role_ok'] = 'Rol «{$a}» asignado a nivel de sistema.';
$string['chat_role_select'] = 'Rol y creación';
$string['chat_role_select_help'] = 'Solo se ofrecen roles que ya existen en este sitio: el plugin no define un rol propio. Elige el más pequeño que permita al asistente hacer lo que necesitas.';
$string['chat_scope_narrow'] = 'El alcance se puede recortar después desde el panel: solo lectura, herramientas concretas o cursos concretos.';
$string['chat_scope_warning'] = 'Léelo antes de crearla: el rol se asigna A NIVEL DE SISTEMA, así que el asistente verá y hará lo que ese rol permita en TODO el sitio, no dentro de un curso. Y CUALQUIER miembro de tu organización en el panel usará esta identidad cuando chatee: el chat no funciona con la cuenta de Moodle de cada persona.';
$string['chat_service_missing'] = 'La cuenta NO está autorizada en el servicio web de su rol.';
$string['chat_service_ok'] = 'Autorizada en el servicio web de su rol.';
$string['chat_status_heading'] = 'Estado';
$string['chat_status_incomplete'] = 'La identidad del chat está incompleta: el chat no funcionará hasta que la regeneres.';
$string['chat_status_none'] = 'La identidad del chat todavía no se ha creado.';
$string['chat_status_ready'] = 'La identidad del chat está lista.';
$string['chat_token_broken'] = 'Su token NO funciona: una llamada real a servicios web con él fue rechazada ({$a}). Regenera la identidad.';
$string['chat_token_missing'] = 'NO tiene token de servicios web de Moodle.';
$string['chat_token_ok'] = 'Tiene un token de servicios web de Moodle vivo.';
$string['chat_token_untested'] = 'Su token todavía no se ha probado: pulsa «Comprobar ahora» para hacer una llamada real a servicios web con él.';
$string['chat_token_works'] = 'Verificado contra el propio Moodle: una llamada real a servicios web con ese token funciona.';
$string['chat_user_missing'] = 'La cuenta de servicio de Moodle no existe (no se creó nunca, o se ha borrado).';
$string['chat_user_ok'] = 'Cuenta de servicio en Moodle: {$a->name} ({$a->username}, {$a->email}), sin acceso interactivo.';
$string['deprovision'] = 'Desprovisionar';
$string['deprovision_confirm'] = 'Esto revocará todas las claves MCP en el panel y eliminará permanentemente todos los servicios, tokens y autorizaciones de usuario creados por Studio LXD. Esta acción no se puede deshacer. ¿Continuar?';
$string['deprovision_help'] = 'Revoca todas las claves MCP en el panel y elimina todos los servicios, tokens y autorizaciones creados por este plugin en Moodle. La licencia y la conexión con el panel se conservan, para que el plugin pueda volver a autoaprovisionarse después.';
$string['deprovision_panel_warning'] = 'Se eliminaron los servicios y tokens en Moodle, pero el panel no pudo confirmar la revocación de las claves: {$a}. Revísalo manualmente en el panel.';
$string['deprovision_success'] = 'Desprovisión completada: se eliminaron {$a} servicio(s) y se revocaron todas las claves del panel.';
$string['editfunctions'] = 'Editar funciones';
$string['email_body'] = 'Cuerpo del email';
$string['email_body_default'] = 'Hola, {$a->firstname}:' . "\n\n" .
    'Tu acceso a Studio LXD está listo. Hay dos formas de conectar tu asistente de IA:' . "\n\n" .
    '1) Claude Desktop o ChatGPT (recomendado): añade un conector con esta URL e inicia sesión con tu Moodle cuando te lo pida — NO necesitas la clave de abajo:' . "\n" .
    '   {$a->mcpurl}' . "\n\n" .
    '2) Herramientas que aceptan un token (Cursor, scripts, CLI): usa la URL de arriba y envía esta clave como cabecera  Authorization: Bearer <clave>' . "\n" .
    '   {$a->mcpkey}' . "\n\n" .
    'Instrucciones completas: {$a->docsurl}' . "\n\n" .
    'Mantén la clave en privado. Contacta con tu administrador si necesitas una nueva.';
$string['email_body_desc'] = 'Plantilla del cuerpo del email con la clave MCP. Placeholders: {$a->firstname}, {$a->lastname}, {$a->username}, {$a->email}, {$a->mcpkey}, {$a->mcpurl} (el endpoint MCP), {$a->docsurl} (la guía de conexión).';
$string['email_section'] = 'Envío de claves por email';
$string['email_subject'] = 'Asunto del email';
$string['email_subject_default'] = 'Tu acceso a Studio LXD';
$string['email_subject_desc'] = 'Asunto del email con la clave MCP.';
$string['errordetail'] = '{$a}';
$string['existing_users'] = 'Usuarios existentes';
$string['health_auto_sync'] = 'Servicios con auto-sync';
$string['health_heading'] = 'Salud de la conexión';
$string['health_keys'] = 'Claves MCP';
$string['health_keys_detail'] = '{$a->active} activas · {$a->suspended} suspendidas · {$a->revoked} revocadas · {$a->expired} caducadas';
$string['health_last_sync'] = 'Última sincronización de usuarios';
$string['health_panel_checked'] = 'Última verificación de licencia';
$string['health_panel_status'] = 'Conectividad con el panel (cacheada)';
$string['health_telemetry'] = 'Telemetría (opt-in)';
$string['health_telemetry_failed'] = 'La telemetría falló: {$a}';
$string['health_telemetry_hint'] = 'Activa la telemetría en la pestaña Configuración para compartir versiones y número de claves con el panel (ayuda al soporte — nunca datos personales).';
$string['health_telemetry_last'] = 'último envío:';
$string['health_telemetry_send'] = 'Enviar ahora';
$string['health_telemetry_sent'] = 'Telemetría enviada al panel.';
$string['health_versions'] = 'Versiones';
$string['invalidservice'] = 'Servicio desconocido.';
$string['key_activate'] = 'Activar';
$string['key_activate_failed'] = 'No se pudo activar la clave.';
$string['key_activated'] = 'Clave activada.';
$string['key_lifetime_days'] = 'Vigencia de la clave (días)';
$string['key_lifetime_days_desc'] = 'Cuánto tiempo es válida una clave MCP recién emitida. 0 significa sin caducidad. Las claves se renuevan automáticamente antes de caducar (se envía una nueva por email al usuario).';
$string['key_regen_failed'] = 'No se pudo regenerar la clave.';
$string['key_regenerate_confirm'] = '¿Regenerar la clave MCP de {$a}? La clave actual se revoca y se envía una nueva por email.';
$string['key_regenerate_email'] = 'Regenerar y enviar clave';
$string['key_regenerated'] = 'Clave regenerada.';
$string['key_revoke'] = 'Revocar';
$string['key_revoke_confirm'] = '¿Revocar la clave MCP de {$a}? Esta acción es permanente y no se puede deshacer.';
$string['key_revoke_failed'] = 'No se pudo revocar la clave.';
$string['key_revoked'] = 'Clave revocada.';
$string['key_send_failed'] = 'No se pudo enviar el email con la clave.';
$string['key_send_queued'] = 'Clave regenerada. El correo se está enviando en segundo plano.';
$string['key_sent'] = 'Email de clave enviado.';
$string['key_status_active'] = 'Activa';
$string['key_status_other_panel'] = 'Otro panel';
$string['key_status_other_panel_help'] = 'Esta clave se emitió para otro panel y dejó de funcionar cuando el sitio se emparejó con el actual. Regenérala para emitir una clave válida.';
$string['key_status_revoked'] = 'Revocada';
$string['key_status_suspended'] = 'Suspendida';
$string['key_suspend'] = 'Suspender';
$string['key_suspend_failed'] = 'No se pudo suspender la clave.';
$string['key_suspended'] = 'Clave suspendida.';
$string['keys_actions'] = 'Acciones';
$string['keys_created'] = 'Creada';
$string['keys_empty'] = 'Aún no hay claves registradas para esta licencia.';
$string['keys_key'] = 'Clave';
$string['keys_missing_license'] = 'Configura una licencia antes de gestionar las claves.';
$string['keys_refresh'] = 'Actualizar desde el panel';
$string['keys_refresh_failed'] = 'No se pudieron actualizar las claves desde el panel: {$a}';
$string['keys_refreshed'] = 'Estados de las claves actualizados desde el panel.';
$string['keys_regenerate_all'] = 'Regenerar todas las claves y enviarlas por correo';
$string['keys_regenerate_all_confirm'] = '¿Regenerar las claves MCP de todos los usuarios afectados? Usuarios afectados: {$a}. Cada clave actual se revoca y se sustituye, y cada usuario recibe por correo una clave nueva: la anterior deja de funcionar de inmediato.';
$string['keys_regenerate_all_done'] = 'Claves regeneradas y enviadas: {$a}.';
$string['keys_regenerate_all_failed'] = 'Claves que no se han podido regenerar: {$a}.';
$string['keys_regenerate_all_none'] = 'Todas las claves pertenecen ya al panel actual.';
$string['keys_regenerate_all_queued'] = 'Las claves se están regenerando en segundo plano (usuarios afectados: {$a}). Cada usuario recibirá por correo su clave nueva según se emita.';
$string['keys_role'] = 'Roles';
$string['keys_section'] = 'Claves MCP';
$string['keys_sent'] = 'Enviado';
$string['keys_status'] = 'Estado';
$string['keys_user'] = 'Usuario';
$string['license_checked_at'] = 'Última comprobación: {$a}';
$string['license_empty'] = 'La clave de licencia es obligatoria.';
$string['license_error'] = 'La licencia es incorrecta o no se pudo verificar.';
$string['license_heading'] = 'Licencia';
$string['license_help'] = 'Introduce tu clave de licencia y valídala.';
$string['license_label'] = 'Clave de licencia';
$string['license_ok'] = 'Licencia verificada.';
$string['license_ok_panel_changed'] = 'Licencia verificada, pero contra un panel distinto del que se venía usando. Las claves emitidas para el panel anterior ya no funcionan: regenéralas.';
$string['license_recheck'] = 'Verificar ahora';
$string['license_required'] = 'Se requiere una licencia válida para activar Studio LXD.';
$string['license_save'] = 'Validar licencia';
$string['license_status_error'] = 'Incorrecta';
$string['license_status_label'] = 'Estado de licencia: {$a}';
$string['license_status_missing'] = 'No configurada';
$string['license_status_ok'] = 'Configurada';
$string['mcp_url'] = 'URL del endpoint MCP';
$string['mcp_url_help'] = 'El endpoint MCP al que se conectan tus asistentes de IA — el subdominio de tu organización en el panel, p. ej. https://tu-org.slxd.app/mcp. El panel te muestra la URL exacta al crear la conexión (cópiala de ahí). Se inserta en los emails de claves mediante el marcador mcpurl.';
$string['mcpconnector:manage'] = 'Gestionar MCP Connector';
$string['missing'] = 'Falta';
$string['missingservice'] = 'Falta el registro del servicio.';
$string['ok'] = 'OK';
$string['panel_changed_warning'] = 'Claves MCP emitidas para otro panel: {$a}. Dejaron de funcionar cuando el sitio se emparejó con el panel actual y seguirán sin funcionar hasta que se regeneren. Al regenerarlas se revoca cada una y se envía por correo una clave nueva a su usuario.';
$string['panel_error_email_failed'] = 'La clave se regeneró, pero no se pudo enviar el correo.';
$string['panel_error_invalid_body'] = 'El panel rechazó la solicitud por malformada.';
$string['panel_error_invalid_credentials'] = 'El panel rechazó la clave de licencia o el secreto del panel.';
$string['panel_error_invalid_license'] = 'Configura y valida una licencia antes de realizar esta acción.';
$string['panel_error_invalid_user'] = 'El usuario de Moodle de esta clave ya no existe.';
$string['panel_error_key_revoked'] = 'La clave está revocada y ya no puede modificarse.';
$string['panel_error_missing_panel_secret'] = 'El secreto del panel no está configurado. Introduce el par clave de licencia + secreto emitido por tu panel.';
$string['panel_error_missing_signature'] = 'Faltaba la firma de la solicitud o no se pudo verificar. Revisa el secreto del panel.';
$string['panel_error_no_service'] = 'El usuario ya no está asignado a ningún servicio MCP.';
$string['panel_error_not_found'] = 'La clave no se encontró en el panel.';
$string['panel_error_rate_limited'] = 'Demasiadas solicitudes al panel. Inténtalo de nuevo en un minuto.';
$string['panel_error_regenerate_failed'] = 'No se ha podido regenerar la clave.';
$string['panel_error_revoke_failed'] = 'No se ha podido revocar la clave anterior en el panel.';
$string['panel_error_server_error'] = 'El panel notificó un error interno.';
$string['panel_error_unknown'] = 'El panel devolvió un error inesperado.';
$string['panel_error_url_mismatch'] = 'La URL de este sitio no coincide con la registrada en el panel.';
$string['panel_pair_notice'] = 'El par clave de licencia + secreto del panel se muestra solo UNA VEZ al crear la conexión en el panel. Si pierdes el secreto no se puede recuperar: rota (regenera) el par en el panel e introduce aquí los nuevos valores.';
$string['panel_secret'] = 'Secreto del panel';
$string['panel_secret_help'] = 'Secreto compartido con el que se firma cada petición que el plugin envía al panel.';
$string['panel_secret_missing'] = 'El secreto del panel no está configurado. Introduce el par clave de licencia + secreto emitido por tu panel.';
$string['panel_url'] = 'URL del panel';
$string['panel_url_help'] = 'URL base de tu panel Studio LXD, p. ej. https://lmsmcp.slxd.app.';
$string['pluginname'] = 'MCP Connector for Moodle';


$string['potential_users'] = 'Usuarios potenciales';
$string['privacy:metadata:localkeys'] = 'Metadata de claves MCP almacenada localmente (nunca valores de claves ni tokens).';
$string['privacy:metadata:localkeys:keylast4'] = 'Los últimos 4 caracteres de la clave, para identificarla.';
$string['privacy:metadata:localkeys:panelkeyid'] = 'El identificador de la clave en el panel.';
$string['privacy:metadata:localkeys:roles'] = 'Los roles de Moodle con los que puede actuar la clave.';
$string['privacy:metadata:localkeys:sentat'] = 'Cuándo se envió la clave por email al usuario.';
$string['privacy:metadata:localkeys:status'] = 'El estado de la clave (activa, suspendida o revocada).';
$string['privacy:metadata:localkeys:userid'] = 'El usuario al que pertenece la clave MCP.';
$string['privacy:metadata:moodlemcp'] = 'Datos enviados al servicio del panel de Studio LXD para crear y gestionar claves API.';
$string['privacy:metadata:moodlemcp:email'] = 'La dirección de correo electrónico del usuario, utilizada al enviar claves MCP.';
$string['privacy:metadata:moodlemcp:firstname'] = 'El nombre del usuario, utilizado en plantillas de correo.';
$string['privacy:metadata:moodlemcp:lastname'] = 'El apellido del usuario, utilizado en plantillas de correo.';
$string['privacy:metadata:moodlemcp:roles'] = 'Los roles del usuario mapeados a servicios MCP.';
$string['privacy:metadata:moodlemcp:token'] = 'El token de servicio web generado para el usuario.';
$string['privacy:metadata:moodlemcp:userid'] = 'El ID de usuario de Moodle.';


$string['secret_keep_blank'] = 'Déjalo en blanco para mantener el valor actual.';
$string['service_edit_heading'] = 'Editar funciones del servicio "{$a}"';
$string['service_functions'] = 'Funciones permitidas';
$string['service_name_admin'] = 'Administrador';
$string['service_name_editingteacher'] = 'Profesor';
$string['service_name_manager'] = 'Gestor';
$string['service_name_student'] = 'Estudiante';
$string['service_name_teacher'] = 'Profesor sin permiso de edición';
$string['service_name_user'] = 'Usuario identificado';
$string['service_restore'] = 'Restaurar servicio';
$string['service_restore_confirm'] = '¿Restaurar el servicio "{$a}" a su lista de funciones base? Esto sobrescribe la lista actual.';
$string['service_restore_failed'] = 'No se pudo restaurar la configuración base del servicio.';
$string['service_restored'] = 'Servicio "{$a}" restaurado a la configuración base.';
$string['service_updated'] = 'Servicio "{$a}" actualizado.';
$string['services_created'] = 'Se crearon {$a} servicio(s) de Studio LXD.';

$string['services_heading'] = 'Servicios';
$string['services_table_actions'] = 'Acciones';
$string['services_table_service'] = 'Servicio';
$string['services_table_status'] = 'Estado';
$string['tab_chat'] = 'Chat';
$string['tab_health'] = 'Salud';
$string['tab_keys'] = 'Claves';
$string['tab_license'] = 'Licencia';
$string['tab_services'] = 'Servicios';
$string['tab_settings'] = 'Configuración';
$string['tab_users'] = 'Usuarios';
$string['task_sync_users'] = 'Sincronizar usuarios de Studio LXD';
$string['taskfailed'] = 'La tarea de Studio LXD falló: {$a}';
$string['telemetry_enabled'] = 'Enviar telemetría al panel';
$string['telemetry_enabled_desc'] = 'Opt-in: comparte con el panel las versiones de plugin/Moodle/PHP y el NÚMERO de claves aproximadamente una vez al día (nunca datos personales). Ayuda al soporte a diagnosticar problemas de forma proactiva.';
$string['telemetry_section'] = 'Telemetría';










$string['users_add'] = 'Añadir';
$string['users_add_failed_plural'] = '{$a} usuarios no se pudieron añadir.';
$string['users_add_failed_singular'] = '1 usuario no se pudo añadir.';
$string['users_added_email_queued'] = 'Las claves se están enviando por correo en segundo plano.';
$string['users_added_plural'] = 'Se añadieron {$a} usuarios.';
$string['users_added_singular'] = 'Se añadió 1 usuario.';
$string['users_assigned'] = 'Usuarios asignados';
$string['users_available'] = 'Usuarios disponibles';
$string['users_manage'] = 'Gestionar usuarios';
$string['users_remove'] = 'Quitar';
$string['users_removed_plural'] = 'Se quitaron {$a} usuarios.';
$string['users_removed_singular'] = 'Se quitó 1 usuario.';
$string['users_sync_all'] = 'Sincronizar todo';
$string['users_sync_queued'] = 'Sincronización en cola. Se ejecutará en segundo plano.';
