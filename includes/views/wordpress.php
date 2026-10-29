<?php
/**
 * Painel do modo WordPress.
 */
defined( 'ABSPATH' ) || exit;

$kpis    = WCD_WP_Data::get_kpis();
$types   = WCD_WP_Data::get_content_by_type();
$taxes   = WCD_WP_Data::get_taxonomies();
$recent  = WCD_WP_Data::get_recent_changes( 10 );
$drafts  = WCD_WP_Data::get_drafts( 8 );
$site    = WCD_WP_Data::get_site_info();
$num     = fn( $n ) => number_format( (int) $n, 0, ',', '.' );
$statuses = [ 'publish' => 'Publicado', 'draft' => 'Rascunho', 'pending' => 'Pendente', 'private' => 'Privado', 'future' => 'Agendado' ];
?>

    <!-- ── KPI Cards ── -->
    <div class="wcd-kpis">
        <?php
        $cards = [
            [ 'label' => 'Páginas publicadas',   'value' => $num( $kpis['pages'] ),            'icon' => '📄', 'color' => 'blue',   'url' => admin_url( 'edit.php?post_type=page' ) ],
            [ 'label' => 'Posts publicados',     'value' => $num( $kpis['posts'] ),            'icon' => '📝', 'color' => 'purple', 'url' => admin_url( 'edit.php' ) ],
            [ 'label' => 'Rascunhos',            'value' => $num( $kpis['drafts'] ),           'icon' => '✏️', 'color' => 'orange', 'url' => '' ],
            [ 'label' => 'Arquivos de mídia',    'value' => $num( $kpis['media'] ),            'icon' => '🖼️', 'color' => 'teal',   'url' => admin_url( 'upload.php' ) ],
            [ 'label' => 'Usuários',             'value' => $num( $kpis['users'] ),            'icon' => '👥', 'color' => 'pink',   'url' => admin_url( 'users.php' ) ],
            [ 'label' => 'Comentários pendentes', 'value' => $num( $kpis['comments_pending'] ), 'icon' => '💬', 'color' => 'indigo', 'url' => admin_url( 'edit-comments.php?comment_status=moderated' ) ],
            [ 'label' => 'Atualizações',         'value' => $num( $kpis['updates'] ),          'icon' => '🔄', 'color' => $kpis['updates'] ? 'red' : 'green', 'url' => admin_url( 'update-core.php' ) ],
        ];
        foreach ( $cards as $c ) :
            $tag = $c['url'] ? 'a' : 'div'; ?>
            <<?php echo $tag; ?> class="wcd-kpi wcd-kpi--<?php echo esc_attr( $c['color'] ); ?>"<?php echo $c['url'] ? ' href="' . esc_url( $c['url'] ) . '"' : ''; ?>>
                <div class="wcd-kpi__icon"><?php echo $c['icon']; ?></div>
                <div class="wcd-kpi__info">
                    <span class="wcd-kpi__value"><?php echo esc_html( $c['value'] ); ?></span>
                    <span class="wcd-kpi__label"><?php echo esc_html( $c['label'] ); ?></span>
                </div>
            </<?php echo $tag; ?>>
        <?php endforeach; ?>
    </div>

    <!-- ── Charts ── -->
    <div class="wcd-charts">
        <div class="wcd-card wcd-card--chart">
            <div class="wcd-card__head"><h3>Publicações — últimos 30 dias</h3></div>
            <canvas id="wcd-chart-published" height="100"></canvas>
        </div>
        <div class="wcd-card wcd-card--chart">
            <div class="wcd-card__head"><h3>Edições — últimos 30 dias</h3></div>
            <canvas id="wcd-chart-edited" height="100"></canvas>
        </div>
        <div class="wcd-card wcd-card--donut">
            <div class="wcd-card__head"><h3>Mídia por tipo</h3></div>
            <div class="wcd-donut-wrap">
                <canvas id="wcd-chart-donut"></canvas>
            </div>
        </div>
    </div>

    <!-- ── Bottom row ── -->
    <div class="wcd-bottom">

        <div class="wcd-main-col">

            <!-- Conteúdo por tipo -->
            <div class="wcd-card">
                <div class="wcd-card__head"><h3>Conteúdo por tipo</h3></div>
                <div class="wcd-table-wrap">
                    <table class="wcd-table">
                        <thead><tr><th>Tipo</th><th>Publicados</th><th>Rascunhos</th><th>Privados / agendados</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ( $types as $t ) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $t['label'] ); ?></strong></td>
                                    <td><?php echo esc_html( $num( $t['publish'] ) ); ?></td>
                                    <td><?php echo $t['draft'] ? '<span class="wcd-badge wcd-badge--pending">' . esc_html( $num( $t['draft'] ) ) . '</span>' : '0'; ?></td>
                                    <td><?php echo esc_html( $num( $t['private'] ) ); ?></td>
                                    <td><a href="<?php echo esc_url( $t['list_url'] ); ?>">Abrir →</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Alterações recentes -->
            <div class="wcd-card">
                <div class="wcd-card__head"><h3>Alterações recentes</h3></div>
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
            </div>

        </div>

        <!-- Sidebar: rascunhos, taxonomias, saúde -->
        <div class="wcd-sidebar">

            <div class="wcd-card">
                <div class="wcd-card__head"><h3>✏️ Rascunhos</h3></div>
                <?php if ( empty( $drafts ) ) : ?>
                    <p class="wcd-empty">Nenhum rascunho.</p>
                <?php else : ?>
                    <ul class="wcd-stock-list">
                        <?php foreach ( $drafts as $d ) : ?>
                            <li>
                                <a href="<?php echo esc_url( $d['edit_url'] ); ?>"><?php echo esc_html( $d['title'] ); ?></a>
                                <span class="wcd-stock-badge wcd-stock-badge--low"><?php echo esc_html( $d['type'] ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <?php if ( $taxes ) : ?>
                <div class="wcd-card">
                    <div class="wcd-card__head"><h3>🏷️ Categorias e termos</h3></div>
                    <ul class="wcd-stock-list">
                        <?php foreach ( $taxes as $tx ) : ?>
                            <li>
                                <a href="<?php echo esc_url( $tx['list_url'] ); ?>"><?php echo esc_html( $tx['label'] ); ?></a>
                                <span class="wcd-qty"><?php echo esc_html( $num( $tx['count'] ) ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="wcd-card">
                <div class="wcd-card__head">
                    <h3>🩺 Saúde do site</h3>
                    <?php if ( current_user_can( 'view_site_health_checks' ) ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'site-health.php' ) ); ?>">Detalhes →</a>
                    <?php endif; ?>
                </div>
                <ul class="wcd-stock-list">
                    <?php foreach ( $site as $s ) : ?>
                        <li><span><?php echo esc_html( $s['label'] ); ?></span><span class="wcd-qty"><?php echo esc_html( $s['value'] ); ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>

        </div>
    </div>
