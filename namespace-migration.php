<?php

class Friends {
	const VERSION       = '1.9.1';
	const REQUIRED_ROLE = 'administrator';
	public static function on_frontend() {
		return Friends\Friends::on_frontend();
	}
	public static function get_instance() {
		return Friends\Friends::get_instance();
	}
}
class Friends_Feed {}
class Friend_User extends \WP_User {}
