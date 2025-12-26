<?php

/*
Copyright EXOR Group ltd 2025 
Version 1.0.0.0 
APEX Laravel Auditing
Description: Spanish language file for APEX Audit messages and labels.
*/

return [
    'actions' => [
        'create' => 'Creado',
        'update' => 'Actualizado',
        'delete' => 'Eliminado',
        'restore' => 'Restaurado',
        'force_delete' => 'Eliminado Permanentemente',
        'retrieve' => 'Recuperado',
        'rollback' => 'Revertido',
        'batch_operation' => 'Operación en Lote',
        'custom' => 'Acción Personalizada',
    ],

    'descriptions' => [
        'created_new' => 'Creó nuevo :model #:id',
        'updated_model' => 'Actualizó :model #:id - Cambios: :fields',
        'deleted_model' => 'Eliminó :model #:id',
        'restored_model' => 'Restauró :model #:id',
        'force_deleted_model' => 'Eliminó permanentemente :model #:id',
        'performed_action' => 'Realizó :action en :model #:id',
    ],

    'fields' => [
        'id' => 'ID',
        'action' => 'Acción',
        'description' => 'Descripción',
        'user' => 'Usuario',
        'date' => 'Fecha',
        'model' => 'Modelo',
        'changes' => 'Cambios',
        'old_value' => 'Valor Anterior',
        'new_value' => 'Valor Nuevo',
        'empty' => '(vacío)',
        'field_label' => 'Campo',
    ],

    'rollback' => [
        'title' => 'Revertir Acción',
        'confirm' => '¿Está seguro de que desea revertir esta acción?',
        'success' => 'Acción revertida exitosamente',
        'failed' => 'Falló la reversión',
        'not_allowed' => 'No se permite revertir esta acción',
        'already_rolled_back' => 'Esta acción ya ha sido revertida',
        'no_permission' => 'No tiene permisos para revertir esta acción',
        'missing_data' => 'No hay datos de reversión disponibles para esta acción',
        'model_not_found' => 'El registro original ya no existe',
        'record_exists' => 'El registro ya existe y no puede ser restaurado',
        'validation_failed' => 'Falló la validación de reversión',
        'functionality_disabled' => 'La funcionalidad de reversión está deshabilitada',
        'field_permission_denied' => 'No se pueden revertir campos no auditables',
        'preview_title' => 'Vista Previa de Reversión',
        'preview_description' => 'Esto restaurará los siguientes cambios:',
    ],

    'history' => [
        'title' => 'Historial de Cambios',
        'no_records' => 'No se encontraron registros de historial',
        'show_changes' => 'Mostrar Cambios',
        'hide_changes' => 'Ocultar Cambios',
        'view_details' => 'Ver Detalles',
        'rollback_action' => 'Revertir',
        'rolled_back_by' => 'Revertido por :user el :date',
        'rollback_available' => 'Reversión Disponible',
        'cannot_rollback' => 'No se Puede Revertir',
        'filter_by_action' => 'Filtrar por Acción',
        'filter_by_user' => 'Filtrar por Usuario',
        'filter_by_date' => 'Filtrar por Fecha',
        'clear_filters' => 'Limpiar Filtros',
        'export' => 'Exportar Historial',
    ],

    'cleanup' => [
        'title' => 'Limpieza de Auditoría',
        'dry_run_mode' => 'MODO DE PRUEBA - No se eliminarán registros realmente',
        'history_retention' => 'Retención de historial: :days días (corte: :date)',
        'audit_retention' => 'Retención de auditoría: :days días (corte: :date)',
        'found_records' => 'Se encontraron :count registros para limpieza',
        'no_records' => 'No se encontraron registros para limpieza',
        'confirm_delete' => '¿Eliminar :count registros anteriores a :date?',
        'deleted_successfully' => 'Se eliminaron exitosamente :count registros',
        'cancelled' => 'Limpieza cancelada por el usuario',
        'skipped' => 'Omitido - :reason',
        'retention_not_configured' => 'No hay política de retención configurada',
        'audit_warning' => 'ADVERTENCIA: ¡Está a punto de eliminar permanentemente registros de auditoría!',
        'audit_confirmation' => 'Esta acción no se puede deshacer y puede violar requisitos de cumplimiento.',
        'absolutely_sure' => '¿Está ABSOLUTAMENTE SEGURO de que desea eliminar :count registros de auditoría?',
        'type_confirmation' => 'Escriba "DELETE AUDIT RECORDS" para confirmar',
        'confirmation_failed' => 'No se ingresó la frase de confirmación',
    ],

    'verification' => [
        'title' => 'Verificación de Firmas',
        'starting' => 'Iniciando verificación de firmas...',
        'signatures_disabled' => 'Las firmas de auditoría están deshabilitadas en la configuración',
        'found_records' => 'Se encontraron :count registros de auditoría para verificar',
        'no_records' => 'No se encontraron registros de auditoría para verificación',
        'valid_signature' => 'VÁLIDA',
        'invalid_signature' => 'INVÁLIDA',
        'all_valid' => 'Todas las firmas son válidas - no se detectó manipulación',
        'tampering_detected' => '¡MANIPULACIÓN DETECTADA!',
        'invalid_records' => 'Los siguientes registros tienen firmas inválidas:',
        'recommended_actions' => 'Acciones recomendadas:',
        'investigate_changes' => 'Investigar cuándo y cómo se modificaron estos registros',
        'check_access_logs' => 'Verificar registros de acceso a la base de datos para cambios no autorizados',
        'notify_security' => 'Notificar al equipo de seguridad si se confirma la manipulación',
        'restore_backup' => 'Considerar restaurar desde respaldo si la integridad está comprometida',
        'verification_completed' => 'Verificación completada',
        'results_summary' => 'Resultados de Verificación',
        'total_processed' => 'Total de registros procesados: :count',
        'valid_count' => 'Firmas válidas: :count',
        'invalid_count' => 'Firmas inválidas: :count',
        'validity_rate' => 'Tasa de validez: :percentage%',
    ],

    'errors' => [
        'general_error' => 'Ocurrió un error',
        'audit_failed' => 'Falló el registro de auditoría',
        'signature_failed' => 'Falló la generación de firma',
        'permission_denied' => 'Permiso denegado',
        'model_not_found' => 'Modelo no encontrado',
        'invalid_data' => 'Datos inválidos proporcionados',
        'database_error' => 'Ocurrió un error de base de datos',
        'configuration_error' => 'Error de configuración',
        'file_not_found' => 'Archivo no encontrado',
        'invalid_format' => 'Formato inválido',
    ],

    'success' => [
        'audit_created' => 'Registro de auditoría creado exitosamente',
        'action_completed' => 'Acción completada exitosamente',
        'settings_saved' => 'Configuración guardada exitosamente',
        'data_exported' => 'Datos exportados exitosamente',
        'cache_cleared' => 'Caché limpiado exitosamente',
    ],

    'filters' => [
        'all' => 'Todos',
        'today' => 'Hoy',
        'yesterday' => 'Ayer',
        'last_week' => 'Última Semana',
        'last_month' => 'Último Mes',
        'last_year' => 'Último Año',
        'custom_range' => 'Rango Personalizado',
        'from_date' => 'Desde Fecha',
        'to_date' => 'Hasta Fecha',
        'apply' => 'Aplicar',
        'reset' => 'Restablecer',
    ],

    'pagination' => [
        'previous' => 'Anterior',
        'next' => 'Siguiente',
        'showing' => 'Mostrando :first a :last de :total resultados',
        'per_page' => 'Por página',
    ],

    'commands' => [
        'cleanup_title' => 'Limpieza de APEX Audit',
        'verify_title' => 'Verificación de APEX Audit',
        'completed_successfully' => 'completado exitosamente',
        'failed_with_errors' => 'falló con errores',
    ],

    'widgets' => [
        'history_widget' => 'Widget de Historial',
        'audit_summary' => 'Resumen de Auditoría',
        'recent_activity' => 'Actividad Reciente',
        'user_activity' => 'Actividad del Usuario',
        'system_stats' => 'Estadísticas del Sistema',
    ],

    'permissions' => [
        'view_audit' => 'Ver Auditoría',
        'view_history' => 'Ver Historial',
        'rollback_changes' => 'Revertir Cambios',
        'cleanup_records' => 'Limpiar Registros',
        'verify_signatures' => 'Verificar Firmas',
        'export_data' => 'Exportar Datos',
        'manage_settings' => 'Gestionar Configuración',
    ],
];
