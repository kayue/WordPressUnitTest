<?php

class WordPressUnitTest
{
    static $options = null;

    private function __construct()
    {
        return;
    }

    public static function init($options = null)
    {
        self::setOptions($options);

        require(__DIR__.'/init.php');
        require(__DIR__.'/Test/WordPressTestCase.php');
        require(__DIR__.'/Test/WooCommerceTestCase.php');
    }

    public static function setOptions(array $options)
    {
        return self::$options = $options;
    }

    public static function getOptions()
    {
        return self::$options;
    }

    public static function loadBuddyPress()
    {
        add_action('bp_loaded', function(){
            error_reporting(E_ALL ^ E_USER_NOTICE ^ E_DEPRECATED);
        });

        add_action('wp', function() {
            error_reporting(E_ALL);
        });
    }

    public static function loadWooCommerce()
    {
        $woocommerce = $GLOBALS['woocommerce'];
        $woocommerce->admin_includes();
        install_woocommerce();
    }
}