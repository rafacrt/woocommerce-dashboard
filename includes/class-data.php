<?php
defined( 'ABSPATH' ) || exit;

class WCD_Data {

    // ─── KPIs ────────────────────────────────────────────────────────────────

    public static function get_kpis() {
        $today_start = date( 'Y-m-d 00:00:00' );
        $today_end   = date( 'Y-m-d 23:59:59' );
        $month_start = date( 'Y-m-01 00:00:00' );

        return [
            'revenue_today'   => self::sum_revenue( $today_start, $today_end ),
            'revenue_month'   => self::sum_revenue( $month_start, date( 'Y-m-d 23:59:59' ) ),
            'orders_today'    => self::count_orders( $today_start, $today_end ),
            'orders_pending'  => self::count_orders_by_status( 'wc-pending' ),
            'orders_processing' => self::count_orders_by_status( 'wc-processing' ),
            'total_customers' => self::count_customers(),
            'avg_ticket'      => self::avg_ticket( $month_start, date( 'Y-m-d 23:59:59' ) ),
        ];
    }

    private static function sum_revenue( $start, $end ) {
        global $wpdb;
        $result = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM( meta_value ) FROM {$wpdb->postmeta}
             WHERE meta_key = '_order_total'
               AND post_id IN (
                   SELECT ID FROM {$wpdb->posts}
                   WHERE post_type   = 'shop_order'
                     AND post_status IN ('wc-completed','wc-processing')
                     AND post_date BETWEEN %s AND %s
               )",
            $start, $end
        ) );
        return (float) $result;
    }

    private static function count_orders( $start, $end ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(ID) FROM {$wpdb->posts}
             WHERE post_type   = 'shop_order'
               AND post_status NOT IN ('trash','auto-draft')
               AND post_date BETWEEN %s AND %s",
            $start, $end
        ) );
    }

    private static function count_orders_by_status( $status ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(ID) FROM {$wpdb->posts}
             WHERE post_type = 'shop_order' AND post_status = %s",
            $status
        ) );
    }

    private static function count_customers() {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(ID) FROM {$wpdb->users}
             WHERE ID IN (
                 SELECT user_id FROM {$wpdb->usermeta}
                 WHERE meta_key = 'wp_capabilities'
                   AND meta_value LIKE '%customer%'
             )"
        );
    }

    private static function avg_ticket( $start, $end ) {
        global $wpdb;
        $result = $wpdb->get_var( $wpdb->prepare(
            "SELECT AVG( meta_value ) FROM {$wpdb->postmeta}
             WHERE meta_key = '_order_total'
               AND post_id IN (
                   SELECT ID FROM {$wpdb->posts}
                   WHERE post_type   = 'shop_order'
                     AND post_status IN ('wc-completed','wc-processing')
                     AND post_date BETWEEN %s AND %s
               )",
            $start, $end
        ) );
        return (float) $result;
    }

    // ─── Gráfico: pedidos + receita últimos 30 dias ───────────────────────────

    public static function get_chart_data( $days = 30 ) {
        global $wpdb;

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(post_date) AS day,
                    COUNT(ID) AS orders,
                    SUM( (SELECT meta_value FROM {$wpdb->postmeta}
                          WHERE post_id = p.ID AND meta_key = '_order_total' LIMIT 1) ) AS revenue
             FROM {$wpdb->posts} p
             WHERE post_type   = 'shop_order'
               AND post_status IN ('wc-completed','wc-processing','wc-pending')
               AND post_date  >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY DATE(post_date)
             ORDER BY day ASC",
            $days
        ) );

        $labels  = [];
        $orders  = [];
        $revenue = [];

        // preenche todos os dias (sem gaps)
        $period = new DatePeriod(
            new DateTime( "-{$days} days" ),
            new DateInterval( 'P1D' ),
            new DateTime( 'tomorrow' )
        );

        $indexed = [];
        foreach ( $rows as $r ) {
            $indexed[ $r->day ] = $r;
        }

        foreach ( $period as $dt ) {
            $key       = $dt->format( 'Y-m-d' );
            $labels[]  = $dt->format( 'd/m' );
            $orders[]  = isset( $indexed[ $key ] ) ? (int) $indexed[ $key ]->orders  : 0;
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
        ] );

        $data = [];
        foreach ( $orders as $order ) {
            $data[] = [
                'id'       => $order->get_id(),
                'customer' => $order->get_formatted_billing_full_name() ?: __( 'Visitante', 'wc-dashboard' ),
                'status'   => $order->get_status(),
                'total'    => $order->get_total(),
                'date'     => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd/m/Y H:i' ) : '—',
                'edit_url' => get_edit_post_link( $order->get_id() ),
            ];
        }
        return $data;
    }

    // ─── Produtos mais vendidos ───────────────────────────────────────────────

    public static function get_top_products( $limit = 5 ) {
        global $wpdb;

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
             INNER JOIN {$wpdb->posts} AS orders
                     ON items.order_id = orders.ID
                    AND orders.post_status IN ('wc-completed','wc-processing')
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

        $statuses = wc_get_order_statuses();
        $data     = [];

        foreach ( $statuses as $slug => $label ) {
            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(ID) FROM {$wpdb->posts}
                 WHERE post_type = 'shop_order' AND post_status = %s",
                $slug
            ) );
            if ( $count > 0 ) {
                $data[] = [ 'label' => $label, 'count' => $count, 'slug' => $slug ];
            }
        }

        return $data;
    }
}
