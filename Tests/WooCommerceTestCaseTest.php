<?php

class WooCommerceTestCaseTest extends WooCommerceTestCase
{
    public function testInsertQuickProducts()
    {
        $numberOfProducts = rand(1, 9);
        static::insertQuickProducts($numberOfProducts);

        $query = new WP_Query(array('post_type' => 'product'));

        $this->assertEquals($numberOfProducts, count($query->posts));
    }

    public function testInsertQuickOrders()
    {
        static::insertQuickOrders(3, null, array('order_status' => 'pending'));
        static::insertQuickOrders(2, null, array('order_status' => 'failed'));

        $query = new WP_Query(array('post_type' => 'shop_order'));

        $this->assertEquals(5, count($query->posts));
    }
}