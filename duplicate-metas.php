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
 * Version: 1.8
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
        $filter_empty_new_meta = isset($_GET['filter_empty_new_meta']) ? sanitize_text_field($_GET['filter_empty_new_meta']) : '';

        if (file_exists($file_to_view)) {
            echo '<div class="wrap">';
            echo '<h1>' . __( 'Ver contenido del CSV', 'duplicate-metas' ) . '</h1>';
            echo '<a href="' . admin_url('admin.php?page=duplicate-metas-logs') . '" class="button button-secondary">' . __('Volver a los logs', 'duplicate-metas') . '</a>';
            
            // Formulario de filtros
            echo '<form method="get" style="margin-top: 10px;">';
            echo '<input type="hidden" name="page" value="duplicate-metas-logs">';
            echo '<input type="hidden" name="view" value="' . esc_attr($_GET['view']) . '">';

            // Filtro por acción
            echo '<label for="filter_action"><strong>' . __('Filtrar por Acción:', 'duplicate-metas') . '</strong></label>';
            echo '<select name="filter_action" id="filter_action">';
            echo '<option value="">' . __('Mostrar Todo', 'duplicate-metas') . '</option>';
            echo '<option value="Duplicado correctamente" ' . selected($selected_action, "Duplicado correctamente", false) . '>' . __('Duplicado correctamente', 'duplicate-metas') . '</option>';
            echo '<option value="No duplicado" ' . selected($selected_action, "No duplicado", false) . '>' . __('No duplicado', 'duplicate-metas') . '</option>';
            echo '</select>';

            // Filtro para "Nuevo Valor Vacío"
            echo '<label for="filter_empty_new_meta"><strong>' . __('Mostrar solo vacíos:', 'duplicate-metas') . '</strong></label>';
            echo '<select name="filter_empty_new_meta" id="filter_empty_new_meta">';
            echo '<option value="">' . __('No filtrar', 'duplicate-metas') . '</option>';
            echo '<option value="yes" ' . selected($filter_empty_new_meta, "yes", false) . '>' . __('Sí, solo vacíos', 'duplicate-metas') . '</option>';
            echo '</select>';

            echo '<input type="submit" value="' . __('Filtrar', 'duplicate-metas') . '" class="button button-primary">';
            echo '</form>';

            // Botón de exportación con los filtros aplicados
            echo '<form method="post" action="' . admin_url('admin-post.php') . '" style="margin-top: 10px;">';
            echo '<input type="hidden" name="action" value="export_filtered_csv">';
            echo '<input type="hidden" name="view" value="' . esc_attr($_GET['view']) . '">';
            echo '<input type="hidden" name="filter_action" value="' . esc_attr($selected_action) . '">';
            echo '<input type="hidden" name="filter_empty_new_meta" value="' . esc_attr($filter_empty_new_meta) . '">';
            echo '<input type="submit" value="' . __('Descargar CSV Filtrado', 'duplicate-metas') . '" class="button button-secondary">';
            echo '</form>';

            echo '<table class="widefat fixed striped" style="margin-top: 10px;">';
            echo '<thead><tr>';
            echo '<th>' . __('Post ID', 'duplicate-metas') . '</th>';
            echo '<th>' . __('Post Title', 'duplicate-metas') . '</th>';
            echo '<th>' . __('Meta Key Antiguo', 'duplicate-metas') . '</th>';
            echo '<th>' . __('Meta Key Nuevo', 'duplicate-metas') . '</th>';
            echo '<th>' . __('Valor Antiguo', 'duplicate-metas') . '</th>';
            echo '<th>' . __('Valor Nuevo', 'duplicate-metas') . '</th>';
            echo '<th>' . __('Acción', 'duplicate-metas') . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            if (($handle = fopen($file_to_view, 'r')) !== FALSE) {
                $is_header = true;
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if ($is_header) {
                        $is_header = false;
                        continue;
                    }

                    // Última columna = Acción, penúltima columna = Nuevo Valor
                    $action_column = end($data);
                    $new_meta_value = prev($data);

                    // Aplicar filtros
                    if ($selected_action && strpos($action_column, $selected_action) === false) {
                        continue;
                    }
                    if ($filter_empty_new_meta === "yes" && !empty($new_meta_value)) {
                        continue;
                    }

                    echo '<tr>';
                    foreach ($data as $cell) {
                        echo '<td>' . esc_html($cell) . '</td>';
                    }
                    echo '</tr>';
                }
                fclose($handle);
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';
            return;
        }
    }

    // Listado de logs disponibles
    $files = glob($log_dir . '*.csv');

    echo '<div class="wrap">';
    echo '<h1>' . __('Logs CSV', 'duplicate-metas') . '</h1>';
    echo '<p>' . __('Aquí puedes descargar o visualizar los archivos de logs generados.', 'duplicate-metas') . '</p>';

    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>' . __('Archivo', 'duplicate-metas') . '</th>';
    echo '<th>' . __('Fecha de creación', 'duplicate-metas') . '</th>';
    echo '<th>' . __('Acciones', 'duplicate-metas') . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    if (!empty($files)) {
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        foreach ($files as $file) {
            $filename = basename($file);
            $file_url = plugin_dir_url(__FILE__) . 'logs/' . $filename;
            $file_time = date('Y-m-d H:i:s', filemtime($file));

            echo "<tr>";
            echo "<td><strong>{$filename}</strong></td>";
            echo "<td>{$file_time}</td>";
            echo "<td>";
            echo "<a href='{$file_url}' class='button button-primary' download>" . __('Descargar', 'duplicate-metas') . "</a>";
            echo "<a href='" . admin_url('admin.php?page=duplicate-metas-logs&view=' . urlencode($filename)) . "' class='button'>" . __('Ver', 'duplicate-metas') . "</a>";
            echo "<a href='" . admin_url('admin-post.php?action=delete_csv_log&file=' . urlencode($filename)) . "' class='button button-secondary' onclick='return confirm(\"¿Seguro que quieres eliminar este archivo?\");'>" . __('Eliminar', 'duplicate-metas') . "</a>";
            echo "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='3' style='text-align: center;'>" . __('No hay logs disponibles.', 'duplicate-metas') . "</td></tr>";
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
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
            'posts_per_page' => 100, // Procesamos en lotes de 100
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

                // **Caso especial: Extraer CV de "potencia-campodestacado"**
                if ($new_meta === 'potencia-campodestacado') {
                    preg_match('/(\d+[,\.]?\d*)\s*CV/i', $old_value, $matches);
                    if (!empty($matches[1])) {
                        if (!$test_mode) {
                            update_post_meta($post->ID, $new_meta, $matches[1]);
                        }
                        $new_value = $matches[1];
                        $action = 'Duplicado correctamente';
                        $reason = 'CV extraído correctamente';
                        $total_processed++;
                    } else {
                        $reason = 'No se encontró un valor válido en el meta original';
                    }
                }
                // **Caso especial: Determinar peso en "peso-campodestacado"**
                elseif ($new_meta === 'peso-campodestacado') {
                    $peso_lleno = get_post_meta($post->ID, 'peso_lleno', true);
                    $peso = get_post_meta($post->ID, 'peso', true);
                    $peso_en_vacio = get_post_meta($post->ID, 'peso_en_vacio', true);
                    $peso_en_seco = get_post_meta($post->ID, 'peso_en_seco', true);

                    if ($peso_lleno) {
                        if (!$test_mode) {
                            update_post_meta($post->ID, $new_meta, $peso_lleno);
                        }
                        $old_value = $peso_lleno;
                        $new_value = $peso_lleno;
                        $action = 'Duplicado correctamente';
                        $reason = 'Valor tomado de peso_lleno';
                        $total_processed++;
                    } elseif ($peso) {
                        if (!$test_mode) {
                            update_post_meta($post->ID, $new_meta, $peso);
                        }
                        $old_value = $peso;
                        $new_value = $peso;
                        $action = 'Duplicado correctamente';
                        $reason = 'Valor tomado de peso';
                        $total_processed++;
                    } elseif ($peso_en_vacio) {
                        if (!$test_mode) {
                            update_post_meta($post->ID, $new_meta, $peso_en_vacio);
                        }
                        $old_value = $peso_en_vacio;
                        $new_value = $peso_en_vacio;
                        $action = 'Duplicado correctamente';
                        $reason = 'Valor tomado de peso_en_vacio';
                        $total_processed++;
                    } elseif ($peso_en_seco) {
                        if (!$test_mode) {
                            update_post_meta($post->ID, $new_meta, $peso_en_seco);
                        }
                        $old_value = $peso_en_seco;
                        $new_value = $peso_en_seco;
                        $action = 'Duplicado correctamente';
                        $reason = 'Valor tomado de peso_en_seco';
                        $total_processed++;
                    } else {
                        $reason = 'No se encontró un valor válido de peso';
                    }
                }
                // **Caso general: Copia del meta si cumple las condiciones**
                else {
                    if (!$test_mode) {
                        update_post_meta($post->ID, $new_meta, $old_value);
                    }
                    $new_value = $old_value;
                    $action = 'Duplicado correctamente';
                    $total_processed++;
                }


                // **1️ Prioridad: Si el nuevo meta ya tiene datos, no sobrescribimos**
                if (!empty($new_value)) {
                    $reason = 'Meta nuevo ya tiene un valor';
                }
                // **2 Prioridad: Si el viejo meta no tiene datos, no hay nada que copiar**
                elseif (empty($old_value)) {
                    $reason = 'Meta antiguo vacío';
                }
                //3 Prioridad final: Si no encaja en ninguna de las anteriores, "Razón desconocida"**
                if ($action === 'No duplicado' && empty($reason)) {
                    $reason = 'Razón desconocida';
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
//Permitimos la descarga del nuevo fichero csv filtrado

function export_filtered_csv() {
    if (!isset($_POST['view'])) {
        wp_die(__('Error: No se proporcionó un archivo CSV.', 'duplicate-metas'));
    }

    $log_dir = plugin_dir_path(__FILE__) . 'logs/';
    $file_to_view = $log_dir . basename($_POST['view']);
    $selected_action = isset($_POST['filter_action']) ? sanitize_text_field($_POST['filter_action']) : '';
    $filter_empty_new_meta = isset($_POST['filter_empty_new_meta']) ? sanitize_text_field($_POST['filter_empty_new_meta']) : '';

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

            // Última columna = Acción, penúltima columna = Nuevo Valor
            $action_column = end($data);
            $new_meta_value = prev($data);

            // Aplicar filtro por acción
            if ($selected_action && strpos($action_column, $selected_action) === false) {
                continue;
            }

            // Aplicar filtro de "Nuevo Valor Vacío"
            if ($filter_empty_new_meta === "yes" && !empty($new_meta_value)) {
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

add_action('admin_post_delete_csv_log', 'delete_csv_log');

function delete_csv_log() {
    if (!current_user_can('manage_options')) {
        wp_die(__('No tienes permisos para eliminar archivos.', 'duplicate-metas'));
    }

    if (!isset($_GET['file']) || empty($_GET['file'])) {
        wp_die(__('Error: No se proporcionó un archivo para eliminar.', 'duplicate-metas'));
    }

    $log_dir = plugin_dir_path(__FILE__) . 'logs/';
    $file_to_delete = $log_dir . basename($_GET['file']);

    if (!file_exists($file_to_delete)) {
        wp_die(__('Error: El archivo CSV no existe.', 'duplicate-metas'));
    }

    // Intentar eliminar el archivo
    if (unlink($file_to_delete)) {
        wp_redirect(admin_url('admin.php?page=duplicate-metas-logs&message=deleted'));
        exit;
    } else {
        wp_die(__('Error al intentar eliminar el archivo.', 'duplicate-metas'));
    }
}
