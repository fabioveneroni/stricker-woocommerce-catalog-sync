<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SWCS_Catalog {
    const OPTION_ACCESS_KEY = 'swcs_access_key';
    const OPTION_LANGUAGE   = 'swcs_language';

    public static function save_settings( $access_key, $language ) {
        if ( $access_key !== '' ) {
            update_option( self::OPTION_ACCESS_KEY, $access_key, false );
        }
        update_option( self::OPTION_LANGUAGE, $language ?: 'PT', false );
    }

    public static function get_access_key() {
        return (string) get_option( self::OPTION_ACCESS_KEY, '' );
    }

    public static function get_language() {
        return (string) get_option( self::OPTION_LANGUAGE, 'PT' );
    }

    public static function build_download_url( $data ) {
        $allowed = array( 'products', 'productsTree', 'producttypes', 'optionals', 'optionalsPrice', 'optionalscomplete', 'customizationOptions', 'customizationTables', 'colors', 'stocks', 'orders', 'canceledproducts' );
        if ( ! in_array( $data, $allowed, true ) ) {
            return new WP_Error( 'invalid_catalog_type', 'Tipo de catálogo não permitido.' );
        }
        $key = self::get_access_key();
        if ( $key === '' ) {
            return new WP_Error( 'missing_access_key', 'Access Key não configurada.' );
        }
        return add_query_arg(
            array(
                'AccessKey' => $key,
                'data'      => $data,
                'lang'      => self::get_language(),
                'extension' => 'xml',
            ),
            'https://ws.spotgifts.com.br/downloads/v1SSL/file'
        );
    }
}
