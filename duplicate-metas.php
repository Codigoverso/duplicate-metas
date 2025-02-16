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
 * Version: 1.5
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

// Page
function duplicate_metas_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Duplicate Metas', 'duplicate-metas'); ?></h1>
        <p><?php _e('Seleccione el tipo de post y los campos meta que desea duplicar.', 'duplicate-metas'); ?></p>

        <form method="post" action="" class="duplicate-metas-form">
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

            <input type="submit" value="<?php _e('Duplicar Metas', 'duplicate-metas'); ?>" class="button button-primary button-large">
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
        .button-primary {
            background: #0073aa;
            border: none;
            padding: 12px 20px;
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            border-radius: 5px;
            cursor: pointer;
            display: block;
            width: 100%;
            text-align: center;
        }
        .button-primary:hover {
            background: #005177;
        }
    </style>
    <?php
}

function duplicate_metas() {
    if ( isset( $_POST['old_meta'] ) && isset( $_POST['new_meta'] ) && isset( $_POST['post_type_to_replace']) ) {
        $old_meta = sanitize_text_field( $_POST['old_meta'] );
        $new_meta = sanitize_text_field( $_POST['new_meta'] );
        $test_mode = isset($_POST['test_mode']); // Verifica si el checkbox de prueba está marcado

        $args = array(
            'post_type' => $_POST['post_type_to_replace'],
            'posts_per_page' => -1,
        );

        $posts = get_posts( $args );

        // Crear directorio logs si no existe
        $log_dir = plugin_dir_path( __FILE__ ) . 'logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        // Nombre del archivo CSV
        $log_type = $test_mode ? 'TEST-' : ''; // Prefijo en caso de modo test
        $log_file = $log_dir . $log_type . 'log-' . date('Y-m-d-H-i-s') . '.csv';

        // Abrir el archivo CSV para escritura
        $file = fopen($log_file, 'w');
        if ($file) {
            // Escribir encabezados
            fputcsv($file, ['Post ID', 'Post Title', 'Meta Key Antiguo', 'Meta Key Nuevo', 'Valor Antiguo', 'Valor Nuevo', 'Acción']);

            foreach ( $posts as $post ) {
                $old_value = get_post_meta( $post->ID, $old_meta, true );
                $new_value = get_post_meta( $post->ID, $new_meta, true );
                $action = 'no duplicado';


                // Caso especial para potencia-campodestacado
                if ($_POST['new_meta'] === 'potencia-campodestacado' && empty($new_value)) {
                    preg_match('/(\d+[,\.]?\d*)\s*CV/i', $old_value, $matches);
                    if (!empty($matches[1])) {
                        if (!$test_mode) {
                            update_post_meta( $post->ID, $new_meta, $matches[1] );
                        }
                        $new_value = $matches[1];
                        $action = 'duplicado correctamente';
                    } else {
                        $action = 'No se encontró un valor de CV en el meta original';
                    }
                } 
                // Caso especial para peso-campodestacado
                elseif ($_POST['new_meta'] === 'peso-campodestacado' && empty($new_value)) {
                    $peso_lleno = get_post_meta( $post->ID, 'peso_lleno', true );
                    $peso = get_post_meta( $post->ID, 'peso', true );
                    $peso_en_vacio = get_post_meta( $post->ID, 'peso_en_vacio', true );
                    $peso_en_seco = get_post_meta( $post->ID, 'peso_en_seco', true );

                    if ($peso_lleno) {
                        if (!$test_mode) {
                            update_post_meta( $post->ID, $new_meta, $peso_lleno);
                        }
                        $new_value = $peso_lleno;
                        $action = 'duplicado correctamente';
                    } elseif ($peso) {
                        if (!$test_mode) {
                            update_post_meta( $post->ID, $new_meta, $peso );
                        }
                        $new_value = $peso;
                        $action = 'duplicado correctamente';
                    } elseif ($peso_en_vacio) {
                        if (!$test_mode) {
                            update_post_meta( $post->ID, $new_meta, $peso_en_vacio );
                        }
                        $new_value = $peso_en_vacio;
                        $action = 'duplicado correctamente';
                    } elseif ($peso_en_seco) {
                        if (!$test_mode) {
                            update_post_meta( $post->ID, $new_meta, $peso_en_seco );
                        }
                        $new_value = $peso_en_seco;
                        $action = 'duplicado correctamente';
                    } else {
                        $action = 'No se encontró ningún valor de peso en los meta keys relacionados';
                    }
                } 
                // Copia general de meta fields
                elseif (!empty($old_value) && empty($new_value)) {
                    if (!$test_mode) {
                        update_post_meta( $post->ID, $new_meta, $old_value );
                    }
                    $new_value = $old_value;
                    $action = 'duplicado correctamente';
                } 
                // Razones por las que no se duplicó
                elseif (empty($old_value)) {
                    $action = 'Meta antiguo vacío, no hay valor para duplicar';
                } elseif (!empty($new_value)) {
                    $action = 'Meta nuevo ya tiene un valor, no se sobrescribe';
                } else {
                    $action = 'Razón desconocida';
                }

                // Guardar log en el archivo CSV con la razón
                fputcsv($file, [
                    $post->ID,
                    $post->post_title,
                    $old_meta,
                    $new_meta,
                    $old_value,
                    $new_value,
                    $action
                ]);
            }

            // Cerrar el archivo CSV
            fclose($file);
        }
    }
}

add_action( 'admin_init', 'duplicate_metas' );


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

        if (file_exists($file_to_view)) {
            echo '<div class="wrap">';
            echo '<h1>' . __( 'Ver contenido del CSV', 'duplicate-metas' ) . '</h1>';
            echo '<a href="' . admin_url('admin.php?page=duplicate-metas-logs') . '" class="button button-secondary">' . __('Volver a los logs', 'duplicate-metas') . '</a>';
            echo '<table class="widefat fixed striped" style="margin-top: 20px;">';

            // Abrir archivo CSV y mostrar su contenido
            if (($handle = fopen($file_to_view, 'r')) !== FALSE) {
                $is_header = true;
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    echo '<tr>';
                    foreach ($data as $cell) {
                        if ($is_header) {
                            echo '<th>' . esc_html($cell) . '</th>';
                        } else {
                            echo '<td>' . esc_html($cell) . '</td>';
                        }
                    }
                    echo '</tr>';
                    $is_header = false;
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

    // Eliminar archivo si se solicita
    if (isset($_GET['delete']) && !empty($_GET['delete'])) {
        $file_to_delete = $log_dir . basename($_GET['delete']);
        if (file_exists($file_to_delete)) {
            unlink($file_to_delete);
            echo '<div class="updated"><p>' . __( 'Archivo eliminado correctamente.', 'duplicate-metas' ) . '</p></div>';
        } else {
            echo '<div class="error"><p>' . __( 'No se pudo eliminar el archivo. Puede que ya haya sido eliminado.', 'duplicate-metas' ) . '</p></div>';
        }
    }

    // Obtener lista de archivos CSV en la carpeta logs y ordenarlos por fecha descendente
    $files = glob($log_dir . '*.csv');
    if (!empty($files)) {
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a); // Ordena por fecha de modificación descendente
        });
    }

    ?>
    <div class="wrap">
        <h1><?php _e('Logs CSV', 'duplicate-metas'); ?></h1>
        <p><?php _e('Aquí puedes descargar o visualizar los archivos de logs generados.', 'duplicate-metas'); ?></p>

        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 40%;"><?php _e('Archivo', 'duplicate-metas'); ?></th>
                    <th style="width: 30%;"><?php _e('Fecha de creación', 'duplicate-metas'); ?></th>
                    <th style="width: 30%;"><?php _e('Acciones', 'duplicate-metas'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($files)) {
                    foreach ($files as $file) {
                        $filename = basename($file);
                        $file_url = plugin_dir_url(__FILE__) . 'logs/' . $filename;
                        $file_time = date('Y-m-d H:i:s', filemtime($file));
                        echo "<tr>
                                <td><strong>{$filename}</strong></td>
                                <td>{$file_time}</td>
                                <td>
                                    <a href='{$file_url}' class='button button-primary' download>" . __('Descargar', 'duplicate-metas') . "</a>
                                    <a href='" . admin_url('admin.php?page=duplicate-metas-logs&view=' . urlencode($filename)) . "' class='button'>" . __('Ver', 'duplicate-metas') . "</a>
                                    <a href='" . admin_url('admin.php?page=duplicate-metas-logs&delete=' . urlencode($filename)) . "' class='button button-secondary' onclick='return confirm(\"¿Seguro que quieres eliminar este archivo?\");'>" . __('Eliminar', 'duplicate-metas') . "</a>
                                </td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='3' style='text-align: center;'>" . __('No hay logs disponibles.', 'duplicate-metas') . "</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}
