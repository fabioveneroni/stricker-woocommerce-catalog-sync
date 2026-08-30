<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SWCS_Admin {
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_post_swcs_save_settings', array( __CLASS__, 'save_settings' ) );
        add_action( 'admin_post_swcs_test_download', array( __CLASS__, 'test_download' ) );
    }

    public static function menu() {
        add_menu_page( 'Stricker Catalog Sync', 'Stricker Catalog', 'manage_options', 'swcs', array( __CLASS__, 'dashboard' ), 'dashicons-download', 56 );
    }

    public static function dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $key = SWCS_Catalog::get_access_key();
        $language = SWCS_Catalog::get_language();
        $notice = get_transient( 'swcs_notice_' . get_current_user_id() );
        if ( $notice ) {
            delete_transient( 'swcs_notice_' . get_current_user_id() );
            echo '<div class="notice notice-' . esc_attr( $notice['type'] ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
        }
        echo '<div class="wrap"><h1>Stricker Catalog Sync</h1>';
        echo '<p>Versão ' . esc_html( SWCS_VERSION ) . '. Nesta primeira etapa, o plugin configura a Access Key e prepara o download dos catálogos XML oficiais da Stricker.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'swcs_save_settings' );
        echo '<input type="hidden" name="action" value="swcs_save_settings">';
        echo '<table class="form-table"><tr><th><label for="swcs_access_key">Access Key</label></th><td><input type="password" class="regular-text" id="swcs_access_key" name="access_key" value="' . esc_attr( $key ) . '" autocomplete="off"><p class="description">A chave é armazenada nas opções do WordPress e não é exibida em mensagens de erro.</p></td></tr>';
        echo '<tr><th><label for="swcs_language">Idioma</label></th><td><select id="swcs_language" name="language"><option value="PT" ' . selected( $language, 'PT', false ) . '>Português (PT)</option><option value="EN" ' . selected( $language, 'EN', false ) . '>English (EN)</option></select></td></tr></table>';
        submit_button( 'Salvar configurações' );
        echo '</form><hr><h2>Teste de download</h2><p>O teste fará uma requisição pequena ao endpoint oficial usando <strong>ProductTypes</strong>. O ProductsTree não será baixado nesta etapa.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'swcs_test_download' );
        echo '<input type="hidden" name="action" value="swcs_test_download">';
        submit_button( 'Testar download do ProductTypes', 'secondary', 'submit', false );
        echo '</form></div>';
    }

    public static function save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sem permissão.' );
        check_admin_referer( 'swcs_save_settings' );
        $key = isset( $_POST['access_key'] ) ? sanitize_text_field( wp_unslash( $_POST['access_key'] ) ) : '';
        $language = isset( $_POST['language'] ) ? sanitize_key( wp_unslash( $_POST['language'] ) ) : 'PT';
        if ( ! in_array( $language, array( 'PT', 'EN' ), true ) ) $language = 'PT';
        SWCS_Catalog::save_settings( $key, $language );
        set_transient( 'swcs_notice_' . get_current_user_id(), array( 'type' => 'success', 'message' => 'Configurações salvas com sucesso.' ), 60 );
        wp_safe_redirect( admin_url( 'admin.php?page=swcs' ) ); exit;
    }

    public static function test_download() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sem permissão.' );
        check_admin_referer( 'swcs_test_download' );
        $url = SWCS_Catalog::build_download_url( 'producttypes' );
        if ( is_wp_error( $url ) ) {
            $message = 'Falha no teste: ' . $url->get_error_message();
            $type = 'error';
        } else {
            $response = wp_remote_get( $url, array( 'timeout' => 30, 'redirection' => 3, 'sslverify' => true ) );
            if ( is_wp_error( $response ) ) {
                $message = 'Falha no download: ' . $response->get_error_message();
                $type = 'error';
            } else {
                $code = wp_remote_retrieve_response_code( $response );
                $body = wp_remote_retrieve_body( $response );
                $message = ( $code >= 200 && $code < 300 && $body !== '' ) ? 'Download do ProductTypes realizado com sucesso. HTTP ' . $code . '.' : 'A Stricker respondeu com HTTP ' . $code . '. O conteúdo recebido não foi considerado válido.';
                $type = ( $code >= 200 && $code < 300 && $body !== '' ) ? 'success' : 'error';
            }
        }
        set_transient( 'swcs_notice_' . get_current_user_id(), array( 'type' => $type, 'message' => $message ), 60 );
        wp_safe_redirect( admin_url( 'admin.php?page=swcs' ) ); exit;
    }
}
