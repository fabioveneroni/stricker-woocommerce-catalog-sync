<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SWCS_Parser {
    public static function parse_products( $path ) {
        if ( ! is_readable( $path ) ) return new WP_Error( 'missing_products_xml', 'Products.xml não foi encontrado.' );
        $xml = simplexml_load_file( $path, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
        if ( false === $xml ) return new WP_Error( 'invalid_products_xml', 'Products.xml não contém XML válido.' );
        $count = 0;
        $samples = array();
        self::walk( $xml, $count, $samples );
        return array( 'count' => $count, 'samples' => $samples );
    }
    private static function walk( $node, &$count, &$samples ) {
        if ( isset( $node->ProdReference ) && isset( $node->Name ) ) {
            $count++;
            if ( count( $samples ) < 5 ) {
                $samples[] = array(
                    'reference' => (string) $node->ProdReference,
                    'name' => (string) $node->Name,
                    'description' => isset( $node->Description ) ? (string) $node->Description : '',
                );
            }
        }
        foreach ( $node->children() as $child ) self::walk( $child, $count, $samples );
    }
}
