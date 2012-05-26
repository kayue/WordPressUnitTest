<?php

class UserTest extends WpTestCase
{
	function testGetUsersOfBlog() {
		// add one of each user role
		$userRoles = array();

		foreach ( array('administrator', 'editor', 'author', 'contributor', 'subscriber' ) as $role ) {
			$id = static::makeUser($role);
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
}