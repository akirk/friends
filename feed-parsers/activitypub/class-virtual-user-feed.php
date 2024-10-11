<?php
/**
 * Virtual User Feed Class
 *
 * @package Friends
 */

namespace Friends;

/**
 * Virtual User Feed Class
 *
 * @author Alex Kirk
 */
class Virtual_User_Feed extends User_Feed {
	private $friend_user;
	private $title;

	public function __construct( User $friend_user, $title ) {
		$this->friend_user = $friend_user;
		$this->title = $title;
	}
	public function __toString() {
	}
	public function get_id() {
		return 'virtual-user-feed-' . $this->friend_user->get_id();
	}
	public function get_url() {
		return null;
	}
	public function get_private_url( $validity = 3600 ) {
		return null;
	}
	public function get_friend_user() {
		return $this->friend_user;
	}
	public function get_active() {
		return false;
	}
	public function get_post_format() {
		return 'status';
	}
	public function get_parser() {
		return 'virtual';
	}
	public function get_mime_type() {
		return '';
	}
	public function get_title() {
		return $this->title;
	}
	public function get_last_log() {
		return '';
	}
	public function is_active() {
		return true;
	}
	public function get_interval() {
		return 0;
	}
	public function get_modifier() {
		return 0;
	}
	public function get_next_poll() {
		return 0;
	}
	public function was_polled() {
		return false;
	}
	public function can_be_polled_now() {
		return true;
	}
	public function set_polling_now() {
		return true;
	}
	public function activate() {
		return true;
	}
	public function deactivate() {
		return false;
	}
	public function delete() {
		return false;
	}
	public function get_metadata( $key ) {
		return null;
	}
	public function update_metadata( $key, $value ) {
		return $value;
	}
	public function delete_metadata( $key ) {
		return null;
	}
	public function update_last_log( $value ) {
	}
}
