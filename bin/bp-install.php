<?php

/**
 * BuddyPress Installer
 */

error_reporting( E_ALL & ~E_DEPRECATED & ~E_STRICT );

$config_file_path = $argv[1];
require_once $config_file_path;

$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['HTTP_HOST'] = WP_TESTS_DOMAIN;
$PHP_SELF = $GLOBALS['PHP_SELF'] = $_SERVER['PHP_SELF'] = '/index.php';

require_once(ABSPATH . '/wp-settings.php');

// Skip if BuddyPress doesn't exist
if(!file_exists(ABSPATH . '/wp-content/plugins/buddypress/bp-loader.php')) {
    return;
}

// Skip if BuddyPress has been installed.
if(get_site_option('bp-db-version') || get_option('bp-db-version')) {
    return;
}

require_once(ABSPATH . '/wp-content/plugins/buddypress/bp-loader.php');

// setup wizard
bp_core_setup_wizard_init();

// setup components
$components = array(
    // requried components
    'members' => 1,
    // optionals components
    'xprofile' => 1,
    'settings' => 1,
    'friends' => 1,
    'messages' => 1,
    'activity' => 1,
    'groups' => 1,
    'forums' => 1,
    'blogs' => 1,
);
bp_core_install($components);
bp_update_option('bp-active-components', $components);

// setup pages and save mapping
$pages = array(
    'blogs',
    'members',
    'activity',
    'groups',
    'forums',
    'register',
    'activate',
);
bp_update_option('bp-pages', array_combine($pages, $bp_wizard->setup_pages($pages)));

// set database version
update_site_option( 'bp-db-version', $bp_wizard->new_version );