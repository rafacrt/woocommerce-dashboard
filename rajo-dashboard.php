<?php
/**
 * Plugin Name: RAJO Dashboard
 * Plugin URI:  https://github.com/rafacrt/woocommerce-dashboard
 * Description: Substitui os widgets padrão do Painel do WordPress por um painel visual. Escolha o modo em Configurações → RAJO Dashboard: WooCommerce (vendas, pedidos, estoque), WordPress (conteúdo, rascunhos, mídia, saúde do site) ou Mesclado (os dois juntos).
 * Version:     2.1.0
 * Author:      Rafael Medeiros
 * License:     GPL-2.0+
 * Text Domain: rajo-dashboard
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WCD_VERSION',  '2.1.0' );
define( 'WCD_DIR',      plugin_dir_path( __FILE__ ) );
define( 'WCD_URL',      plugin_dir_url( __FILE__ ) );
define( 'WCD_FILE',     __FILE__ );

require_once WCD_DIR . 'includes/class-settings.php';
require_once WCD_DIR . 'includes/class-woo-data.php';
require_once WCD_DIR . 'includes/class-wp-data.php';
require_once WCD_DIR . 'includes/class-mixed.php';
require_once WCD_DIR . 'includes/class-dashboard.php';

add_action( 'plugins_loaded', [ 'WCD_Settings', 'init' ] );
add_action( 'plugins_loaded', [ 'WCD_Dashboard', 'init' ] );

// Declara compatibilidade com HPOS (tabelas próprias de pedidos do WooCommerce)
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );
