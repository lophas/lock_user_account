<?php
/*
 * Version: 1.4
 * Author: Attila Seres
 * Source: https://github.com/lophas/lock_user_account/
- adds multisite support
- adds profile edit support
- adds dropdown filter
- immediately logs out locked users
- fixes minor issues (i.e. hardcoded referer)
*/
class fix_Baba_User_Meta {
	function __construct() {
		add_action('admin_init', [$this, 'admin_init'], 9);
	}
	public function admin_init() {
		if(!class_exists('Baba_User_Meta')) return;
		global $wp_filter;
		$hook_name = 'manage_users_columns';
		if ( $filters = $wp_filter[ $hook_name ]) {
			foreach($filters->callbacks[10] as $filter) {
				if($filter['function'][1] == 'register_column_header') {
					$instance = $filter['function'][0];
					if($instance instanceof Baba_User_Meta) {
						$this->init($instance);
						break;
					}
				}
			}
		}
	}
	public function init($instance) {
		remove_action( 'admin_init', array( $instance, 'process_lock_action' ) );
		add_action( 'admin_init', array( $this, 'process_lock_action' ) );
		add_action( 'personal_options', [$this, 'edit_user'] );
		add_action( 'edit_user_profile_update', [$this, 'update_user']);
		add_action('manage_users_extra_tablenav', [$this,'dropdown'], 100);
		add_filter('pre_get_users', [$this,'pre_get_users']);
		if(is_network_admin()) {
			add_filter( 'wpmu_users_columns', [$instance, 'register_column_header'] );
			add_filter( 'bulk_actions-users-network', array( $instance, 'register_bulk_action' ) );
			add_action( 'admin_footer-users.php', [$this, 'network_dropdown']);
		}
	}
	public function edit_user($profile_user) {
        	if( ! current_user_can( 'create_users' ) ) return;
		if(get_current_user_id() == $profile_user->ID) return;
		$locked = get_user_meta( (int)$profile_user->ID, sanitize_key( 'baba_user_locked' ), true );
?>
						<tr class="user-lock-wrap">
							<th scope="row"><?php _e( 'Lock User Account', 'babatechs' ); ?></th>
							<td>
								<label for="user_lock"><input name="user_lock" type="checkbox" id="user_lock" value="yes" <?php checked( 'yes', $locked ); ?> />
								</label>
							</td>
						</tr>

<?php
	}
	public function update_user($user_id) {
        	if( ! current_user_can( 'create_users' ) ) return;
		if(get_current_user_id() == $user_id) return;
		if($_POST['user_lock'] == 'yes') $this->lock_user($user_id);
		else $this->unlock_user($user_id);
	}
    	public function process_lock_action(){
	  $request = is_network_admin() ? $_POST : $_GET;

	  if ( isset( $request['_wpnonce'] ) && ! empty( $request['_wpnonce'] ) && (strpos(wp_get_referer(), '/wp-admin/users.php' ) === 0 || strpos(wp_get_referer(), '/wp-admin/network/users.php' ) === 0)) {
            $action  = filter_input( is_network_admin() ? INPUT_POST : INPUT_GET, 'action', FILTER_SANITIZE_STRING );
            
            //  check the action is not supposed to catch
            if( 'lock' !== $action && 'unlock' !== $action ){
                return;
            }
			//  security check one
            if ( ! check_admin_referer( is_network_admin() ? 'bulk-users-network' : 'bulk-users' ) ) {
                return;
            }
            
            //  security check two
            if( ! current_user_can( 'create_users' ) ){
                return;
            }
            
            //  secure input for user ids
            $userids = [];
	    $users = is_network_admin() ? $request['allusers'] : $request['users'];
            if( isset( $users ) && is_array( $users ) && !empty( $users ) ){
                foreach( $users as $user_id ){
                    $userids[] = (int)$user_id;
                }
            }
            else{
                return;
            }
            
            //  Process lock request
            if( 'lock' === $action ){
                $current_user_id = get_current_user_id();
                foreach( $userids as $userid ){
                    if( $userid == $current_user_id ) continue;
		    $this->lock_user($userid);
		}
            }
            
            //  Process unlock request
            elseif( 'unlock' === $action ){
                foreach( $userids as $userid ){
		    $this->unlock_user($userid);
                }
            }
          }
    	}
	public function lock_user($userid) {
		update_user_meta( (int)$userid, sanitize_key( 'baba_user_locked' ), 'yes' );
		$sessions = WP_Session_Tokens::get_instance($userid);
		$sessions->destroy_all();
	}
	public function unlock_user($userid) {
//		update_user_meta( (int)$userid, sanitize_key( 'baba_user_locked' ), '' );
		delete_user_meta( (int)$userid, sanitize_key( 'baba_user_locked' ) );
	}
	public function dropdown($which = '') {
        	echo '<select onChange="window.location.href = this.value">';
        	echo '<option value="'.esc_url(remove_query_arg('user_lock')).'">'.__( 'Lock User Account', 'babatechs' ).'</option>';
        	echo '<option value="'.esc_url(add_query_arg('user_lock', 'yes')).'" '.selected("yes", $_GET['user_lock']).'>'.__( 'Locked', 'babatechs' ).'</option>';
        	echo '</select>';
	}
	function network_dropdown(){
		echo '<div id="extra_tablenav" style="display:none">';
		$this->dropdown();
		echo '</div>';
?><script>
	jQuery('.bulkactions').after(jQuery('#extra_tablenav').html());
</script><?php
	}
	public function pre_get_users($query) {
        	global $pagenow;
        	if (is_admin() && 'users.php' == $pagenow) {
           		if ($_GET['user_lock'] == 'yes') {
                		$meta_query = [['key' => sanitize_key( 'baba_user_locked' ),'value' => 'yes', 'compare' => '=']];
                		$query->set('meta_key', sanitize_key( 'baba_user_locked' ));
                		$query->set('meta_query', $meta_query);
            		}
        	}
	}
}
new fix_Baba_User_Meta;

