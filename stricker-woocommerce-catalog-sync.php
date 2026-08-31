<?php
/**
 * Plugin Name: Stricker WooCommerce Catalog Sync
 * Description: Baixa e processa o catálogo XML da Stricker para posterior sincronização com WooCommerce.
 * Version: 0.3.2
 * Author: Fabio Veneroni
 * Text Domain: stricker-woocommerce-catalog-sync
 */
if ( ! defined( 'ABSPATH' ) ) exit;
define( 'SWCS_VERSION', '0.3.2' );
define( 'SWCS_FILE', __FILE__ );
define( 'SWCS_DIR', plugin_dir_path( __FILE__ ) );
define( 'SWCS_URL', plugin_dir_url( __FILE__ ) );
require_once SWCS_DIR . 'includes/class-swcs-admin.php';
require_once SWCS_DIR . 'includes/class-swcs-catalog.php';
add_action( 'swcs_background_download', array( 'SWCS_Catalog', 'background_download' ), 10, 1 );
SWCS_Admin::init();
