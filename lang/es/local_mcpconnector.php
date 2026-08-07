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
 * Spanish language pack for Moodle MCP
 *
 * @package    local_mcpconnector
 * @category   string
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'MCP Connector for Moodle';

$string['mcpconnector:manage'] = 'Gestionar MCP Connector';

$string['privacy:metadata:moodlemcp'] = 'Datos enviados al servicio del panel MCP para crear y gestionar claves API.';
$string['privacy:metadata:moodlemcp:userid'] = 'El ID de usuario de Moodle.';
$string['privacy:metadata:moodlemcp:token'] = 'El token de servicio web generado para el usuario.';
$string['privacy:metadata:moodlemcp:roles'] = 'Los roles del usuario mapeados a servicios MCP.';
$string['privacy:metadata:moodlemcp:email'] = 'La dirección de correo electrónico del usuario, utilizada al enviar claves MCP.';
$string['privacy:metadata:moodlemcp:firstname'] = 'El nombre del usuario, utilizado en plantillas de correo.';
$string['privacy:metadata:moodlemcp:lastname'] = 'El apellido del usuario, utilizado en plantillas de correo.';

$string['adminpage'] = 'Moodle MCP';
$string['changes_saved'] = 'Cambios guardados.';
$string['ok'] = 'OK';
$string['missing'] = 'Falta';

$string['editfunctions'] = 'Editar funciones';
$string['service_restore'] = 'Restaurar servicio';
$string['service_restore_confirm'] = '¿Restaurar el servicio "{$a}" a su lista de funciones base? Esto sobrescribe la lista actual.';
$string['service_restored'] = 'Servicio "{$a}" restaurado a la configuración base.';
$string['service_restore_failed'] = 'No se pudo restaurar la configuración base del servicio.';
$string['service_updated'] = 'Servicio "{$a}" actualizado.';
$string['service_functions'] = 'Funciones permitidas';
$string['service_edit_heading'] = 'Editar funciones del servicio "{$a}"';
$string['services_created'] = 'Se crearon {$a} servicio(s) MoodleMCP.';
$string['invalidservice'] = 'Servicio desconocido.';
$string['missingservice'] = 'Falta el registro del servicio.';
$string['deprovision'] = 'Desprovisionar';
$string['deprovision_help'] = 'Revoca todas las claves MCP en el panel y elimina todos los servicios, tokens y autorizaciones creados por este plugin en Moodle. La licencia y la conexión con el panel se conservan, para que el plugin pueda volver a autoaprovisionarse después.';
$string['deprovision_confirm'] = 'Esto revocará todas las claves MCP en el panel y eliminará permanentemente todos los servicios, tokens y autorizaciones de usuario creados por MoodleMCP. Esta acción no se puede deshacer. ¿Continuar?';
$string['deprovision_success'] = 'Desprovisión completada: se eliminaron {$a} servicio(s) y se revocaron todas las claves del panel.';
$string['deprovision_panel_warning'] = 'Se eliminaron los servicios y tokens en Moodle, pero el panel no pudo confirmar la revocación de las claves: {$a}. Revísalo manualmente en el panel.';

$string['tab_license'] = 'Licencia';
$string['tab_services'] = 'Servicios';
$string['tab_users'] = 'Usuarios';
$string['tab_keys'] = 'Claves';
$string['tab_settings'] = 'Configuración';
$string['tab_health'] = 'Salud';
$string['health_heading'] = 'Salud de la conexión';
$string['health_panel_status'] = 'Conectividad con el panel (cacheada)';
$string['health_panel_checked'] = 'Última verificación de licencia';
$string['health_keys'] = 'Claves MCP';
$string['health_keys_detail'] = '{$a->active} activas · {$a->suspended} suspendidas · {$a->revoked} revocadas · {$a->expired} caducadas';
$string['health_last_sync'] = 'Última sincronización de usuarios';
$string['health_auto_sync'] = 'Servicios con auto-sync';
$string['health_versions'] = 'Versiones';
$string['health_telemetry'] = 'Telemetría (opt-in)';
$string['health_telemetry_last'] = 'último envío:';
$string['health_telemetry_send'] = 'Enviar ahora';
$string['health_telemetry_sent'] = 'Telemetría enviada al panel.';
$string['health_telemetry_failed'] = 'La telemetría falló: {$a}';
$string['health_telemetry_hint'] = 'Activa la telemetría en la pestaña Configuración para compartir versiones y número de claves con el panel (ayuda al soporte — nunca datos personales).';
$string['telemetry_section'] = 'Telemetría';
$string['telemetry_enabled'] = 'Enviar telemetría al panel';
$string['telemetry_enabled_desc'] = 'Opt-in: comparte con el panel las versiones de plugin/Moodle/PHP y el NÚMERO de claves aproximadamente una vez al día (nunca datos personales). Ayuda al soporte a diagnosticar problemas de forma proactiva.';

$string['service_name_admin'] = 'Administrador';
$string['service_name_manager'] = 'Gestor';
$string['service_name_editingteacher'] = 'Profesor';
$string['service_name_teacher'] = 'Profesor sin permiso de edición';
$string['service_name_student'] = 'Estudiante';
$string['service_name_user'] = 'Usuario identificado';

$string['license_heading'] = 'Licencia';
$string['license_label'] = 'Clave de licencia';
$string['license_help'] = 'Introduce tu clave de licencia y valídala.';
$string['secret_keep_blank'] = 'Déjalo en blanco para mantener el valor actual.';
$string['panel_url'] = 'URL del panel';
$string['panel_url_help'] = 'URL base de tu panel MoodleMCP, p. ej. https://moodlemcp.com.';
$string['panel_secret'] = 'Secreto del panel';
$string['panel_secret_help'] = 'Secreto compartido con el que se firma cada petición que el plugin envía al panel.';
$string['panel_secret_missing'] = 'El secreto del panel no está configurado. Introduce el par clave de licencia + secreto emitido por tu panel.';
$string['panel_pair_notice'] = 'El par clave de licencia + secreto del panel se muestra solo UNA VEZ al crear la conexión en el panel. Si pierdes el secreto no se puede recuperar: rota (regenera) el par en el panel e introduce aquí los nuevos valores.';
$string['mcp_url'] = 'URL del endpoint MCP';
$string['mcp_url_help'] = 'El endpoint MCP al que se conectan tus asistentes de IA — el subdominio de tu organización en el panel, p. ej. https://tu-org.moodlemcp.com/mcp. El panel te muestra la URL exacta al crear la conexión (cópiala de ahí). Se inserta en los emails de claves mediante el marcador mcpurl.';
$string['license_status_label'] = 'Estado de licencia: {$a}';
$string['license_status_ok'] = 'Configurada';
$string['license_status_error'] = 'Incorrecta';
$string['license_status_missing'] = 'No configurada';
$string['license_required'] = 'Se requiere una licencia válida para activar Moodle MCP.';
$string['license_save'] = 'Validar licencia';
$string['license_ok'] = 'Licencia verificada.';
$string['license_error'] = 'La licencia es incorrecta o no se pudo verificar.';
$string['license_empty'] = 'La clave de licencia es obligatoria.';
$string['license_checked_at'] = 'Última comprobación: {$a}';

$string['panel_error_unknown'] = 'El panel devolvió un error inesperado.';
$string['panel_error_server_error'] = 'El panel notificó un error interno.';
$string['panel_error_invalid_body'] = 'El panel rechazó la solicitud por malformada.';
$string['panel_error_invalid_credentials'] = 'El panel rechazó la clave de licencia o el secreto del panel.';
$string['panel_error_missing_signature'] = 'Faltaba la firma de la solicitud o no se pudo verificar. Revisa el secreto del panel.';
$string['panel_error_missing_panel_secret'] = 'El secreto del panel no está configurado. Introduce el par clave de licencia + secreto emitido por tu panel.';
$string['panel_error_invalid_license'] = 'Configura y valida una licencia antes de realizar esta acción.';
$string['panel_error_invalid_user'] = 'El usuario de Moodle de esta clave ya no existe.';
$string['panel_error_rate_limited'] = 'Demasiadas solicitudes al panel. Inténtalo de nuevo en un minuto.';
$string['panel_error_url_mismatch'] = 'La URL de este sitio no coincide con la registrada en el panel.';
$string['panel_error_not_found'] = 'La clave no se encontró en el panel.';
$string['panel_error_key_revoked'] = 'La clave está revocada y ya no puede modificarse.';

$string['services_heading'] = 'Servicios';
$string['services_table_service'] = 'Servicio';
$string['services_table_status'] = 'Estado';
$string['services_table_actions'] = 'Acciones';


$string['auto_sync_admin'] = 'Sincronización automática de admins';
$string['auto_sync_admin_desc'] = 'Sincronizar automáticamente cuando se asigna o quita el rol de administrador del sitio.';
$string['auto_sync_manager'] = 'Sincronización automática de managers';
$string['auto_sync_manager_desc'] = 'Sincronizar automáticamente cuando se asigna o quita el rol de manager.';
$string['auto_sync_editingteacher'] = 'Sincronización automática de editingteachers';
$string['auto_sync_editingteacher_desc'] = 'Sincronizar automáticamente cuando se asigna o quita el rol de profesor con permiso de edición.';
$string['auto_sync_teacher'] = 'Sincronización automática de teachers';
$string['auto_sync_teacher_desc'] = 'Sincronizar automáticamente cuando se asigna o quita el rol de profesor sin permiso de edición.';
$string['auto_sync_student'] = 'Sincronización automática de students';
$string['auto_sync_student_desc'] = 'Sincronizar automáticamente cuando se matricula o desmatricula a un estudiante.';
$string['auto_sync_user'] = 'Sincronización automática de usuarios';
$string['auto_sync_user_desc'] = 'Sincronizar automáticamente cuando se crea un nuevo usuario en la plataforma.';

$string['auto_sync_section'] = 'Sincronización automática';
$string['email_section'] = 'Envío de claves por email';
$string['auto_email'] = 'Enviar claves MCP automáticamente por email';
$string['auto_email_desc'] = 'Cuando está habilitado, Moodle MCP envía las claves la primera vez que se crean.';
$string['email_subject'] = 'Asunto del email';
$string['email_subject_desc'] = 'Asunto del email con la clave MCP.';
$string['email_body'] = 'Cuerpo del email';
$string['email_body_desc'] = 'Plantilla del cuerpo del email con la clave MCP. Placeholders: {$a->firstname}, {$a->lastname}, {$a->username}, {$a->email}, {$a->mcpkey}, {$a->mcpurl} (el endpoint MCP), {$a->docsurl} (la guía de conexión).';
$string['email_subject_default'] = 'Tu acceso a Moodle MCP';
$string['email_body_default'] = 'Hola, {$a->firstname}:' . "\n\n" .
    'Tu acceso a Moodle MCP está listo. Hay dos formas de conectar tu asistente de IA:' . "\n\n" .
    '1) Claude Desktop o ChatGPT (recomendado): añade un conector con esta URL e inicia sesión con tu Moodle cuando te lo pida — NO necesitas la clave de abajo:' . "\n" .
    '   {$a->mcpurl}' . "\n\n" .
    '2) Herramientas que aceptan un token (Cursor, scripts, CLI): usa la URL de arriba y envía esta clave como cabecera  Authorization: Bearer <clave>' . "\n" .
    '   {$a->mcpkey}' . "\n\n" .
    'Instrucciones completas: {$a->docsurl}' . "\n\n" .
    'Mantén la clave en privado. Contacta con tu administrador si necesitas una nueva.';

$string['keys_missing_license'] = 'Configura una licencia antes de gestionar las claves.';
$string['keys_empty'] = 'Aún no hay claves registradas para esta licencia.';
$string['keys_user'] = 'Usuario';
$string['keys_key'] = 'Clave';
$string['keys_role'] = 'Roles';
$string['keys_status'] = 'Estado';
$string['keys_sent'] = 'Enviado';
$string['keys_created'] = 'Creada';
$string['keys_actions'] = 'Acciones';
$string['keys_refresh'] = 'Actualizar desde el panel';
$string['keys_refreshed'] = 'Estados de las claves actualizados desde el panel.';
$string['keys_refresh_failed'] = 'No se pudieron actualizar las claves desde el panel: {$a}';

$string['key_status_active'] = 'Activa';
$string['key_status_suspended'] = 'Suspendida';
$string['key_status_revoked'] = 'Revocada';
$string['key_suspend'] = 'Suspender';
$string['key_activate'] = 'Activar';
$string['key_revoke'] = 'Revocar';
$string['key_revoke_confirm'] = '¿Revocar la clave MCP de {$a}? Esta acción es permanente y no se puede deshacer.';
$string['key_regenerate_email'] = 'Regenerar y enviar clave';
$string['key_regenerate_confirm'] = '¿Regenerar la clave MCP de {$a}? La clave actual se revoca y se envía una nueva por email.';
$string['key_sent'] = 'Email de clave enviado.';
$string['key_send_failed'] = 'No se pudo enviar el email con la clave.';
$string['key_suspended'] = 'Clave suspendida.';
$string['key_suspend_failed'] = 'No se pudo suspender la clave.';
$string['key_activated'] = 'Clave activada.';
$string['key_activate_failed'] = 'No se pudo activar la clave.';
$string['key_revoked'] = 'Clave revocada.';
$string['key_revoke_failed'] = 'No se pudo revocar la clave.';
$string['key_regenerated'] = 'Clave regenerada.';
$string['key_regen_failed'] = 'No se pudo regenerar la clave.';

$string['users_available'] = 'Usuarios disponibles';
$string['users_assigned'] = 'Usuarios asignados';
$string['users_add'] = 'Añadir';
$string['users_remove'] = 'Quitar';
$string['users_added_singular'] = 'Se añadió 1 usuario.';
$string['users_added_plural'] = 'Se añadieron {$a} usuarios.';
$string['users_add_failed_singular'] = '1 usuario no se pudo añadir.';
$string['users_add_failed_plural'] = '{$a} usuarios no se pudieron añadir.';
$string['users_removed_singular'] = 'Se quitó 1 usuario.';
$string['users_removed_plural'] = 'Se quitaron {$a} usuarios.';
$string['users_sync_all'] = 'Sincronizar todo';
$string['users_sync_queued'] = 'Sincronización en cola. Se ejecutará en segundo plano.';
$string['potential_users'] = 'Usuarios potenciales';
$string['existing_users'] = 'Usuarios existentes';

$string['users_manage'] = 'Gestionar usuarios';
$string['task_sync_users'] = 'Sincronizar usuarios de MoodleMCP';
$string['taskfailed'] = 'La tarea de MoodleMCP falló: {$a}';

// Privacidad — tabla local de metadata de claves.
$string['privacy:metadata:localkeys'] = 'Metadata de claves MCP almacenada localmente (nunca valores de claves ni tokens).';
$string['privacy:metadata:localkeys:userid'] = 'El usuario al que pertenece la clave MCP.';
$string['privacy:metadata:localkeys:panelkeyid'] = 'El identificador de la clave en el panel.';
$string['privacy:metadata:localkeys:keylast4'] = 'Los últimos 4 caracteres de la clave, para identificarla.';
$string['privacy:metadata:localkeys:roles'] = 'Los roles de Moodle con los que puede actuar la clave.';
$string['privacy:metadata:localkeys:status'] = 'El estado de la clave (activa, suspendida o revocada).';
$string['privacy:metadata:localkeys:sentat'] = 'Cuándo se envió la clave por email al usuario.';

// Caducidad de claves.
$string['keys_section'] = 'Claves MCP';
$string['key_lifetime_days'] = 'Vigencia de la clave (días)';
$string['key_lifetime_days_desc'] = 'Cuánto tiempo es válida una clave MCP recién emitida. 0 significa sin caducidad. Las claves se renuevan automáticamente antes de caducar (se envía una nueva por email al usuario).';

// Errores de guard de webservices — el detalle viaja en el MESSAGE (con
// debugging apagado Moodle nunca envía debuginfo).
$string['errordetail'] = '{$a}';
$string['license_recheck'] = 'Verificar ahora';
