<?php
/**
 * Painel do modo Mesclado (WordPress + WooCommerce). O conteúdo vem de WCD_Mixed::layout().
 */
defined( 'ABSPATH' ) || exit;

$layout = WCD_Mixed::layout();
?>

    <!-- ── KPI Cards ── -->
    <div class="wcd-kpis">
        <?php foreach ( $layout['cards'] as $c ) :
            $tag = ! empty( $c['url'] ) ? 'a' : 'div'; ?>
            <<?php echo $tag; ?> class="wcd-kpi wcd-kpi--<?php echo esc_attr( $c['color'] ?? 'blue' ); ?>"<?php echo 'a' === $tag ? ' href="' . esc_url( $c['url'] ) . '"' : ''; ?>>
                <div class="wcd-kpi__icon"><?php echo esc_html( $c['icon'] ?? '' ); ?></div>
                <div class="wcd-kpi__info">
                    <span class="wcd-kpi__value"><?php echo esc_html( $c['value'] ); ?></span>
                    <span class="wcd-kpi__label"><?php echo esc_html( $c['label'] ); ?></span>
                </div>
            </<?php echo $tag; ?>>
        <?php endforeach; ?>
    </div>

    <!-- ── Charts ── -->
    <?php if ( $layout['graficos'] ) : ?>
        <div class="wcd-charts">
            <?php foreach ( $layout['graficos'] as $g ) :
                $donut = 'doughnut' === $g['tipo']; ?>
                <div class="wcd-card <?php echo $donut ? 'wcd-card--donut' : 'wcd-card--chart'; ?>">
                    <div class="wcd-card__head"><h3><?php echo esc_html( $g['titulo'] ); ?></h3></div>
                    <?php if ( $donut ) : ?>
                        <div class="wcd-donut-wrap"><canvas id="<?php echo esc_attr( $g['id'] ); ?>"></canvas></div>
                        <?php if ( ! array_sum( (array) ( $g['series'][0]['dados'] ?? [] ) ) ) : ?>
                            <p class="wcd-empty" style="text-align:center">Sem dados ainda.</p>
                        <?php endif; ?>
                    <?php else : ?>
                        <canvas id="<?php echo esc_attr( $g['id'] ); ?>" height="100"></canvas>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ── Bottom row ── -->
    <div class="wcd-bottom">
        <div class="wcd-main-col">
            <?php foreach ( $layout['principal'] as $panel ) WCD_Mixed::render_panel( $panel ); ?>
        </div>
        <div class="wcd-sidebar">
            <?php foreach ( $layout['lateral'] as $panel ) WCD_Mixed::render_panel( $panel ); ?>
        </div>
    </div>
