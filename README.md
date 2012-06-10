# WordPress Unit Test

This is a fork of Nikolay's [wordpress-tests](https://github.com/nb/wordpress-tests) project, but includes a lot more tests from the original WordPress's automated [testing suite](http://unit-tests.trac.wordpress.org/).

## Rationale

WordPress already has an automated [testing suite](http://unit-tests.trac.wordpress.org/). What you see here is an alternative testing framework, with the following goals:

* Faster
* Runs every test case in a clean WordPress install
* Uses the default PHPUnit runner, instead of custom one
* Doesn't encourage or support the usage of shared/prebuilt fixtures

It uses **SQL transactions** to clean up automatically after each test.

## Installation

1. Create config file. Copy `unittests-config-sample.php` to `unittests-config.php`.
4. Edit the config file. 
5. Since the tests will be running on a separated Wordpress installation, please **use a new database, because all the data inside will be deleted** to create a clean environment .
3. Run tests with `$ phpunit`

## Example

Please see [TwentyTest](https://github.com/kayue/twentytest).
 
## Usage

#### bootstrap.php

```php
<?php

include('/path/to/WordPressUnitTest/WordPressUnitTest.php');

WordPressUnitTest::init(array(
    // parent theme.
    'template' => 'twentyeleven',

    // child theme, set to the same as parent theme if not a child theme.
    'stylesheet' => 'twentytest',

    // activate plugins.
    'active_plugins' => array(
        // BuddyPress
        'buddypress/bp-loader.php',

        // WooCommerce
        'woocommerce/woocommerce.php',

        // other plugins...
    ),

    'plugins_loaded' => function() {
        // optional housekeeping work here
        WordPressUnitTest::loadBuddyPress();
        WordPressUnitTest::loadWooCommerce();
    },
));
```

#### phpunit.xml

```xml
<?xml version="1.0" encoding="UTF-8"?>

<phpunit bootstrap="tests/bootstrap.php" backupGlobals="false">
    <testsuites>
        <testsuite name="My Theme's Test Suite">
            <directory>./tests/</directory>
        </testsuite>
    </testsuites>
</phpunit>
```