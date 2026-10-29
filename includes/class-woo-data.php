<?php
defined( 'ABSPATH' ) || exit;

/**
 * Consultas do modo WooCommerce.
 * Funciona com pedidos em wp_posts (legado) e nas tabelas HPOS (wc_orders).
 */
class WCD_Woo_Data {

    // ─── Tabela de pedidos (HPOS ou legado) ──────────────────────────────────

    private static function hpos() {
        return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }

    /**
     * Partes do SQL de pedidos: FROM (com o total), tabela pura para JOIN, colunas de id/status/tipo/total/data.
     * No HPOS a data é GMT; no legado é a hora local do site.
     */
    private static function orders_sql() {
        global $wpdb;
        if ( self::hpos() ) {
            return [
                'from'   => "{$wpdb->prefix}wc_orders o",
                'table'  => "{$wpdb->prefix}wc_orders o",
                'id'     => 'o.id',
                'status' => 'o.status',
                'type'   => 'o.type',
                'total'  => 'o.total_amount',
                'date'   => 'o.date_created_gmt',
                'gmt'    => true,
            ];
        }
        return [
            'from'   => "{$wpdb->posts} o LEFT JOIN {$wpdb->postmeta} ot ON ot.post_id = o.ID AND ot.meta_key = '_order_total'",
            'table'  => "{$wpdb->posts} o",
            'id'     => 'o.ID',
            'status' => 'o.post_status',
            'type'   => 'o.post_type',
            'total'  => 'ot.meta_value',
            'date'   => 'o.post_date',
            'gmt'    => false,
        ];
    }

    /** Converte um horário local do site para o formato da coluna de data. */
    private static function date_param( $local, $sql ) {
        return $sql['gmt'] ? get_gmt_from_date( $local ) : $local;
    }

    public static function orders_list_url() {
        return self::hpos() ? admin_url( 'admin.php?page=wc-orders' ) : admin_url( 'edit.php?post_type=shop_order' );
    }

    // ─── KPIs ────────────────────────────────────────────────────────────────

    public static function get_kpis() {
        $today_start = wp_date( 'Y-m-d 00:00:00' );
        $today_end   = wp_date( 'Y-m-d 23:59:59' );
        $month_start = wp_date( 'Y-m-01 00:00:00' );

        return [
            'revenue_today'     => self::sum_revenue( $today_start, $today_end ),
            'revenue_month'     => self::sum_revenue( $month_start, $today_end ),
            'orders_today'      => self::count_orders( $today_start, $today_end ),
            'orders_pending'    => self::count_orders_by_status( 'wc-pending' ),
            'orders_processing' => self::count_orders_by_status( 'wc-processing' ),
            'total_customers'   => self::count_customers(),
            'avg_ticket'        => self::avg_ticket( $month_start, $today_end ),
        ];
    }

    private static function aggregate_paid( $fn, $start, $end ) {
        global $wpdb;
        $s = self::orders_sql();
        return (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT {$fn}( {$s['total']} ) FROM {$s['from']}
             WHERE {$s['type']} = 'shop_order'
               AND {$s['status']} IN ('wc-completed','wc-processing')
               AND {$s['date']} BETWEEN %s AND %s",
            self::date_param( $start, $s ), self::date_param( $end, $s )
        ) );
    }

    private static function sum_revenue( $start, $end ) {
        return self::aggregate_paid( 'SUM', $start, $end );
    }

    private static function avg_ticket( $start, $end ) {
        return self::aggregate_paid( 'AVG', $start, $end );
    }

    private static function count_orders( $start, $end ) {
        global $wpdb;
        $s = self::orders_sql();
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT( {$s['id']} ) FROM {$s['from']}
             WHERE {$s['type']} = 'shop_order'
               AND {$s['status']} NOT IN ('trash','auto-draft','wc-checkout-draft')
               AND {$s['date']} BETWEEN %s AND %s",
            self::date_param( $start, $s ), self::date_param( $end, $s )
        ) );
    }

    private static function count_orders_by_status( $status ) {
        global $wpdb;
        $s = self::orders_sql();
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT( {$s['id']} ) FROM {$s['from']}
             WHERE {$s['type']} = 'shop_order' AND {$s['status']} = %s",
            $status
        ) );
    }

    private static function count_customers() {
        $users = count_users();
        return (int) ( $users['avail_roles']['customer'] ?? 0 );
    }

    // ─── Gráfico: pedidos + receita últimos 30 dias ───────────────────────────

    public static function get_chart_data( $days = 30 ) {
        global $wpdb;
        $s     = self::orders_sql();
        $start = wp_date( 'Y-m-d 00:00:00', strtotime( "-{$days} days" ) );

        // No HPOS a data é GMT: agrupa pelo dia local somando o fuso do site
        $offset = $s['gmt'] ? (int) round( (float) get_option( 'gmt_offset' ) * 3600 ) : 0;
        $day    = "DATE( DATE_ADD( {$s['date']}, INTERVAL {$offset} SECOND ) )";

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT {$day} AS day, COUNT( {$s['id']} ) AS orders, SUM( {$s['total']} ) AS revenue
             FROM {$s['from']}
             WHERE {$s['type']} = 'shop_order'
               AND {$s['status']} IN ('wc-completed','wc-processing','wc-pending')
               AND {$s['date']} >= %s
             GROUP BY day
             ORDER BY day ASC",
            self::date_param( $start, $s )
        ) );

        $indexed = [];
        foreach ( $rows as $r ) {
            $indexed[ $r->day ] = $r;
        }

        $labels  = [];
        $orders  = [];
        $revenue = [];
        $tz      = wp_timezone();
        $period  = new DatePeriod(
            new DateTime( "-{$days} days", $tz ),
            new DateInterval( 'P1D' ),
            new DateTime( 'tomorrow', $tz )
        );

        // preenche todos os dias (sem gaps)
        foreach ( $period as $dt ) {
            $key       = $dt->format( 'Y-m-d' );
            $labels[]  = $dt->format( 'd/m' );
            $orders[]  = isset( $indexed[ $key ] ) ? (int) $indexed[ $key ]->orders    : 0;
            $revenue[] = isset( $indexed[ $key ] ) ? (float) $indexed[ $key ]->revenue : 0;
        }

        return compact( 'labels', 'orders', 'revenue' );
    }

    // ─── Pedidos recentes ─────────────────────────────────────────────────────

    public static function get_recent_orders( $limit = 10 ) {
        $orders = wc_get_orders( [
            'limit'   => $limit,
            'orderby' => 'date',
            'order'   => 'DESC',
            'type'    => 'shop_order',
        ] );

        $data = [];
        foreach ( $orders as $order ) {
            $data[] = [
                'id'       => $order->get_id(),
                'customer' => $order->get_formatted_billing_full_name() ?: __( 'Visitante', 'rajo-dashboard' ),
                'status'   => $order->get_status(),
                'total'    => $order->get_total(),
                'date'     => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd/m/Y H:i' ) : '—',
                'edit_url' => $order->get_edit_order_url(),
            ];
        }
        return $data;
    }

    // ─── Produtos mais vendidos ───────────────────────────────────────────────

    public static function get_top_products( $limit = 5 ) {
        global $wpdb;
        $s = self::orders_sql();

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT order_item_meta.meta_value AS product_id,
                    SUM( qty_meta.meta_value )  AS qty_sold
             FROM {$wpdb->prefix}woocommerce_order_items AS items
             INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_item_meta
                     ON items.order_item_id = order_item_meta.order_item_id
                    AND order_item_meta.meta_key = '_product_id'
             INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS qty_meta
                     ON items.order_item_id = qty_meta.order_item_id
                    AND qty_meta.meta_key = '_qty'
             INNER JOIN {$s['table']}
                     ON items.order_id = {$s['id']}
                    AND {$s['status']} IN ('wc-completed','wc-processing')
             WHERE items.order_item_type = 'line_item'
             GROUP BY product_id
             ORDER BY qty_sold DESC
             LIMIT %d",
            $limit
        ) );

        $data = [];
        foreach ( $rows as $r ) {
            $product = wc_get_product( $r->product_id );
            if ( ! $product ) continue;
            $data[] = [
                'name'     => $product->get_name(),
                'qty'      => (int) $r->qty_sold,
                'edit_url' => get_edit_post_link( $r->product_id ),
                'thumb'    => get_the_post_thumbnail_url( $r->product_id, [ 40, 40 ] ) ?: wc_placeholder_img_src( [ 40, 40 ] ),
            ];
        }
        return $data;
    }

    // ─── Produtos com estoque baixo ───────────────────────────────────────────

    public static function get_low_stock( $threshold = 5, $limit = 10 ) {
        $notify_no_stock_amount = absint( get_option( 'woocommerce_notify_no_stock_amount', 0 ) );
        $threshold = max( $threshold, $notify_no_stock_amount );

        global $wpdb;

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT posts.ID, posts.post_title,
                    CAST( stock_meta.meta_value AS SIGNED ) AS stock
             FROM {$wpdb->posts} AS posts
             INNER JOIN {$wpdb->postmeta} AS manage_meta
                     ON posts.ID = manage_meta.post_id
                    AND manage_meta.meta_key = '_manage_stock'
                    AND manage_meta.meta_value = 'yes'
             INNER JOIN {$wpdb->postmeta} AS stock_meta
                     ON posts.ID = stock_meta.post_id
                    AND stock_meta.meta_key = '_stock'
             WHERE posts.post_type   = 'product'
               AND posts.post_status = 'publish'
               AND CAST( stock_meta.meta_value AS SIGNED ) <= %d
               AND CAST( stock_meta.meta_value AS SIGNED ) >= 0
             ORDER BY stock ASC
             LIMIT %d",
            $threshold, $limit
        ) );

        $data = [];
        foreach ( $rows as $r ) {
            $data[] = [
                'name'     => $r->post_title,
                'stock'    => (int) $r->stock,
                'edit_url' => get_edit_post_link( $r->ID ),
            ];
        }
        return $data;
    }

    // ─── Pedidos por status (para donut) ─────────────────────────────────────

    public static function get_orders_by_status() {
        global $wpdb;
        $s = self::orders_sql();

        $counts = $wpdb->get_results(
            "SELECT {$s['status']} AS status, COUNT( {$s['id']} ) AS n FROM {$s['from']}
             WHERE {$s['type']} = 'shop_order' GROUP BY {$s['status']}",
            OBJECT_K
        );

        $data = [];
        foreach ( wc_get_order_statuses() as $slug => $label ) {
            $count = isset( $counts[ $slug ] ) ? (int) $counts[ $slug ]->n : 0;
            if ( $count > 0 ) {
                $data[] = [ 'label' => $label, 'count' => $count, 'slug' => $slug ];
            }
        }

        return $data;
    }
}
