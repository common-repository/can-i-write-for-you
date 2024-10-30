<?php

class CanIWriteWidget extends WP_Widget {
	
	function CanIWriteWidget() {
		// Instantiate the parent object
		parent::__construct(
			'can_i_write', 
			'Can I Write For You?',
			array(
				'classname' => PLUGIN_PREFIX . 'widget',
				'description' => __( 'Allows visitors to request author subscription', I18N_DOMAIN ),
			)
			);
	}
	
	function print_styles() {
		wp_enqueue_style(PLUGIN_PREFIX . 'widget-style', plugins_url('css/ciwfy_style.css', dirname(__FILE__)));
	}
	// http://stackoverflow.com/questions/4315171/loading-custom-javascript-files-in-the-wordpress-widgets-admin-panel
	// http://codex.wordpress.org/Function_Reference/plugins_url
	function print_admin_scripts() {
		wp_enqueue_script(PLUGIN_PREFIX . 'widget-admin_script', plugins_url('js/ciwfy_widget_script.js', dirname(__FILE__)), array('jquery'));
	}

	function widget($args, $instance) {
		global $wpdb, $fd_caniwrite;
		
		extract( $args );
		
		echo $before_widget;
		
		$title = empty($instance['title']) ? ' ' : apply_filters('widget_title', $instance['title']);
		if ( !empty( $title ) ) { echo $before_title . $title . $after_title; };
		
		$output = '';
		
		if (isset($_POST[PLUGIN_PREFIX . 'send']) && $_POST[PLUGIN_PREFIX . 'send']) {
			
			$input_name = trim($_POST[PLUGIN_PREFIX . 'name']);
			$regex_string = '/^[\w. ]+$/';
			
			$input_email = trim($_POST[PLUGIN_PREFIX . 'email']);
			$regex_email = '/^[\w.]+@\w+.[\w]{1,5}$/';
			
			$input_msg = ($instance['msg_enable'] == 'on') ? trim($_POST[PLUGIN_PREFIX . 'msg']) : null;
			
			$valid_name = false;
			$valid_email = false;
			$valid_msg = true;
			
			$existing_users = get_users();
			
			if (preg_match($regex_string, $input_name)) {
				
				$query = 'SELECT COUNT(*) FROM ' . $fd_caniwrite->table_records . ' WHERE name LIKE "' . $input_name . '"';
				$exists = $wpdb->get_var($query);
				
				foreach ($existing_users as $existing_user) {
					if ($existing_user->user_login == $input_name) {
						$exists = true;
						break;
					}
				} // endforeach;
				
				if (!$exists) {
					$valid_name = true;
				} else {
					$output = '<div class="notification error">' . __('Username already in use', I18N_DOMAIN) . '</div>';
				}
				
			} else {
				$output = '<div class="notification error">' . __('Name is not valid. Only letters and spaces allowed.', I18N_DOMAIN) . '</div>';
			}
			
			if ($valid_name) {
				if (preg_match($regex_email, $input_email)) {
					
					$query = 'SELECT COUNT(*) FROM ' . $fd_caniwrite->table_records . ' WHERE email LIKE "' . $input_email . '"';
					$exists = $wpdb->get_var($query);
					
					foreach ($existing_users as $existing_user) {
						if ($existing_user->user_email == $input_email) {
							$exists = true;
							break;
						}
					} // endforeach;
					
					if (!$exists) {
						$valid_email = true;
					} else {
						$output = '<div class="notification error">' . __('Email already in use', I18N_DOMAIN) . '</div>';
					}
					
				} else {
					$output = '<div class="notification error">' . __('Email is not valid. Check it, please.', I18N_DOMAIN) . '</div>';
				}
			}
			
			if ($valid_name && $valid_email) {
				if ($instance['msg_enable'] == 'on') {
					if (isset($instance['msg_required']) && $instance['msg_required'] == 'on') {
						$valid_msg = !empty($input_msg); // questo diventa false se il messaggio è richiesto, ma viene lasciato vuoto.
					}
					if (!$valid_msg) {
						$output = '<div class="notification error">' . __('Message is required', I18N_DOMAIN) . '</div>';
					}
				}
			}
			
			if ($valid_name && $valid_email && $valid_msg) {	// submit to db
				// controllo se nella tabella esiste già un record simile
				$query = 'SELECT COUNT(*) FROM ' . $fd_caniwrite->table_records . '
					WHERE name LIKE "' . $input_name . '" AND email LIKE "' . $input_email . '"';
				$query = $wpdb->prepare($query);
				$matches = $wpdb->get_var($query);
					
				if (!$matches) {
					$query = 'INSERT INTO ' . $fd_caniwrite->table_records . '
						(date, name, email, message, status)
						VALUES (
							FROM_UNIXTIME(' . time() . '),
							"' . $input_name . '",
							"' . $input_email . '",
							"' . $input_msg . '",
							"pending"
						)
					';
					$query = $wpdb->prepare($query);
					$wpdb->query($query);
					
					$output = '<div class="notification ok">' . __('Your request has been sent. Thank you :)', I18N_DOMAIN) . '</div>';
					
					// alla fine di tutto, mando l'email agli admin con la notifica
					
					$admins = get_users(array('role' => 'administrator'));
					
					$admin_list = array();
					foreach ($admins as $admin) { $admin_list[] = $admin->user_email; }
					
					$login_fastlink = get_bloginfo('wpurl') . '/wp-admin/' . $fd_caniwrite->options_page_link();
					
					$wp_mail_headers = "MIME-Version: 1.0\n".
					"Content-type: text/html; charset=utf-8\n".
					
					$wp_mail_subject = __('A new user wants to be an author', I18N_DOMAIN);
					
					$wp_mail_output = sprintf(__('Name: %s', I18N_DOMAIN), $input_name);
					$wp_mail_output .= '<br/>';
					$wp_mail_output .= sprintf(__('Email: <a href="%s">%s</a>', I18N_DOMAIN), $input_email, $input_email);
					$wp_mail_output .= '<br/>';
					$wp_mail_output .= sprintf(__('Notes: %s', I18N_DOMAIN), $input_msg);
					$wp_mail_output .= '<br/>';
					$wp_mail_output .= sprintf(__('Please <a href="%s">login into blog</a> to confirm or reject this request.', I18N_DOMAIN), $login_fastlink);
					
					wp_mail(
						$admin_list,
						$wp_mail_subject,
						$wp_mail_output,
						$wp_mail_headers
					);
				} else {
					$output = '<div class="notification warning">Your request has already been submitted. Please wait until approval.</div>';
				}
			
			}
		}
		
		?>
		
		<?php if (!empty($output)) echo $output; ?>
		<form method="post" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label for="<?php echo PLUGIN_PREFIX ?>name"><?php _e('Name', I18N_DOMAIN); ?>: *</label>
			<input 
				type="text" class="field" 
				id="<?php echo PLUGIN_PREFIX ?>name" 
				name="<?php echo PLUGIN_PREFIX ?>name" 
				<?php if (isset($valid_name) && $valid_name) { echo 'value="' . $input_name . '" '; } ?> 
			/>
			<label for="<?php echo PLUGIN_PREFIX ?>email"><?php _e('Email', I18N_DOMAIN); ?>: *</label>
			<input 
				type="text" class="field" 
				id="<?php echo PLUGIN_PREFIX ?>email" 
				name="<?php echo PLUGIN_PREFIX ?>email" 
				<?php if (isset($valid_email) && $valid_email) { echo 'value="' . $input_email . '" '; } ?> 
			/>
			<?php if ($instance['msg_enable'] == 'on'): ?>
				<label for="<?php echo PLUGIN_PREFIX ?>msg">
					<?php _e('Message', I18N_DOMAIN); ?><?php if ($instance['msg_required'] == 'on') { echo ' <em>(' . __('required', I18N_DOMAIN) . ')</em>'; } ?>:
				</label>
				<textarea id="<?php echo PLUGIN_PREFIX ?>msg" name="<?php echo PLUGIN_PREFIX ?>msg"></textarea>
			<?php endif; ?>
			<input type="hidden" name="<?php echo PLUGIN_PREFIX ?>send" value="1"  />
			<input type="submit" class="submit" value="<?php _e('Send request', I18N_DOMAIN); ?>" />
		</form>
		<?php
		echo $after_widget;
	}

	function update($new_instance, $old_instance) {
		
		// Save widget options
		// http://www.wptavern.com/forum/plugins-hacks/1141-widget-options-using-checkboxes.html
		
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['msg_enable'] = $new_instance['msg_enable'];
		$instance['msg_required'] = $new_instance['msg_required'];
		
		return $instance;
	}

	function form($instance) {
		// Output admin widget options form
		
		$instance = wp_parse_args( (array) $instance, array( 'title' => '') );
		$title = strip_tags($instance['title']);
		
		if ( isset( $instance[ 'title' ] ) ) {
			$title = $instance[ 'title' ];
		}
		else {
			$title = __( 'Can I write for you?', 'text_domain' );
		}
		?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>">
				<?php _e('Title', I18N_DOMAIN); ?>:
				<input type="text" class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo esc_attr($title); ?>" />
			</label>
		</p>
		<p>
			<div class="row <?php echo PLUGIN_PREFIX ?>msg_enable">
				<input type="checkbox"
					class="checkbox"
					id="<?php echo $this->get_field_id( 'msg_enable' ); ?>"
					name="<?php echo $this->get_field_name( 'msg_enable' ); ?>"
					<?php if (isset($instance['msg_enable']) && $instance['msg_enable'] == 'on') { echo ' checked="checked"'; } ?>
				/>
				<label for="<?php echo $this->get_field_id( 'msg_enable' ); ?>"><?php _e('Enable user message', I18N_DOMAIN); ?></label>
			</div>
			<div class="row <?php echo PLUGIN_PREFIX ?>msg_required">
				<input type="checkbox"
					class="checkbox" 
					id="<?php echo $this->get_field_id( 'msg_required' ); ?>"
					name="<?php echo $this->get_field_name( 'msg_required' ); ?>"
					<?php if (isset($instance['msg_required']) && $instance['msg_required'] == 'on') { echo ' checked="checked"'; } ?>
				/>
				<label for="<?php echo $this->get_field_id( 'msg_required' ); ?>"><?php _e('User message is required', I18N_DOMAIN); ?></label>
			</div>
		</p>
		
		<?php
		
	}
}

?>