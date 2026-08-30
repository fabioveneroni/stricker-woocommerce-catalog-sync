<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SWCS_Catalog {
    const OPTION_ACCESS_KEY = 'swcs_access_key';
    const OPTION_LANGUAGE   = 'swcs_language';
    const OPTION_LAST_SYNC  = 'swcs_last_sync';
    const OPTION_DOWNLOAD_STATUS = 'swcs_download_status';

    public static function save_settings( $access_key, $language ) {
        if ( $access_key !== '' ) update_option( self::OPTION_ACCESS_KEY, $access_key, false );
        update_option( self::OPTION_LANGUAGE, $language ?: 'PT', false );
    }
    public static function get_access_key() { return (string) get_option( self::OPTION_ACCESS_KEY, '' ); }
    public static function get_language() { return (string) get_option( self::OPTION_LANGUAGE, 'PT' ); }

    public static function build_download_url( $data ) {
        $allowed = array( 'products', 'productsTree', 'producttypes', 'optionals', 'optionalsPrice', 'optionalscomplete', 'customizationOptions', 'customizationTables', 'colors', 'stocks', 'orders', 'canceledproducts' );
        if ( ! in_array( $data, $allowed, true ) ) return new WP_Error( 'invalid_catalog_type', 'Tipo de catálogo não permitido.' );
        $key = self::get_access_key();
        if ( $key === '' ) return new WP_Error( 'missing_access_key', 'Access Key não configurada.' );
        return add_query_arg( array( 'AccessKey' => $key, 'data' => $data, 'lang' => self::get_language(), 'extension' => 'xml' ), 'https://ws.spotgifts.com.br/downloads/v1SSL/file' );
    }

    public static function get_storage_dir() {
        $uploads = wp_upload_dir();
        $dir = trailingslashit( $uploads['basedir'] ) . 'swcs-catalog';
        if ( ! file_exists( $dir ) ) wp_mkdir_p( $dir );
        if ( is_dir( $dir ) ) {
            if ( ! file_exists( trailingslashit( $dir ) . 'index.php' ) ) file_put_contents( trailingslashit( $dir ) . 'index.php', "<?php\n// Silence is golden.\n" );
            if ( ! file_exists( trailingslashit( $dir ) . '.htaccess' ) ) file_put_contents( trailingslashit( $dir ) . '.htaccess', "Deny from all\n" );
        }
        return $dir;
    }

    public static function request_download( $data ) {
        update_option( self::OPTION_DOWNLOAD_STATUS, array( 'status' => 'queued', 'type' => $data, 'started_at' => current_time( 'mysql' ) ), false );
        if ( ! wp_next_scheduled( 'swcs_background_download', array( $data ) ) ) wp_schedule_single_event( time() + 1, 'swcs_background_download', array( $data ) );
    }

    public static function background_download( $data ) {
        update_option( self::OPTION_DOWNLOAD_STATUS, array( 'status' => 'running', 'type' => $data, 'started_at' => current_time( 'mysql' ) ), false );
        $result = self::download_catalog( $data );
        if ( is_wp_error( $result ) ) {
            update_option( self::OPTION_DOWNLOAD_STATUS, array( 'status' => 'error', 'type' => $data, 'message' => $result->get_error_message(), 'finished_at' => current_time( 'mysql' ) ), false );
            return;
        }
        update_option( self::OPTION_DOWNLOAD_STATUS, array( 'status' => 'completed', 'type' => $data, 'bytes' => $result['bytes'], 'http_code' => $result['http_code'], 'path' => $result['path'], 'finished_at' => current_time( 'mysql' ) ), false );
    }

    public static function get_download_status() { return get_option( self::OPTION_DOWNLOAD_STATUS, array( 'status' => 'idle' ) ); }

    public static function download_catalog( $data ) {
        $url = self::build_download_url( $data );
        if ( is_wp_error( $url ) ) return $url;
        $response = wp_remote_get( $url, array( 'timeout' => 120, 'redirection' => 3, 'sslverify' => true, 'headers' => array( 'Accept' => 'application/xml, text/xml, */*' ) ) );
        if ( is_wp_error( $response ) ) return new WP_Error( 'download_failed', 'Falha ao baixar ' . $data . ': ' . $response->get_error_message() );
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        if ( $code < 200 || $code >= 300 ) return new WP_Error( 'http_error', 'A Stricker respondeu com HTTP ' . $code . ' ao baixar ' . $data . '.' );
        if ( trim( $body ) === '' ) return new WP_Error( 'empty_response', 'A Stricker retornou uma resposta vazia para ' . $data . '.' );
        libxml_use_internal_errors( true );
        if ( simplexml_load_string( $body ) === false ) return new WP_Error( 'invalid_xml', 'O arquivo ' . $data . ' foi recebido, mas não contém XML válido.' );
        $dir = self::get_storage_dir();
        if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) return new WP_Error( 'storage_error', 'Não foi possível criar ou gravar no diretório local do catálogo.' );
        $path = trailingslashit( $dir ) . sanitize_file_name( $data ) . '.xml';
        if ( file_put_contents( $path, $body, LOCK_EX ) === false ) return new WP_Error( 'write_error', 'Não foi possível salvar o arquivo ' . $data . '.xml.' );
        update_option( self::OPTION_LAST_SYNC, current_time( 'mysql' ), false );
        return array( 'type' => $data, 'path' => $path, 'bytes' => strlen( $body ), 'http_code' => $code );
    }
}
