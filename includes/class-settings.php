<?php
defined( 'ABSPATH' ) || exit;

/**
 * Configurações → RAJO Dashboard: o usuário escolhe qual painel é exibido.
 */
class WCD_Settings {

    const OPTION = 'wcd_mode';

    const MODES = [ 'woocommerce' => 'WooCommerce', 'wordpress' => 'WordPress', 'mesclado' => 'Mesclado' ];

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_page' ] );
        add_action( 'admin_init', [ __CLASS__, 'register' ] );
        add_filter( 'plugin_action_links_' . plugin_basename( WCD_FILE ), [ __CLASS__, 'action_links' ] );
    }

    public static function woo_active() {
        return class_exists( 'WooCommerce' );
    }

    /**
     * Modo escolhido: 'woocommerce', 'wordpress' ou 'mesclado' (WordPress + WooCommerce no mesmo painel).
     * Sem escolha salva, usa WooCommerce quando ele está ativo.
     * Escolheu WooCommerce mas ele foi desativado: cai para WordPress.
     */
    public static function mode() {
        $mode = get_option( self::OPTION, '' );
        if ( ! isset( self::MODES[ $mode ] ) ) {
            $mode = self::woo_active() ? 'woocommerce' : 'wordpress';
        }
        if ( 'woocommerce' === $mode && ! self::woo_active() ) {
            $mode = 'wordpress';
        }
        return $mode;
    }

    public static function add_page() {
        add_options_page( 'RAJO Dashboard', 'RAJO Dashboard', 'manage_options', 'rajo-dashboard', [ __CLASS__, 'render' ] );
    }

    public static function register() {
        register_setting( 'wcd_settings', self::OPTION, [
            'type'              => 'string',
            'sanitize_callback' => function ( $value ) {
                return isset( self::MODES[ $value ] ) ? $value : 'wordpress';
            },
        ] );
    }

    public static function action_links( $links ) {
        array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=rajo-dashboard' ) ) . '">Configurações</a>' );
        return $links;
    }

    public static function render() {
        $saved = get_option( self::OPTION, '' );
        $mode  = self::mode();
        $woo   = self::woo_active();
        ?>
        <div class="wrap">
            <h1>RAJO Dashboard</h1>
            <p>Escolha qual painel aparece em <a href="<?php echo esc_url( admin_url() ); ?>">Painel → Início</a>.</p>

            <?php if ( 'woocommerce' === $saved && ! $woo ) : ?>
                <div class="notice notice-warning inline"><p>O modo WooCommerce está escolhido, mas o WooCommerce não está ativo. O painel WordPress está sendo exibido no lugar.</p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields( 'wcd_settings' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Modo do painel</th>
                        <td>
                            <fieldset>
                                <label style="display:block;margin-bottom:12px">
                                    <input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>" value="woocommerce" <?php checked( $mode, 'woocommerce' ); ?> <?php disabled( ! $woo ); ?>>
                                    <strong>WooCommerce</strong> — receita, pedidos, ticket médio, mais vendidos e estoque baixo.
                                    <?php if ( ! $woo ) : ?><em>(requer o WooCommerce ativo)</em><?php endif; ?>
                                </label>
                                <label style="display:block;margin-bottom:12px">
                                    <input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>" value="wordpress" <?php checked( $mode, 'wordpress' ); ?>>
                                    <strong>WordPress</strong> — conteúdo por tipo, rascunhos, alterações recentes, mídia, usuários e saúde do site.
                                </label>
                                <label style="display:block">
                                    <input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>" value="mesclado" <?php checked( $mode, 'mesclado' ); ?>>
                                    <strong>Mesclado</strong> — WordPress e WooCommerce no mesmo painel: vendas e pedidos junto com conteúdo e saúde do site. Cada site pode trocar cards, gráficos e painéis pelo filtro <code>rajo_dashboard_mesclado</code>.
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
