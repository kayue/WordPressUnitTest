<?php

class WooCommerceTestCase extends WordPressTestCase
{
    static protected function insertQuickProducts($num = 1, $more = array())
    {
        if(!$num)   $num    = 1;
        if(!$more)  $more   = array();

        for ($i=0; $i<$num; $i++) {            
            $data = array_merge(
                array(
                    'post_author' => 1, 
                    'post_status' => 'publish',
                    'post_title' => "Product title {$i}",
                    'post_content' => "Product content {$i}",
                    'post_excerpt' => "Product excerpt {$i}",
                    'post_type' => 'product', 
                ),
                $more
            );

            wp_insert_post($data);
        }
    }

    /**
     * Insert a given number of trivial posts, each with predictable title, content and excerpt
     */
    protected static function insertQuickOrders($num = 1, $more = array(), $meta = array())
    {
        if(!$num)  $num  = 1;
        if(!$more) $more = array();
        if(!$meta) $meta = array();

        for ($i=0; $i<$num; $i++) {
            $orderId = wp_insert_post(array_merge(array(
                'post_author' => 1,
                'post_status' => 'publish',
                'post_title' => "Order title {$i}",
                'post_content' => "Order content {$i}",
                'post_excerpt' => "Order excerpt {$i}",
                'post_type' => 'shop_order'
            ), $more));

            $meta = array_merge(
                array(
                    'shipping_method'       => 'free_shipping',
                    'payment_method'        => 'paypal',
                    'shipping_method_title' => 'Free Shipping',
                    'payment_method_title'  => 'PayPal',
                    'order_shipping'        => 10.0,
                    'order_discount'        => 0.0,
                    'cart_discount'         => 0.0,
                    'tax_total'             => 0.0,
                    'order_shipping_tax'    => 0.0,
                    'order_total'           => 10.0,
                    'order_items'           => array(),
                    'order_taxes'           => array(),
                    'order_status'          => 'pending',
                ),
                $meta
            );

            // Save other order meta fields
            update_post_meta($orderId, '_shipping_method',        $meta['shipping_method']);
            update_post_meta($orderId, '_payment_method',         $meta['payment_method']);
            update_post_meta($orderId, '_shipping_method_title',  $meta['shipping_method_title']);
            update_post_meta($orderId, '_payment_method_title',   $meta['payment_method_title']);
            update_post_meta($orderId, '_order_shipping',         number_format((float)$meta['order_shipping'],     2, '.', '' ));
            update_post_meta($orderId, '_order_discount',         number_format((float)$meta['order_discount'],     2, '.', '' ));
            update_post_meta($orderId, '_cart_discount',          number_format((float)$meta['cart_discount'],      2, '.', '' ));
            update_post_meta($orderId, '_order_tax',              number_format((float)$meta['tax_total'],          2, '.', '' ));
            update_post_meta($orderId, '_order_shipping_tax',     number_format((float)$meta['order_shipping_tax'], 2, '.', '' ));
            update_post_meta($orderId, '_order_total',            number_format((float)$meta['order_total'],        2, '.', '' ));
            update_post_meta($orderId, '_order_key',              apply_filters('woocommerce_generate_order_key', uniqid('order_') ));
            update_post_meta($orderId, '_customer_user',          (int) 1 );
            update_post_meta($orderId, '_order_items',            $meta['order_items'] );
            update_post_meta($orderId, '_order_taxes',            $meta['order_taxes'] );
            update_post_meta($orderId, '_order_currency',         get_woocommerce_currency() );
            update_post_meta($orderId, '_prices_include_tax',     get_option('woocommerce_prices_include_tax') );

            wp_set_object_terms($orderId, $meta['order_status'], 'shop_order_status' );
        }
    }
}