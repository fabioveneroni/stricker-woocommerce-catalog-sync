<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SWCS_Admin {
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_post_swcs_save_settings', array( __CLASS__, 'save_settings' ) );
        add_action( 'admin_post_swcs_test_download', array( __CLASS__, 'test_download' ) );
        add_action( 'admin_post_swcs_download_catalog', array( __CLASS__, 'download_catalog' ) );
    }
    public static function menu() { add_menu_page( 'Stricker Catalog Sync', 'Stricker Catalog', 'manage_options', 'swcs', array( __CLASS__, 'dashboard' ), 'dashicons-download', 56 ); }
    private static function notice() {
        $notice = get_transient( 'swcs_notice_' . get_current_user_id() );
        if ( $notice ) { delete_transient( 'swcs_notice_' . get_current_user_id() ); echo '<div class="notice notice-' . esc_attr( $notice['type'] ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>'; }
    }
    public static function dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $key = SWCS_Catalog::get_access_key(); $language = SWCS_Catalog::get_language(); $status = SWCS_Catalog::get_download_status();
        echo '<div class="wrap"><h1>Stricker Catalog Sync</h1>'; self::notice();
        echo '<p>Versão ' . esc_html( SWCS_VERSION ) . '. Downloads grandes são executados em background para evitar timeout do navegador/Nginx.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( 'swcs_save_settings' ); echo '<input type="hidden" name="action" value="swcs_save_settings">';
        echo '<table class="form-table"><tr><th><label for="swcs_access_key">Access Key</label></th><td><input type="password" class="regular-text" id="swcs_access_key" name="access_key" value="' . esc_attr( $key ) . '" autocomplete="off"></td></tr><tr><th><label for="swcs_language">Idioma</label></th><td><select id="swcs_language" name="language"><option value="PT" ' . selected( $language, 'PT', false ) . '>Português (PT)</option><option value="EN" ' . selected( $language, 'EN', false ) . '>English (EN)</option></select></td></tr></table>'; submit_button( 'Salvar configurações' ); echo '</form><hr><h2>Catálogo</h2>';
        self::catalog_button( 'producttypes', 'Baixar ProductTypes' ); echo ' '; self::catalog_button( 'products', 'Baixar Products' );
        if ( isset( $status['status'] ) && 'idle' !== $status['status'] ) { echo '<div style="margin-top:20px;padding:12px;background:#fff;border-left:4px solid ' . ( 'error' === $status['status'] ? '#d63638' : '#00a32a' ) . '"><strong>Status:</strong> ' . esc_html( $status['status'] ) . ' &nbsp; <strong>Tipo:</strong> ' . esc_html( $status['type'] ?? '' ); if ( isset( $status['message'] ) ) echo '<br><strong>Mensagem:</strong> ' . esc_html( $status['message'] ); if ( isset( $status['bytes'] ) ) echo '<br><strong>Tamanho:</strong> ' . esc_html( size_format( $status['bytes'] ) ); if ( isset( $status['finished_at'] ) ) echo '<br><strong>Concluído:</strong> ' . esc_html( $status['finished_at'] ); echo '</div>'; }
        echo '</div>';
    }
    private static function catalog_button( $type, $label ) { echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block">'; wp_nonce_field( 'swcs_download_catalog_' . $type ); echo '<input type="hidden" name="action" value="swcs_download_catalog"><input type="hidden" name="catalog_type" value="' . esc_attr( $type ) . '">'; submit_button( $label, 'secondary', 'submit', false ); echo '</form>'; }
    public static function save_settings() { if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sem permissão.' ); check_admin_referer( 'swcs_save_settings' ); $key = isset( $_POST['access_key'] ) ? sanitize_text_field( wp_unslash( $_POST['access_key'] ) ) : ''; $language = isset( $_POST['language'] ) ? sanitize_key( wp_unslash( $_POST['language'] ) ) : 'PT'; if ( ! in_array( $language, array( 'PT', 'EN' ), true ) ) $language = 'PT'; SWCS_Catalog::save_settings( $key, $language ); self::set_notice( 'success', 'Configurações salvas com sucesso.' ); wp_safe_redirect( admin_url( 'admin.php?page=swcs' ) ); exit; }
    public static function test_download() { self::download_catalog(); }
    public static function download_catalog() { if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sem permissão.' ); $type = isset( $_POST['catalog_type'] ) ? sanitize_key( wp_unslash( $_POST['catalog_type'] ) ) : 'producttypes'; check_admin_referer( 'swcs_download_catalog_' . $type ); if ( ! in_array( $type, array( 'producttypes', 'products' ), true ) ) self::set_notice( 'error', 'Tipo de catálogo inválido.' ); elseif ( '' === SWCS_Catalog::get_access_key() ) self::set_notice( 'error', 'Access Key não configurada.' ); else { SWCS_Catalog::request_download( $type ); self::set_notice( 'success', 'Download de ' . $type . ' colocado em segundo plano. A página pode ser atualizada para acompanhar o status.' ); } wp_safe_redirect( admin_url( 'admin.php?page=swcs' ) ); exit; }
    private static function set_notice( $type, $message ) { set_transient( 'swcs_notice_' . get_current_user_id(), array( 'type' => $type, 'message' => $message ), 60 ); }
}
