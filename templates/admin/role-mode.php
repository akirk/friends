<?php
/**
 * This template contains the Friends Role Mode Switcher
 *
 * @package Friends
 */

if ( empty( $args['roles'] ) ) {
	return;
}

?>

<h2><?php esc_html_e( 'Do you use your website in a private or professional context?', 'friends' ); ?></h1>
<p>
	<?php esc_html_e( 'Or in other words: Will your network consist of friends (as in a social network) or business acquaintances (as in business networking)?', 'friends' ); ?>
</p>
<p>
	<?php esc_html_e( 'The friend plugin defines a couple of relevant roles that make it work.', 'friends' ); ?>
	<?php esc_html_e( 'You can customize those roles to your liking to better represent your network.', 'friends' ); ?>
</p>
<h3>Presets</h3>
<label><input type="radio" name="role-preset" value="private" checked="checked" /> Private use case</label>
<label><input type="radio" name="role-preset" value="professional" /> Professional use case</label>

<h3>Standard Roles</h3>

<form action="">
	<table>
		<thead>
			<tr>
				<th><?php echo esc_html( _x( 'Identifier', 'role-type', 'friends' ) ); ?></th>
				<th><?php echo esc_html( _x( 'Label', 'role-type', 'friends' ) ); ?></th>
				<th><?php echo esc_html( _x( 'Type', 'role-type', 'friends' ) ); ?></th>
				<th><?php echo esc_html_e( 'Action', 'friends' ); ?></th>
			</tr>
		</thead>
<?php
foreach ( $args['roles'] as $role => $data ) {
	?>
	<tr>
		<td><?php echo esc_html( $role ); ?></td>
		<td><input type="text" name="role[<?php echo esc_attr( $role ); ?>][name]" value="<?php echo esc_attr( $data['name'] ); ?>" placeholder="Name"></td>
		<td>
		<?php
		if ( isset( $data['capabilities']['acquaintance'] ) ) {
			?>
			<?php esc_html_e( 'Friend who can only read public posts', 'friends' ); ?>
			<?php
		} elseif ( isset( $data['capabilities']['friend'] ) ) {
			?>
			<?php esc_html_e( 'Friend who can also read private posts', 'friends' ); ?>
			<?php
		} elseif ( isset( $data['capabilities']['friend_request'] ) ) {
			?>
			<?php esc_html_e( 'A received request to form a connection', 'friends' ); ?>
			<?php
		} elseif ( isset( $data['capabilities']['pending_friend_request'] ) ) {
			?>
			<?php esc_html_e( 'A request sent by you to form a connection', 'friends' ); ?>
			<?php
		} elseif ( isset( $data['capabilities']['subscription'] ) ) {
			?>
			<?php esc_html_e( 'You are just subscribed to their posts', 'friends' ); ?>
			<?php
		}
		?>
		</td>
		<td></td>
	</tr>
	<?php
}
?>
	</table>
</form>

<h3>Additional Roles</h3>
<form action="">
<table class="form-table">
	<tr>
		<th scope="row"><label for="additional-role-identifier"><?php echo esc_html( _x( 'Identifier', 'role-type', 'friends' ) ); ?></label></th>
		<td><input type="text" id="additional-role-identifier" value="" pattern="[a-z0-9-]{4,}" title="This needs to be one word with no extra characters." required="required" />
			<p class="description">This needs to be one word with no extra characters.</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="additional-role-name"><?php echo esc_html( _x( 'Label', 'role-type', 'friends' ) ); ?></label></th>
		<td><input type="text" id="additional-role-name" value="" pattern="[a-z0-9-]{3,}" title="This will be displayed for the role." required="required" />
			<p class="description">This will be displayed for the role.</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="additional-role-type"><?php echo esc_html( _x( 'Type', 'role-type', 'friends' ) ); ?></label></th>
		<td>
			<select id="additional-role-type" required="required">
				<option value="acquaintance"><?php esc_html_e( 'Friend who can only read public posts', 'friends' ); ?></option>
				<option value="friend"><?php esc_html_e( 'Friend who can also read private posts', 'friends' ); ?></option>
			</select>
		</td>
	</tr>
</table>
<button>Create</button>
</form>
