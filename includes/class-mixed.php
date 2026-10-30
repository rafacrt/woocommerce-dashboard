<?php
defined( 'ABSPATH' ) || exit;

/**
 * Modo Mesclado: WordPress e WooCommerce no mesmo painel.
 *
 * O painel é montado a partir de um layout com quatro partes, cada uma indexada por chave
 * para que o site possa trocar, remover ou acrescentar itens pelo filtro rajo_dashboard_mesclado:
 *
 *   cards     => [ chave => [ label, value, icon, color, url ] ]
 *   graficos  => [ chave => [ titulo, tipo (bar|line|doughnut), labels, series => [ [ nome, dados, cor ] ],
 *                             formato (numero|moeda), empilhado (bool), unidade (texto do tooltip) ] ]
 *   principal => [ chave => painel ]  coluna larga
 *   lateral   => [ chave => painel ]  coluna estreita
 *
 * Painel: [ titulo, link => [ url, texto ] (opcional), render => callable ].
 *
 * add_filter( 'rajo_dashboard_mesclado', function ( $layout ) {
 *     $layout['cards']['paginas'] = [ 'label' => 'Unidades', 'value' => 8, 'icon' => '📍', 'color' => 'blue', 'url' => '' ];
 *     unset( $layout['lateral']['rascunhos'] );
 *     return $layout;
 * } );
 */
class WCD_Mixed {

    private static $layout = null;

    public static function layout() {
        if ( null !== self::$layout ) {
            return self::$layout;
        }
        $woo = WCD_Settings::woo_active();
        $wp  = WCD_WP_Data::get_kpis();
        $num = fn( $n ) => number_format( (int) $n, 0, ',', '.' );

        $cards = [];
        if ( $woo ) {
            $k     = WCD_Woo_Data::get_kpis();
            $cards = [
                'receita_mes'      => [ 'label' => 'Receita do mês',   'value' => self::money( $k['revenue_month'] ), 'icon' => '📈', 'color' => 'green',  'url' => WCD_Woo_Data::orders_list_url() ],
                'pedidos_hoje'     => [ 'label' => 'Pedidos hoje',     'value' => $num( $k['orders_today'] ),          'icon' => '🛒', 'color' => 'purple', 'url' => WCD_Woo_Data::orders_list_url() ],
                'em_processamento' => [ 'label' => 'Em processamento', 'value' => $num( $k['orders_processing'] ),     'icon' => '⚙️', 'color' => 'indigo', 'url' => WCD_Woo_Data::orders_list_url() ],
            ];
        }
        $cards += [
            'paginas'      => [ 'label' => 'Páginas publicadas',    'value' => $num( $wp['pages'] ),            'icon' => '📄', 'color' => 'blue',   'url' => admin_url( 'edit.php?post_type=page' ) ],
            'rascunhos'    => [ 'label' => 'Rascunhos',             'value' => $num( $wp['drafts'] ),           'icon' => '✏️', 'color' => 'orange', 'url' => '' ],
            'comentarios'  => [ 'label' => 'Comentários pendentes', 'value' => $num( $wp['comments_pending'] ), 'icon' => '💬', 'color' => 'teal',   'url' => admin_url( 'edit-comments.php?comment_status=moderated' ) ],
            'atualizacoes' => [ 'label' => 'Atualizações',          'value' => $num( $wp['updates'] ),          'icon' => '🔄', 'color' => $wp['updates'] ? 'red' : 'pink', 'url' => admin_url( 'update-core.php' ) ],
        ];

        if ( $woo ) {
            $chart    = WCD_Woo_Data::get_chart_data( 30 );
            $status   = WCD_Woo_Data::get_orders_by_status();
            $graficos = [
                'pedidos' => [ 'titulo' => 'Pedidos — últimos 30 dias', 'tipo' => 'bar',  'labels' => $chart['labels'], 'series' => [ [ 'nome' => 'Pedidos', 'dados' => $chart['orders'], 'cor' => 'purple' ] ], 'unidade' => 'pedido(s)' ],
                'receita' => [ 'titulo' => 'Receita — últimos 30 dias', 'tipo' => 'line', 'labels' => $chart['labels'], 'series' => [ [ 'nome' => 'Receita', 'dados' => $chart['revenue'], 'cor' => 'blue' ] ], 'formato' => 'moeda' ],
                'status'  => [ 'titulo' => 'Pedidos por status',        'tipo' => 'doughnut', 'labels' => wp_list_pluck( $status, 'label' ), 'series' => [ [ 'nome' => 'Pedidos', 'dados' => wp_list_pluck( $status, 'count' ) ] ], 'unidade' => 'pedido(s)' ],
            ];
        } else {
            $chart    = WCD_WP_Data::get_chart_data( 30 );
            $media    = WCD_WP_Data::get_media_by_type();
            $graficos = [
                'publicacoes' => [ 'titulo' => 'Publicações — últimos 30 dias', 'tipo' => 'bar', 'labels' => $chart['labels'], 'series' => [ [ 'nome' => 'Publicações', 'dados' => $chart['published'], 'cor' => 'purple' ] ], 'unidade' => 'publicação(ões)' ],
                'edicoes'     => [ 'titulo' => 'Edições — últimos 30 dias',     'tipo' => 'bar', 'labels' => $chart['labels'], 'series' => [ [ 'nome' => 'Edições', 'dados' => $chart['edited'], 'cor' => 'blue' ] ], 'unidade' => 'item(ns) editado(s)' ],
                'midia'       => [ 'titulo' => 'Mídia por tipo', 'tipo' => 'doughnut', 'labels' => wp_list_pluck( $media, 'label' ), 'series' => [ [ 'nome' => 'Arquivos', 'dados' => wp_list_pluck( $media, 'count' ) ] ], 'unidade' => 'arquivo(s)' ],
            ];
        }

        $principal = [];
        $lateral   = [];
        if ( $woo ) {
            $principal['pedidos_recentes'] = [ 'titulo' => 'Pedidos recentes', 'link' => [ WCD_Woo_Data::orders_list_url(), 'Ver todos →' ], 'render' => [ __CLASS__, 'render_recent_orders' ] ];
            $lateral['mais_vendidos']      = [ 'titulo' => '🏆 Mais vendidos', 'render' => [ __CLASS__, 'render_top_products' ] ];
        }
        $principal['alteracoes'] = [ 'titulo' => 'Alterações recentes', 'render' => [ __CLASS__, 'render_recent_changes' ] ];
        $lateral['rascunhos']    = [ 'titulo' => '✏️ Rascunhos', 'render' => [ __CLASS__, 'render_drafts' ] ];
        $lateral['saude']        = [ 'titulo' => '🩺 Saúde do site', 'link' => current_user_can( 'view_site_health_checks' ) ? [ admin_url( 'site-health.php' ), 'Detalhes →' ] : null, 'render' => [ __CLASS__, 'render_site_health' ] ];

        self::$layout = apply_filters( 'rajo_dashboard_mesclado', compact( 'cards', 'graficos', 'principal', 'lateral' ) );
        foreach ( self::$layout['graficos'] as $chave => &$grafico ) {
            $grafico['id'] = 'wcd-m-' . sanitize_key( $chave );
        }
        unset( $grafico );
        return self::$layout;
    }

    public static function money( $value ) {
        $symbol = WCD_Settings::woo_active() ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : 'R$';
        return $symbol . ' ' . number_format( (float) $value, 2, ',', '.' );
    }

    // ─── Painéis padrão ──────────────────────────────────────────────────────

    public static function render_panel( array $panel ) {
        if ( empty( $panel['render'] ) || ! is_callable( $panel['render'] ) ) return;
        ?>
        <div class="wcd-card">
            <div class="wcd-card__head">
                <h3><?php echo esc_html( $panel['titulo'] ?? '' ); ?></h3>
                <?php if ( ! empty( $panel['link'][0] ) ) : ?>
                    <a href="<?php echo esc_url( $panel['link'][0] ); ?>"><?php echo esc_html( $panel['link'][1] ?? 'Ver →' ); ?></a>
                <?php endif; ?>
            </div>
            <?php call_user_func( $panel['render'] ); ?>
        </div>
        <?php
    }

    public static function render_recent_orders() {
        $orders = WCD_Woo_Data::get_recent_orders( 10 );
        ?>
        <div class="wcd-table-wrap">
            <table class="wcd-table">
                <thead><tr><th>#</th><th>Cliente</th><th>Status</th><th>Total</th><th>Data</th><th></th></tr></thead>
                <tbody>
                    <?php if ( empty( $orders ) ) : ?>
                        <tr><td colspan="6" class="wcd-empty" style="text-align:center;padding:20px">Nenhum pedido encontrado.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $orders as $o ) : ?>
                            <tr>
                                <td><strong>#<?php echo esc_html( $o['id'] ); ?></strong></td>
                                <td><?php echo esc_html( $o['customer'] ); ?></td>
                                <td><span class="wcd-badge wcd-badge--<?php echo esc_attr( $o['status'] ); ?>"><?php echo esc_html( wc_get_order_status_name( $o['status'] ) ); ?></span></td>
                                <td><?php echo esc_html( self::money( $o['total'] ) ); ?></td>
                                <td><?php echo esc_html( $o['date'] ); ?></td>
                                <td><a href="<?php echo esc_url( $o['edit_url'] ); ?>" class="wcd-btn-icon" title="Editar">✏️</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function render_recent_changes() {
        $recent   = WCD_WP_Data::get_recent_changes( 8 );
        $statuses = [ 'publish' => 'Publicado', 'draft' => 'Rascunho', 'pending' => 'Pendente', 'private' => 'Privado', 'future' => 'Agendado' ];
        ?>
        <div class="wcd-table-wrap">
            <table class="wcd-table">
                <thead><tr><th>Título</th><th>Tipo</th><th>Status</th><th>Por</th><th>Quando</th><th></th></tr></thead>
                <tbody>
                    <?php if ( empty( $recent ) ) : ?>
                        <tr><td colspan="6" class="wcd-empty" style="text-align:center;padding:20px">Nenhum conteúdo ainda.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $recent as $r ) : ?>
                            <tr>
                                <td><?php echo esc_html( $r['title'] ); ?></td>
                                <td><?php echo esc_html( $r['type'] ); ?></td>
                                <td><span class="wcd-badge wcd-badge--<?php echo esc_attr( $r['status'] ); ?>"><?php echo esc_html( $statuses[ $r['status'] ] ?? $r['status'] ); ?></span></td>
                                <td><?php echo esc_html( $r['author'] ); ?></td>
                                <td><?php echo esc_html( $r['date'] ); ?></td>
                                <td><?php if ( $r['edit_url'] ) : ?><a href="<?php echo esc_url( $r['edit_url'] ); ?>" class="wcd-btn-icon" title="Editar">✏️</a><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function render_top_products() {
        $top = WCD_Woo_Data::get_top_products( 5 );
        if ( empty( $top ) ) {
            echo '<p class="wcd-empty">Sem dados ainda.</p>';
            return;
        }
        ?>
        <ul class="wcd-product-list">
            <?php foreach ( $top as $i => $p ) : ?>
                <li>
                    <span class="wcd-rank"><?php echo $i + 1; ?></span>
                    <img src="<?php echo esc_url( $p['thumb'] ); ?>" alt="" width="36" height="36">
                    <a href="<?php echo esc_url( $p['edit_url'] ); ?>"><?php echo esc_html( $p['name'] ); ?></a>
                    <span class="wcd-qty"><?php echo esc_html( $p['qty'] ); ?> un.</span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    public static function render_drafts() {
        $drafts = WCD_WP_Data::get_drafts( 8 );
        if ( empty( $drafts ) ) {
            echo '<p class="wcd-empty">Nenhum rascunho.</p>';
            return;
        }
        ?>
        <ul class="wcd-stock-list">
            <?php foreach ( $drafts as $d ) : ?>
                <li>
                    <a href="<?php echo esc_url( $d['edit_url'] ); ?>"><?php echo esc_html( $d['title'] ); ?></a>
                    <span class="wcd-stock-badge wcd-stock-badge--low"><?php echo esc_html( $d['type'] ); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    public static function render_site_health() {
        ?>
        <ul class="wcd-stock-list">
            <?php foreach ( WCD_WP_Data::get_site_info() as $s ) : ?>
                <li><span><?php echo esc_html( $s['label'] ); ?></span><span class="wcd-qty"><?php echo esc_html( $s['value'] ); ?></span></li>
            <?php endforeach; ?>
        </ul>
        <?php
    }
}
