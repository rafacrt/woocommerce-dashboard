<?php
defined( 'ABSPATH' ) || exit;

/**
 * Consultas do modo WordPress: conteúdo, rascunhos, mídia, usuários e saúde do site.
 * Os tipos de conteúdo e taxonomias são descobertos sozinhos (inclui os personalizados de cada site).
 */
class WCD_WP_Data {

    /** Tipos internos que não interessam no painel. */
    const IGNORED_TYPES = [ 'attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles', 'wp_font_family', 'wp_font_face', 'shop_order', 'shop_order_refund', 'shop_coupon' ];

    /** Tipos de conteúdo com tela no admin: [ nome => objeto ]. */
    public static function content_types() {
        $types = get_post_types( [ 'show_ui' => true ], 'objects' );
        foreach ( self::IGNORED_TYPES as $ignored ) {
            unset( $types[ $ignored ] );
        }
        return $types;
    }

    // ─── KPIs ────────────────────────────────────────────────────────────────

    public static function get_kpis() {
        $drafts = 0;
        foreach ( self::content_types() as $name => $type ) {
            $c       = wp_count_posts( $name );
            $drafts += (int) ( $c->draft ?? 0 ) + (int) ( $c->pending ?? 0 );
        }
        $comments = wp_count_comments();
        $users    = count_users();

        return [
            'pages'            => (int) ( wp_count_posts( 'page' )->publish ?? 0 ),
            'posts'            => (int) ( wp_count_posts( 'post' )->publish ?? 0 ),
            'drafts'           => $drafts,
            'media'            => (int) array_sum( (array) wp_count_attachments() ),
            'users'            => (int) $users['total_users'],
            'comments_pending' => (int) $comments->moderated,
            'updates'          => self::count_updates(),
        ];
    }

    private static function count_updates() {
        $total = 0;
        foreach ( [ 'update_plugins', 'update_themes' ] as $transient ) {
            $data   = get_site_transient( $transient );
            $total += ! empty( $data->response ) ? count( $data->response ) : 0;
        }
        $core = get_site_transient( 'update_core' );
        if ( ! empty( $core->updates[0]->response ) && 'upgrade' === $core->updates[0]->response ) {
            $total++;
        }
        return $total;
    }

    // ─── Conteúdo por tipo ───────────────────────────────────────────────────

    public static function get_content_by_type() {
        $data = [];
        foreach ( self::content_types() as $name => $type ) {
            $c         = wp_count_posts( $name );
            $publish   = (int) ( $c->publish ?? 0 );
            $draft     = (int) ( $c->draft ?? 0 ) + (int) ( $c->pending ?? 0 );
            $private   = (int) ( $c->private ?? 0 ) + (int) ( $c->future ?? 0 );
            if ( ! $publish && ! $draft && ! $private ) continue;
            $data[] = [
                'name'     => $name,
                'label'    => $type->labels->name,
                'publish'  => $publish,
                'draft'    => $draft,
                'private'  => $private,
                'list_url' => admin_url( 'post' === $name ? 'edit.php' : 'edit.php?post_type=' . $name ),
            ];
        }
        usort( $data, fn( $a, $b ) => $b['publish'] + $b['draft'] <=> $a['publish'] + $a['draft'] );
        return $data;
    }

    // ─── Taxonomias ──────────────────────────────────────────────────────────

    public static function get_taxonomies() {
        $data = [];
        foreach ( get_taxonomies( [ 'show_ui' => true ], 'objects' ) as $name => $tax ) {
            if ( in_array( $name, [ 'nav_menu', 'link_category', 'post_format', 'wp_pattern_category' ], true ) ) continue;
            $count = wp_count_terms( [ 'taxonomy' => $name, 'hide_empty' => false ] );
            if ( is_wp_error( $count ) || ! $count ) continue;
            $object_type = $tax->object_type[0] ?? 'post';
            $data[] = [
                'label'    => $tax->labels->name,
                'count'    => (int) $count,
                'list_url' => admin_url( 'edit-tags.php?taxonomy=' . $name . ( 'post' !== $object_type ? '&post_type=' . $object_type : '' ) ),
            ];
        }
        return $data;
    }

    // ─── Listas de itens ─────────────────────────────────────────────────────

    private static function list_posts( array $args, $limit ) {
        $query = new WP_Query( $args + [
            'post_type'              => array_keys( self::content_types() ),
            'posts_per_page'         => $limit,
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'ignore_sticky_posts'    => true,
        ] );

        $data = [];
        foreach ( $query->posts as $post ) {
            $type   = get_post_type_object( $post->post_type );
            $editor = get_userdata( (int) get_post_meta( $post->ID, '_edit_last', true ) ?: (int) $post->post_author );
            $data[] = [
                'title'    => get_the_title( $post ) ?: '(sem título)',
                'type'     => $type ? $type->labels->singular_name : $post->post_type,
                'status'   => $post->post_status,
                'author'   => $editor ? $editor->display_name : '—',
                'date'     => mysql2date( 'd/m/Y H:i', $post->post_modified ),
                'edit_url' => get_edit_post_link( $post->ID ),
            ];
        }
        return $data;
    }

    public static function get_recent_changes( $limit = 10 ) {
        return self::list_posts( [ 'post_status' => [ 'publish', 'draft', 'pending', 'private', 'future' ], 'orderby' => 'modified', 'order' => 'DESC' ], $limit );
    }

    public static function get_drafts( $limit = 8 ) {
        return self::list_posts( [ 'post_status' => [ 'draft', 'pending' ], 'orderby' => 'modified', 'order' => 'DESC' ], $limit );
    }

    // ─── Gráfico: publicações e edições últimos 30 dias ──────────────────────

    public static function get_chart_data( $days = 30 ) {
        global $wpdb;

        $types        = array_keys( self::content_types() );
        $placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
        $start        = wp_date( 'Y-m-d 00:00:00', strtotime( "-{$days} days" ) );

        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $published = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(post_date) AS day, COUNT(ID) AS n FROM {$wpdb->posts}
             WHERE post_type IN ($placeholders) AND post_status = 'publish' AND post_date >= %s
             GROUP BY day",
            array_merge( $types, [ $start ] )
        ), OBJECT_K );
        $edited = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(post_modified) AS day, COUNT(ID) AS n FROM {$wpdb->posts}
             WHERE post_type IN ($placeholders) AND post_status NOT IN ('auto-draft','trash','inherit') AND post_modified >= %s
             GROUP BY day",
            array_merge( $types, [ $start ] )
        ), OBJECT_K );
        // phpcs:enable

        $labels = [];
        $pub    = [];
        $edit   = [];
        $tz     = wp_timezone();
        $period = new DatePeriod( new DateTime( "-{$days} days", $tz ), new DateInterval( 'P1D' ), new DateTime( 'tomorrow', $tz ) );
        foreach ( $period as $dt ) {
            $key      = $dt->format( 'Y-m-d' );
            $labels[] = $dt->format( 'd/m' );
            $pub[]    = isset( $published[ $key ] ) ? (int) $published[ $key ]->n : 0;
            $edit[]   = isset( $edited[ $key ] ) ? (int) $edited[ $key ]->n : 0;
        }

        return [ 'labels' => $labels, 'published' => $pub, 'edited' => $edit ];
    }

    // ─── Mídia por tipo (donut) ──────────────────────────────────────────────

    public static function get_media_by_type() {
        $groups = [ 'image' => 'Imagens', 'video' => 'Vídeos', 'audio' => 'Áudios', 'application' => 'Documentos' ];
        $counts = [];
        foreach ( (array) wp_count_attachments() as $mime => $n ) {
            if ( 'trash' === $mime || ! $n ) continue;
            $group            = strtok( $mime, '/' );
            $label            = $groups[ $group ] ?? 'Outros';
            $counts[ $label ] = ( $counts[ $label ] ?? 0 ) + (int) $n;
        }
        arsort( $counts );
        $data = [];
        foreach ( $counts as $label => $n ) {
            $data[] = [ 'label' => $label, 'count' => $n ];
        }
        return $data;
    }

    // ─── Saúde do site ───────────────────────────────────────────────────────

    public static function get_site_info() {
        global $wp_version;
        $theme   = wp_get_theme();
        $plugins = (array) get_option( 'active_plugins', [] );

        return [
            [ 'label' => 'WordPress',       'value' => $wp_version ],
            [ 'label' => 'PHP',             'value' => PHP_VERSION ],
            [ 'label' => 'Tema',            'value' => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) ],
            [ 'label' => 'Plugins ativos',  'value' => count( $plugins ) ],
            [ 'label' => 'Cache de objetos', 'value' => wp_using_ext_object_cache() ? 'Ativo' : 'Não' ],
            [ 'label' => 'Modo debug',      'value' => defined( 'WP_DEBUG' ) && WP_DEBUG ? 'Ligado' : 'Desligado' ],
            [ 'label' => 'Indexação',       'value' => get_option( 'blog_public' ) ? 'Liberada' : 'Bloqueada para buscadores' ],
        ];
    }
}
