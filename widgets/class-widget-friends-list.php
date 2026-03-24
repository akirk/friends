<?php
/**
 * Friend List Widget
 *
 * A widget that displays a list of your friends.
 *
 * @package Friends
 * @since 0.3
 */

namespace Friends;

/**
 * This is the class for the Friend List Widget.
 *
 * @package Friends
 * @author Alex Kirk
 */
class Widget_Friends_List extends Widget_Base_Friends_List {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			'friends-widget-friend-list',
			__( 'Friend List', 'friends' ),
			array(
				'description' => __( 'Shows a list of your friends.', 'friends' ),
			)
		);
	}

	/**
	 * Return default values for this widget's options.
	 *
	 * @return array
	 */
	public function defaults() {
		return array(
			'title'      => '',
			'user_types' => 'folders',
			'folder'     => 0,
		);
	}

	/**
	 * Save updated settings from the widget form.
	 *
	 * @param array $new_instance New settings.
	 * @param array $old_instance Old settings.
	 * @return array Sanitized settings.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance                = $this->defaults();
		$instance['title']       = sanitize_text_field( $new_instance['title'] );
		$instance['user_types']  = in_array( $new_instance['user_types'], array( 'subscriptions', 'starred', 'folders' ), true ) ? $new_instance['user_types'] : 'folders';
		$instance['folder']      = intval( $new_instance['folder'] );
		return $instance;
	}

	/**
	 * Output the widget settings form.
	 *
	 * @param array $instance Current settings.
	 */
	public function form( $instance ) {
		$instance   = wp_parse_args( $instance, $this->defaults() );
		$user_types = $instance['user_types'];
		$folder     = intval( $instance['folder'] );
		$folders    = Subscription::get_folders();
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'friends' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'user_types' ) ); ?>"><?php esc_html_e( 'Display:', 'friends' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'user_types' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'user_types' ) ); ?>">
				<option value="subscriptions"<?php selected( $user_types, 'subscriptions' ); ?>><?php esc_html_e( 'Subscriptions', 'friends' ); ?></option>
				<option value="starred"<?php selected( $user_types, 'starred' ); ?>><?php esc_html_e( 'Starred', 'friends' ); ?></option>
				<option value="folders"<?php selected( $user_types, 'folders' ); ?>><?php esc_html_e( 'Grouped by Folder', 'friends' ); ?></option>
			</select>
		</p>
		<?php if ( ! empty( $folders ) ) : ?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'folder' ) ); ?>"><?php esc_html_e( 'Folder:', 'friends' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'folder' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'folder' ) ); ?>">
				<option value="0"<?php selected( $folder, 0 ); ?>><?php esc_html_e( '— All —', 'friends' ); ?></option>
				<?php foreach ( $folders as $f ) : ?>
				<option value="<?php echo esc_attr( $f->term_id ); ?>"<?php selected( $folder, $f->term_id ); ?>><?php echo esc_html( $f->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render the widget.
	 *
	 * @param array $args Sidebar arguments.
	 * @param array $instance Widget instance settings.
	 */
	public function widget( $args, $instance ) {
		$instance   = wp_parse_args( $instance, $this->defaults() );
		$user_types = $instance['user_types'];
		$folder     = intval( $instance['folder'] );
		$widget_id  = ! empty( $args['widget_id'] ) ? $args['widget_id'] : '';

		if ( 'starred' === $user_types ) {
			$users = User_Query::starred_friends_subscriptions();
		} elseif ( $folder > 0 ) {
			$users = User_Query::subscriptions_in_folder( $folder );
		} else {
			$users = User_Query::all_subscriptions();
		}

		if ( 0 === $users->get_total() && 'folders' !== $user_types ) {
			do_action( 'friends_widget_friend_list_after', $this, $args );
			return;
		}

		echo $args['before_widget'];

		if ( 'folders' === $user_types ) {
			$folders = Subscription::get_folders();
			foreach ( $folders as $f ) {
				$folder_subs = User_Query::subscriptions_in_folder( $f->term_id );
				if ( $folder_subs->get_total() > 0 ) {
					$this->list_friends(
						array_merge( $args, array( 'widget_id' => $widget_id . '-folder-' . $f->term_id ) ),
						'&#128193; ' . esc_html( $f->name ) . ' <span class="subscription-count">' . $folder_subs->get_total() . '</span>',
						$folder_subs
					);
				}
			}
			$unfoldered = User_Query::unfoldered_subscriptions();
			if ( $unfoldered->get_total() > 0 ) {
				$this->list_friends(
					array_merge( $args, array( 'widget_id' => $widget_id . '-subscriptions' ) ),
					'<span class="dashicons dashicons-admin-users"></span> ' . sprintf(
						// translators: %s is the number of subscriptions.
						_n( 'Subscription %s', 'Subscriptions %s', $unfoldered->get_total(), 'friends' ),
						'<span class="subscription-count">' . $unfoldered->get_total() . '</span>'
					),
					$unfoldered
				);
			}
		} else {
			$folder_term = $folder > 0 ? get_term( $folder, Subscription::TAXONOMY ) : null;
			if ( $folder_term && ! is_wp_error( $folder_term ) ) {
				$title = '&#128193; ' . esc_html( $folder_term->name ) . ' <span class="subscription-count">' . $users->get_total() . '</span>';
			} else {
				$title = '<span class="dashicons dashicons-admin-users"></span> ' . sprintf(
					// translators: %s is the number of subscriptions.
					_n( 'Subscription %s', 'Subscriptions %s', $users->get_total(), 'friends' ),
					'<span class="subscription-count">' . $users->get_total() . '</span>'
				);
			}
			$this->list_friends(
				array_merge( $args, array( 'widget_id' => $widget_id . '-subscriptions' ) ),
				$title,
				$users
			);
		}

		echo $args['after_widget'];

		do_action( 'friends_widget_friend_list_after', $this, $args );
	}
}
