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
 * Version: 1.0
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
        <h2><?php _e( 'Duplicate Metas', 'duplicate-metas' ); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'duplicate_metas', 'duplicate_metas_nonce' ); ?>
            <label for="post_type_to_replace"><?php _e( 'Post type to replace', 'duplicate-metas' ); ?></label>
            <select name="post_type_to_replace" id="post_type_to_replace">
                <?php
                $post_types = get_post_types( array( 'public' => true ), 'objects' );
                foreach ( $post_types as $post_type ) {
                    echo '<option value="' . $post_type->name . '">' . $post_type->label . '</option>';
                }
                ?>
            </select>
            <br>
            <label for="old_meta"><?php _e( 'Old meta key', 'duplicate-metas' ); ?></label>
            <input type="text" name="old_meta" id="old_meta" required>
            <br>
            <label for="new_meta"><?php _e( 'New meta key', 'duplicate-metas' ); ?></label>
            <input type="text" name="new_meta" id="new_meta" required>
            <br>
            <!--Make backup checkbox-->
            <label for="make_backup"><?php _e( 'Make backup', 'duplicate-metas' ); ?></label>
            <input type="checkbox" name="make_backup" id="make_backup">
            <br>
            <input type="submit" value="<?php _e( 'Duplicate', 'duplicate-metas' ); ?>">

        </form>
    </div>
    <?php
}

// Duplicate metas

function duplicate_metas() {
    if ( isset( $_POST['old_meta'] ) && isset( $_POST['new_meta'] ) && isset( $_POST['post_type_to_replace']) ) {
        $old_meta = sanitize_text_field( $_POST['old_meta'] );
        $new_meta = sanitize_text_field( $_POST['new_meta'] );

        $args = array(
            'post_type' => $_POST['post_type_to_replace'],
            'posts_per_page' => -1,
        );

        $posts = get_posts( $args );

        //Make Log
        $log = array();        
        
        foreach ( $posts as $post ) {
            
            $old_value = get_post_meta( $post->ID, $old_meta, true );

        
            if ( $_POST['make_backup'] ) {
                $new_post_meta_data = get_post_meta( $post->ID,$new_meta );
                if ( !empty( $new_post_meta_data ) ) {
                    update_post_meta( $post->ID, $new_meta . '_backup_codigoverso', $new_post_meta_data );
                }
            }

            if ($_POST['new_meta']==='potencia-campodestacado'  && empty( get_post_meta( $post->ID, $new_meta, true ) )){
                preg_match('/(\d+[,\.]?\d*)\s*CV/i', $old_value, $matches);
                update_post_meta( $post->ID, $new_meta, $matches[1] );
                $log[] = array(
                    'post_id' => $post->ID,
                    'post_title' => $post->post_title,
                    'old_meta' => $old_meta,
                    'new_meta' => $new_meta,
                    'old_value' => $old_value,
                    'action' => 'duplicated',
                );
            }if ($_POST['new_meta']==='peso-campodestacado'  && empty( get_post_meta( $post->ID, $new_meta, true ) )){
                $peso_lleno = get_post_meta( $post->ID, 'peso_lleno', true );
                $peso = get_post_meta( $post->ID, 'peso', true );
                $peso_en_vacio = get_post_meta( $post->ID, 'peso_en_vacio', true );
                $peso_en_seco = get_post_meta( $post->ID, 'peso_en_seco', true );
                if ($peso_lleno){
                    update_post_meta( $post->ID, $new_meta, $peso_lleno);
                }elseif ($peso){
                    update_post_meta( $post->ID, $new_meta, $peso );
                }elseif ($peso_en_vacio){
                    update_post_meta( $post->ID, $new_meta, $peso_en_vacio );
                }elseif ($peso_en_seco){
                    update_post_meta( $post->ID, $new_meta, $peso_en_seco );
                }
                $log[] = array(
                    'post_id' => $post->ID,
                    'post_title' => $post->post_title,
                    'old_meta' => $old_meta,
                    'new_meta' => $new_meta,
                    'old_value' => $old_value,
                    'action' => 'duplicated',
                );

            }elseif ( $old_value && empty( get_post_meta( $post->ID, $new_meta, true ) ) ) {
                update_post_meta( $post->ID, $new_meta, $old_value );
                $log[] = array(
                    'post_id' => $post->ID,
                    'post_title' => $post->post_title,
                    'old_meta' => $old_meta,
                    'new_meta' => $new_meta,
                    'old_value' => $old_value,
                    'action' => 'duplicated',
                );
            }else{
                $log[] = array(
                    'post_id' => $post->ID,
                    'post_title' => $post->post_title,
                    'old_meta' => $old_meta,
                    'new_meta' => $new_meta,
                    'old_value' => $old_value,
                    'new_value' => get_post_meta( $post->ID, $new_meta, true ),
                    'action' => 'not duplicated',
                );
            }
            
        }
        
        //save log in current plugin folder
        $log_file = plugin_dir_path( __FILE__ ) . '/logs/log-' . date('Y-m-d-H-i-s') . '.json';
        file_put_contents( $log_file, json_encode( $log ) );
        
    }
}

add_action( 'admin_init', 'duplicate_metas' );

