<?php
/**
 * Painel do modo WooCommerce.
 */
defined( 'ABSPATH' ) || exit;

$kpis   = WCD_Woo_Data::get_kpis();
$orders = WCD_Woo_Data::get_recent_orders( 10 );
$top    = WCD_Woo_Data::get_top_products( 5 );
$stock  = WCD_Woo_Data::get_low_stock( 5, 10 );
$symbol = get_woocommerce_currency_symbol();

$fmt = function( $val ) use ( $symbol ) {
    return $symbol . number_format( $val, 2, ',', '.' );
};
?>

    <!-- ── KPI Cards ── -->
    <div class="wcd-kpis">
        <?php
        $cards = [
            [ 'label' => 'Receita Hoje',     'value' => $fmt( $kpis['revenue_today'] ),   'icon' => '💰', 'color' => 'green' ],
            [ 'label' => 'Receita do Mês',   'value' => $fmt( $kpis['revenue_month'] ),   'icon' => '📈', 'color' => 'blue' ],
            [ 'label' => 'Pedidos Hoje',     'value' => $kpis['orders_today'],             'icon' => '🛒', 'color' => 'purple' ],
            [ 'label' => 'Aguardando',       'value' => $kpis['orders_pending'],           'icon' => '⏳', 'color' => 'orange' ],
            [ 'label' => 'Em Processamento', 'value' => $kpis['orders_processing'],        'icon' => '⚙️', 'color' => 'indigo' ],
            [ 'label' => 'Ticket Médio/Mês', 'value' => $fmt( $kpis['avg_ticket'] ),       'icon' => '🎫', 'color' => 'teal' ],
            [ 'label' => 'Clientes',         'value' => number_format( $kpis['total_customers'], 0, ',', '.' ), 'icon' => '👥', 'color' => 'pink' ],
        ];
        foreach ( $cards as $c ) : ?>
            <div class="wcd-kpi wcd-kpi--<?php echo esc_attr( $c['color'] ); ?>">
                <div class="wcd-kpi__icon"><?php echo $c['icon']; ?></div>
                <div class="wcd-kpi__info">
                    <span class="wcd-kpi__value"><?php echo esc_html( $c['value'] ); ?></span>
                    <span class="wcd-kpi__label"><?php echo esc_html( $c['label'] ); ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Charts ── -->
    <div class="wcd-charts">
        <div class="wcd-card wcd-card--chart">
            <div class="wcd-card__head"><h3>Pedidos — últimos 30 dias</h3></div>
            <canvas id="wcd-chart-orders" height="100"></canvas>
        </div>
        <div class="wcd-card wcd-card--chart">
            <div class="wcd-card__head"><h3>Receita — últimos 30 dias</h3></div>
            <canvas id="wcd-chart-revenue" height="100"></canvas>
        </div>
        <div class="wcd-card wcd-card--donut">
            <div class="wcd-card__head"><h3>Pedidos por Status</h3></div>
            <div class="wcd-donut-wrap">
                <canvas id="wcd-chart-donut"></canvas>
            </div>
        </div>
    </div>

    <!-- ── Bottom row ── -->
    <div class="wcd-bottom">

        <!-- Pedidos recentes -->
        <div class="wcd-card wcd-card--orders">
            <div class="wcd-card__head">
                <h3>Pedidos Recentes</h3>
                <a href="<?php echo esc_url( WCD_Woo_Data::orders_list_url() ); ?>">Ver todos →</a>
            </div>
            <div class="wcd-table-wrap">
                <table class="wcd-table">
                    <thead>
                        <tr>
                            <th>#</th><th>Cliente</th><th>Status</th>
                            <th>Total</th><th>Data</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $orders ) ) : ?>
                            <tr><td colspan="6" class="wcd-empty" style="text-align:center;padding:20px">Nenhum pedido encontrado.</td></tr>
                        <?php else : ?>
                            <?php foreach ( $orders as $o ) : ?>
                                <tr>
                                    <td><strong>#<?php echo esc_html( $o['id'] ); ?></strong></td>
                                    <td><?php echo esc_html( $o['customer'] ); ?></td>
                                    <td>
                                        <span class="wcd-badge wcd-badge--<?php echo esc_attr( $o['status'] ); ?>">
                                            <?php echo esc_html( wc_get_order_status_name( $o['status'] ) ); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html( $symbol . number_format( $o['total'], 2, ',', '.' ) ); ?></td>
                                    <td><?php echo esc_html( $o['date'] ); ?></td>
                                    <td><a href="<?php echo esc_url( $o['edit_url'] ); ?>" class="wcd-btn-icon" title="Editar">✏️</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sidebar: top produtos + estoque baixo -->
        <div class="wcd-sidebar">

            <div class="wcd-card">
                <div class="wcd-card__head"><h3>🏆 Mais Vendidos</h3></div>
                <?php if ( empty( $top ) ) : ?>
                    <p class="wcd-empty">Sem dados ainda.</p>
                <?php else : ?>
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
                <?php endif; ?>
            </div>

            <div class="wcd-card">
                <div class="wcd-card__head"><h3>⚠️ Estoque Baixo</h3></div>
                <?php if ( empty( $stock ) ) : ?>
                    <p class="wcd-empty">Nenhum produto com estoque crítico.</p>
                <?php else : ?>
                    <ul class="wcd-stock-list">
                        <?php foreach ( $stock as $s ) : ?>
                            <li>
                                <a href="<?php echo esc_url( $s['edit_url'] ); ?>"><?php echo esc_html( $s['name'] ); ?></a>
                                <span class="wcd-stock-badge wcd-stock-badge--<?php echo $s['stock'] === 0 ? 'out' : 'low'; ?>">
                                    <?php echo $s['stock'] === 0 ? 'Sem estoque' : $s['stock'] . ' restantes'; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

        </div>
    </div>
