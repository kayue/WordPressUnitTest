<?php

class UserTest extends WordPressTestCase
{
	function testGetUsersOfBlog() {
		// add one of each user role
		$userRoles = array();

		foreach ( array('administrator', 'editor', 'author', 'contributor', 'subscriber' ) as $role ) {
			$id = static::createUser($role);
			$userRoles[$id] = $role;
		}

		$users = get_users();

		// find the role of each user as returned by get_users_of_blog
		$found = array();
		foreach ( $users as $user ) {
			// only include the users we just created - there might be some others that existed previously
			if ( isset( $userRoles[$user->ID] ) ) {
				$user = new WP_User($user->ID);
				$this->assertTrue($user->has_cap($userRoles[$user->ID]));

				$found[$user->ID] = $user->roles[0];
			}
		}

		// make sure every user we created was returned
		$this->assertEquals($userRoles, $found);
	}


	/**
	 * Simple get/set tests for user_option functions
	 */ 
	function testUserOption() {
		$key = static::randomString();
		$val = static::randomString();

		$userId = static::createUser('author');

		// get an option that doesn't exist
		$this->assertFalse(get_user_option($key, $userId));

		// set and get
		update_user_option( $userId, $key, $val );
		$this->assertEquals( $val, get_user_option($key, $userId) );

		// change and get again
		$val2 = static::randomString();
		update_user_option( $userId, $key, $val2 );
		$this->assertEquals( $val2, get_user_option($key, $userId) );
	}


	/**
	 * Simple tests for usermeta functions
	 */
	function testUserMeta() {
		$key = static::randomString();
		$val = static::randomString();

		$userId = static::createUser('author');

		// get a meta key that doesn't exist
		$this->assertEmpty(get_user_meta($userId, $key) );

		// set and get
		update_user_meta($userId, $key, $val);
		$this->assertEquals($val, array_shift(get_user_meta($userId, $key)));

		// change and get again
		$val2 = static::randomString();
		update_user_meta($userId, $key, $val2);
		$this->assertEquals($val2, array_shift(get_user_meta($userId, $key)));

		// deleting based on key
		delete_user_meta($userId, $key);
		$this->assertEmpty(get_user_meta($userId, $key));

		// set user meta again
		update_user_meta($userId, $key, $val);
		$this->assertEquals($val, array_shift(get_user_meta($userId, $key)));

		// deleting based on key and a incorrect value
		delete_user_meta($userId, $key, static::randomString());
		$this->assertEquals($val, array_shift(get_user_meta($userId, $key)));

		// deleting based on correct key and value
		delete_user_meta( $userId, $key, $val );
		$this->assertEmpty(get_user_meta($userId, $key) );

	}
}