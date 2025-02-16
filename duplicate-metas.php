<?php

/**
 * Plugin Name: Duplicate Metas
 * Plugin URI: https://codigoverso.es
 * Description: Plugin para copiar informaación de metas antiguos a los nuevos campos
 * Author: Alejandro Gimeno Martin
 * Author URI: https://codigoverso.es
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: duplicate-metas
 * Domain Path: /languages
 * Version: 1.6
 */

// Load text domain
function duplicate_metas_load_textdomain() {
    load_plugin_textdomain( 'duplicate-metas', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'duplicate_metas_load_textdomain' );

// Add menu
function duplicate_metas_menu() {
    add_menu_page( __( 'Duplicate Metas', 'duplicate-metas' ), __( 'Duplicate Metas', 'duplicate-metas' ), 'manage_options', 'duplicate-metas', 'duplicate_metas_page' );
}

add_action( 'admin_menu', 'duplicate_metas_menu' );

function duplicate_metas_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Duplicate Metas', 'duplicate-metas'); ?></h1>
        <p><?php _e('Seleccione el tipo de post y los campos meta que desea duplicar.', 'duplicate-metas'); ?></p>

        <form id="duplicate-metas-form" method="post" class="duplicate-metas-form">
            <?php wp_nonce_field('duplicate_metas', 'duplicate_metas_nonce'); ?>

            <fieldset class="field-group">
                <legend><?php _e('Configuración de duplicación', 'duplicate-metas'); ?></legend>

                <label for="post_type_to_replace"><strong><?php _e('Tipo de post:', 'duplicate-metas'); ?></strong></label>
                <select name="post_type_to_replace" id="post_type_to_replace">
                    <?php
                    $post_types = get_post_types(array('public' => true), 'objects');
                    foreach ($post_types as $post_type) {
                        echo '<option value="' . esc_attr($post_type->name) . '">' . esc_html($post_type->label) . '</option>';
                    }
                    ?>
                </select>

                <label for="old_meta"><strong><?php _e('Meta Key antigua:', 'duplicate-metas'); ?></strong></label>
                <input type="text" name="old_meta" id="old_meta" required placeholder="Ejemplo: old_meta_key">

                <label for="new_meta"><strong><?php _e('Meta Key nueva:', 'duplicate-metas'); ?></strong></label>
                <input type="text" name="new_meta" id="new_meta" required placeholder="Ejemplo: new_meta_key">

                <div class="checkbox-group">
                    <input type="checkbox" name="test_mode" id="test_mode">
                    <label for="test_mode"><?php _e('Generar CSV de prueba sin modificar datos', 'duplicate-metas'); ?></label>
                </div>
            </fieldset>

            <button type="button" id="sync-metas" class="button button-primary button-large"><?php _e('Ejecutar Sincronización', 'duplicate-metas'); ?></button>
            <div id="sync-loader" style="display: none; margin-top: 10px;">
                <img src="<?php echo esc_url(admin_url('images/spinner.gif')); ?>" alt="Cargando...">
            </div>
            <p id="sync-result"></p>
        </form>
    </div>

    <style>
        .duplicate-metas-form {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin-top: 20px;
        }
        .field-group {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
        .field-group legend {
            font-weight: bold;
            font-size: 14px;
        }
        .duplicate-metas-form label {
            display: block;
            font-weight: bold;
            margin: 10px 0 5px;
        }
        .duplicate-metas-form input[type="text"],
        .duplicate-metas-form select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            margin: 10px 0;
        }
        .checkbox-group input {
            margin-right: 10px;
        }
        #sync-result {
            margin-top: 10px;
            font-weight: bold;
        }
    </style>
    <?php
}

// Agregar nueva página de logs al menú del plugin
function duplicate_metas_logs_menu() {
    add_submenu_page(
        'duplicate-metas',
        __( 'Logs CSV', 'duplicate-metas' ),
        __( 'Logs', 'duplicate-metas' ),
        'manage_options',
        'duplicate-metas-logs',
        'duplicate_metas_logs_page'
    );
}
add_action( 'admin_menu', 'duplicate_metas_logs_menu' );

function duplicate_metas_logs_page() {
    $log_dir = plugin_dir_path(__FILE__) . 'logs/';

    // Verificar si se seleccionó un archivo para visualizar
    if (isset($_GET['view']) && !empty($_GET['view'])) {
        $file_to_view = $log_dir . basename($_GET['view']);
        $selected_action = isset($_GET['filter_action']) ? sanitize_text_field($_GET['filter_action']) : '';

        if (file_exists($file_to_view)) {
            echo '<div class="wrap">';
            echo '<h1>' . __( 'Ver contenido del CSV', 'duplicate-metas' ) . '</h1>';
            echo '<a href="' . admin_url('admin.php?page=duplicate-metas-logs') . '" class="button button-secondary">' . __('Volver a los logs', 'duplicate-metas') . '</a>';
            
            // Filtro por acción
            echo '<form method="get" style="margin-top: 10px;">';
            echo '<input type="hidden" name="page" value="duplicate-metas-logs">';
            echo '<input type="hidden" name="view" value="' . esc_attr($_GET['view']) . '">';
            echo '<label for="filter_action"><strong>' . __('Filtrar por Acción:', 'duplicate-metas') . '</strong></label>';
            echo '<select name="filter_action" id="filter_action">';
            echo '<option value="">' . __('Mostrar Todo', 'duplicate-metas') . '</option>';
            echo '<option value="Duplicado correctamente" ' . selected($selected_action, "Duplicado correctamente", false) . '>' . __('Duplicado correctamente', 'duplicate-metas') . '</option>';
            echo '<option value="No duplicado" ' . selected($selected_action, "No duplicado", false) . '>' . __('No duplicado', 'duplicate-metas') . '</option>';
            echo '</select>';
            echo '<input type="submit" value="' . __('Filtrar', 'duplicate-metas') . '" class="button button-primary">';
            echo '</form>';

            // Botón de exportación (ahora redirige a admin-post.php)
            echo '<form method="post" action="' . admin_url('admin-post.php') . '" style="margin-top: 10px;">';
            echo '<input type="hidden" name="action" value="export_filtered_csv">';
            echo '<input type="hidden" name="view" value="' . esc_attr($_GET['view']) . '">';
            echo '<input type="hidden" name="filter_action" value="' . esc_attr($selected_action) . '">';
            echo '<input type="submit" value="' . __('Descargar CSV Filtrado', 'duplicate-metas') . '" class="button button-secondary">';
            echo '</form>';

            // Contador de registros
            $total_records = 0;
            $filtered_records = 0;

            // Primera pasada para contar registros
            if (($handle = fopen($file_to_view, 'r')) !== FALSE) {
                $is_header = true;
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (!$is_header) {
                        $total_records++;
                    }
                    if (!$is_header && (!$selected_action || strpos(end($data), $selected_action) !== false)) {
                        $filtered_records++;
                    }
                    $is_header = false;
                }
                fclose($handle);
            }

            // Mostrar el contador de registros **antes** de la tabla
            echo '<p style="margin-top: 10px; font-weight: bold;">';
            if ($selected_action) {
                echo __('Mostrando ', 'duplicate-metas') . $filtered_records . __(' de ', 'duplicate-metas') . $total_records . __(' registros filtrados.', 'duplicate-metas');
            } else {
                echo __('Mostrando todos los ', 'duplicate-metas') . $total_records . __(' registros.', 'duplicate-metas');
            }
            echo '</p>';

            echo '<table class="widefat fixed striped" style="margin-top: 10px;">';

            // Segunda pasada para mostrar datos
            if (($handle = fopen($file_to_view, 'r')) !== FALSE) {
                $is_header = true;
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if ($is_header) {
                        echo '<tr>';
                        foreach ($data as $cell) {
                            echo '<th>' . esc_html($cell) . '</th>';
                        }
                        echo '</tr>';
                        $is_header = false;
                        continue;
                    }

                    // Aplicar filtro por acción si está seleccionado
                    $action_column = end($data);
                    if ($selected_action && strpos($action_column, $selected_action) === false) {
                        continue;
                    }

                    // Mostrar la fila si cumple con el filtro
                    echo '<tr>';
                    foreach ($data as $cell) {
                        echo '<td>' . esc_html($cell) . '</td>';
                    }
                    echo '</tr>';
                }
                fclose($handle);
            } else {
                echo '<p style="color: red;">' . __('Error al abrir el archivo CSV.', 'duplicate-metas') . '</p>';
            }

            echo '</table>';
            echo '</div>';
            return;
        } else {
            echo '<div class="error"><p>' . __( 'El archivo no existe o ha sido eliminado.', 'duplicate-metas' ) . '</p></div>';
        }
    }
}


function duplicate_metas_enqueue_scripts($hook) {
    if ($hook !== 'toplevel_page_duplicate-metas') {
        return;
    }

    // Cargar CSS
    wp_enqueue_style(
        'duplicate-metas-css',
        plugin_dir_url(__FILE__) . 'admin/css/duplicate-metas.css',
        array(),
        null
    );

    // Cargar JS
    wp_enqueue_script(
        'duplicate-metas-ajax',
        plugin_dir_url(__FILE__) . 'admin/js/duplicate-metas.js',
        array('jquery'),
        null,
        true
    );

    // Pasar variables de AJAX a JS
    wp_localize_script('duplicate-metas-ajax', 'duplicateMetasAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('duplicate_metas')
    ));
}
add_action('admin_enqueue_scripts', 'duplicate_metas_enqueue_scripts');


add_action('wp_ajax_duplicate_metas_ajax', 'duplicate_metas_ajax_callback');

function duplicate_metas_ajax_callback() {
    try {
        if (!isset($_POST['duplicate_metas_nonce']) || !wp_verify_nonce($_POST['duplicate_metas_nonce'], 'duplicate_metas')) {
            throw new Exception('Error de seguridad: nonce inválido.');
        }

        if (empty($_POST['old_meta']) || empty($_POST['new_meta']) || empty($_POST['post_type_to_replace'])) {
            throw new Exception('Faltan datos obligatorios para ejecutar la sincronización.');
        }

        $old_meta = sanitize_text_field($_POST['old_meta']);
        $new_meta = sanitize_text_field($_POST['new_meta']);
        $post_type = sanitize_text_field($_POST['post_type_to_replace']);
        $test_mode = isset($_POST['test_mode']);

        $args = array(
            'post_type'      => $post_type,
            'posts_per_page' => 100, // Procesar en lotes
            'paged'          => 1,
        );

        $total_processed = 0;
        $total_skipped = 0;

        // Crear directorio logs si no existe
        $log_dir = plugin_dir_path(__FILE__) . 'logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        // Nombre del archivo CSV
        $log_type = $test_mode ? 'TEST-' : '';
        $log_file = $log_dir . $log_type . 'log-' . date('Y-m-d-H-i-s') . '.csv';

        // Abrir el archivo CSV para escritura
        $file = fopen($log_file, 'w');
        if (!$file) {
            throw new Exception('No se pudo crear el archivo de logs en: ' . $log_file);
        }

        // Escribir encabezados del CSV
        fputcsv($file, ['Post ID', 'Post Title', 'Meta Key Antiguo', 'Meta Key Nuevo', 'Valor Antiguo', 'Valor Nuevo', 'Acción']);

        do {
            $posts = get_posts($args);
            if (empty($posts)) {
                break;
            }

            foreach ($posts as $post) {
                $old_value = get_post_meta($post->ID, $old_meta, true);
                $new_value = get_post_meta($post->ID, $new_meta, true);
                $action = 'No duplicado';
                $reason = '';

                // Determinar la razón por la que no se duplicó
                if (empty($old_value)) {
                    $reason = 'Meta antiguo vacío';
                } elseif (!empty($new_value)) {
                    $reason = 'Meta nuevo ya tiene un valor';
                } else {
                    // Proceder con la actualización si aplica
                    if (!$test_mode) {
                        update_post_meta($post->ID, $new_meta, $old_value);
                    }
                    $new_value = $old_value;
                    $action = 'Duplicado correctamente';
                    $reason = '';
                    $total_processed++;
                }

                // Guardar log en el archivo CSV con la razón
                fputcsv($file, [
                    $post->ID,
                    $post->post_title,
                    $old_meta,
                    $new_meta,
                    $old_value,
                    $new_value,
                    $action . ($reason ? " - $reason" : '')
                ]);

                if ($action === 'No duplicado') {
                    $total_skipped++;
                }
            }

            $args['paged']++;
            sleep(1); // Pequeña pausa para reducir la carga en el servidor

        } while (!empty($posts));

        fclose($file);

        wp_send_json_success(['message' => "Sincronización completada. Procesados: $total_processed, No duplicados: $total_skipped."]);

    } catch (Exception $e) {
        error_log('Error en duplicate_metas_ajax_callback: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Error en la sincronización: ' . $e->getMessage()]);
    }

    wp_die();
}


add_action('admin_post_export_filtered_csv', 'export_filtered_csv');

function export_filtered_csv() {
    if (!isset($_POST['view'])) {
        wp_die(__('Error: No se proporcionó un archivo CSV.', 'duplicate-metas'));
    }

    $log_dir = plugin_dir_path(__FILE__) . 'logs/';
    $file_to_view = $log_dir . basename($_POST['view']);
    $selected_action = isset($_POST['filter_action']) ? sanitize_text_field($_POST['filter_action']) : '';

    if (!file_exists($file_to_view)) {
        wp_die(__('Error: El archivo CSV no existe.', 'duplicate-metas'));
    }

    // Definir nombre del archivo de descarga
    $filename = 'filtered-' . basename($_POST['view']);

    // Configurar headers para descarga
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    if (($handle = fopen($file_to_view, 'r')) !== FALSE) {
        $is_header = true;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if ($is_header) {
                fputcsv($output, $data); // Escribir encabezado
                $is_header = false;
                continue;
            }

            // Aplicar filtro por acción si está seleccionado
            $action_column = end($data);
            if ($selected_action && strpos($action_column, $selected_action) === false) {
                continue;
            }

            // Escribir la fila en el CSV de salida
            fputcsv($output, $data);
        }
        fclose($handle);
    }

    fclose($output);
    exit;
}
