<?php
defined( 'ABSPATH' ) || exit;

class WCD_Dashboard {

    public static function init() {
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

        // Injeta o painel direto na página, sem depender do sistema de meta boxes
        add_action( 'admin_notices', [ __CLASS__, 'maybe_render' ] );

        // Remove todos os widgets nativos e de terceiros
        add_action( 'wp_dashboard_setup', [ __CLASS__, 'remove_all_widgets' ], PHP_INT_MAX );
    }

    public static function remove_all_widgets() {
        global $wp_meta_boxes;
        if ( isset( $wp_meta_boxes['dashboard'] ) ) {
            $wp_meta_boxes['dashboard'] = [];
        }
    }

    // ─── Só renderiza na página do Dashboard ─────────────────────────────────

    public static function maybe_render() {
        $screen = get_current_screen();
        if ( ! $screen || 'dashboard' !== $screen->id ) return;
        self::render_main();
    }

    // ─── Assets ──────────────────────────────────────────────────────────────

    public static function enqueue_assets( $hook ) {
        if ( 'index.php' !== $hook ) return;

        wp_enqueue_style(
            'wcd-style',
            WCD_URL . 'assets/css/dashboard.css',
            [],
            WCD_VERSION
        );

        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js',
            [],
            '4.4.2',
            true
        );

        wp_enqueue_script(
            'wcd-script',
            WCD_URL . 'assets/js/dashboard.js',
            [ 'chart-js', 'jquery' ],
            WCD_VERSION,
            true
        );

        $mode = WCD_Settings::mode();
        if ( 'woocommerce' === $mode ) {
            $data = [
                'chart'    => WCD_Woo_Data::get_chart_data( 30 ),
                'donut'    => WCD_Woo_Data::get_orders_by_status(),
                'currency' => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
            ];
        } else {
            $data = [
                'chart' => WCD_WP_Data::get_chart_data( 30 ),
                'donut' => WCD_WP_Data::get_media_by_type(),
            ];
        }
        $data['mode'] = $mode;

        wp_localize_script( 'wcd-script', 'wcdData', $data );
    }

    // ─── Painéis extras ──────────────────────────────────────────────────────

    /**
     * Painéis próprios de cada site, abaixo do painel principal.
     *
     * add_filter( 'rajo_dashboard_paineis', function ( $paineis, $modo ) {
     *     $paineis[] = [
     *         'titulo' => 'Minhas unidades',
     *         'link'   => [ admin_url( 'edit.php?post_type=unidade' ), 'Ver todas →' ], // opcional
     *         'largura'=> 'inteira', // 'inteira' (padrão) ou 'metade'
     *         'render' => function () { echo '...'; },
     *     ];
     *     return $paineis;
     * }, 10, 2 );
     */
    private static function render_extra_panels( $mode ) {
        $panels = apply_filters( 'rajo_dashboard_paineis', [], $mode );
        if ( empty( $panels ) ) return;
        ?>
        <div class="wcd-extras">
            <?php foreach ( $panels as $panel ) :
                if ( empty( $panel['render'] ) || ! is_callable( $panel['render'] ) ) continue;
                $half = 'metade' === ( $panel['largura'] ?? '' );
                ?>
                <div class="wcd-card<?php echo $half ? ' wcd-card--half' : ''; ?>">
                    <div class="wcd-card__head">
                        <h3><?php echo esc_html( $panel['titulo'] ?? '' ); ?></h3>
                        <?php if ( ! empty( $panel['link'][0] ) ) : ?>
                            <a href="<?php echo esc_url( $panel['link'][0] ); ?>"><?php echo esc_html( $panel['link'][1] ?? 'Ver →' ); ?></a>
                        <?php endif; ?>
                    </div>
                    <?php call_user_func( $panel['render'] ); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    // ─── Render principal ─────────────────────────────────────────────────────

    public static function render_main() {
        $mode = WCD_Settings::mode();
        ?>

        <!-- Esconde tudo que não é nosso painel (widgets WP, notices de outros plugins) -->
        <style>
            #dashboard-widgets-wrap,
            #wpbody-content > .wrap > h1 { display: none !important; }
            /* Remove notices de outros plugins (Elementor Go Pro, etc.) */
            #wpbody-content > .notice:not(.wcd-notice),
            #wpbody-content > .updated:not(.wcd-notice),
            #wpbody-content > .update-nag { display: none !important; }
        </style>

        <div class="wcd-wrap">

            <!-- ── Header ── -->
            <div class="wcd-header">
                <div class="wcd-header__logo">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 3h18v4H3zM3 10h11v4H3zM3 17h7v4H3z" fill="currentColor" opacity=".9"/>
                        <circle cx="18" cy="19" r="4" fill="#7B5EA7"/>
                    </svg>
                    <span>RAJO Dashboard</span>
                    <?php if ( current_user_can( 'manage_options' ) ) : ?>
                        <a class="wcd-header__mode" href="<?php echo esc_url( admin_url( 'options-general.php?page=rajo-dashboard' ) ); ?>" title="Trocar o modo do painel">
                            <?php echo 'woocommerce' === $mode ? 'WooCommerce' : 'WordPress'; ?>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="wcd-header__date"><?php echo esc_html( date_i18n( 'l, d \d\e F \d\e Y' ) ); ?></div>
            </div>

            <?php
            require WCD_DIR . 'includes/views/' . ( 'woocommerce' === $mode ? 'woocommerce.php' : 'wordpress.php' );
            self::render_extra_panels( $mode );
            ?>

        </div>
        <?php
    }
}
