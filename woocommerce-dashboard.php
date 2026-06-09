<?php
/**
 * Plugin Name: WooCommerce Dashboard
 * Plugin URI:  https://github.com/rafaelmedeiros/woocommerce-dashboard
 * Description: Substitui todos os widgets padrão do Dashboard do WordPress por um painel completo com métricas, gráficos e dados do WooCommerce.
 * Version:     1.0.0
 * Author:      Rafael Medeiros
 * License:     GPL-2.0+
 * Text Domain: wc-dashboard
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WCD_VERSION',  '1.0.0' );
define( 'WCD_DIR',      plugin_dir_path( __FILE__ ) );
define( 'WCD_URL',      plugin_dir_url( __FILE__ ) );

require_once WCD_DIR . 'includes/class-data.php';
require_once WCD_DIR . 'includes/class-dashboard.php';

add_action( 'plugins_loaded', [ 'WCD_Dashboard', 'init' ] );
