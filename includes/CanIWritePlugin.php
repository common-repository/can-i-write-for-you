<?php

class CanIWritePlugin {
		
	var $table_records;			// il nome della tabella
	
	/**
	 * - Se sono state compiute delle azioni attraverso il plugin (accept/delete)
	 * - oppure se ci sono degli utenti in coda
	 * */
	var $pending_notification; 	// si attiva in dashboard_admin_init se ci sono state aggiunte;
	var $pending_users_result;	// per sapere se/quali utenti ci sono in attesa di approvazione
	
	function CanIWritePlugin() {
		global $wpdb;
		
	    $this->table_records = $wpdb->prefix . PLUGIN_PREFIX . 'records';
	}
	
	// -- WIDGET
	function widget_init() {
		register_widget('CanIWriteWidget');
	}
	
	function widget_install() {
		global $wpdb, $wp_rewrite;
		
		$query = 'CREATE TABLE IF NOT EXISTS ' . $this->table_records . ' (
			id INT NOT NULL AUTO_INCREMENT,
			date DATETIME,
			name VARCHAR(255),
			email VARCHAR(255),
			message TEXT,
			status VARCHAR(50),
			PRIMARY KEY(id)
		)';
		$query = $wpdb->prepare($query);
		$wpdb->query($query);
		
		$wp_rewrite->flush_rules();
	}
	function widget_uninstall() {
		global $wpdb;
		
		$query = 'DROP TABLE ' . $this->table_records;
		$query = $wpdb->prepare($query); 
		$wpdb->query($query);
	}
		
	// DASHBOARD NOTICES
	function dashboard_admin_notice() { // ovvero gli output successivi a dashboard_admin_init
		
		$result = $this->pending_users_result;
		$count = count($result);
		
		function doShow($context) { // funzione interna. Mostra la barra standard
			
			$result = $context->refresh_pending_users_result();
			$count = count($result);
			
			if (!$count) { return; }
			
			$output = '<div id=' . PLUGIN_PREFIX . '"admin_notice" class="updated fade">';
			$output .= '<p>';
			$output .= sprintf(_n('There is %d new person who want to write for you:', 'There are %d people who want to write for you:', $count, I18N_DOMAIN), $count) . ' ';
			
			foreach ($result as $iterator => $item) {
				if ($iterator) { $output .= ($iterator >= $count - 1) ? ' and ' : ', '; } 
				$output .= '<strong><span title="' . $item['email'] . '">' . $item['name'] . '</span></strong>';
			}
			
			$output .= '&nbsp;&nbsp;';
			$output .= '<a href="?add_author=all" class="button-secondary">';
			$output .= _n('Allow him', 'Allow them', $count, I18N_DOMAIN);
			$output .= '</a>';
			$output .= ' ' . __('or', I18N_DOMAIN);
			$output .= '<a href="' . $context->options_page_link() . '"> ';
			$output .= __('Display requester details', I18N_DOMAIN);
			$output .= '</a>';
			
			$output .= '</p></div>';
			echo $output;
		} // FINE doShow()
		
		switch ($this->pending_notification) {
			case 'add':
				 
				if (!$count) { // se non ci sono utenti aggiunti, applico show/default 
					doShow(&$this);
					break;
				}
				
				$output = '<div id=' . PLUGIN_PREFIX . '"admin_notice" class="updated fade">';
				$output .= '<p>';
				$output .= sprintf(_n('%d new author added:', '%d new author added:', $count, I18N_DOMAIN), $count) . ' ';
				foreach ($result as $iterator => $item) {
					if ($iterator) { $output .= ($iterator >= $count - 1) ? ' ' . __('and', I18N_DOMAIN) . ' ' : ', '; } 
					$output .= '<strong><span title="' . $item['email'] . '">' . $item['name'] . '</span></strong>';
				}
				$output .= '</p></div>';
				echo $output;
			
			break; // FINE add
			
			case 'delete':
				
				if (!$count) { // se non ci sono utenti aggiunti, applico show/default 
					doShow(&$this);
					break;
				}
				
				$output = '<div id=' . PLUGIN_PREFIX . '"admin_notice" class="updated fade">';
				$output .= '<p>';
				$output .= sprintf(_n('%d new author deleted:', '%d new author deleted:', $count, I18N_DOMAIN), $count) . ' ';
				foreach ($result as $iterator => $item) {
					if ($iterator) { $output .= ($iterator >= $count - 1) ? ' ' . __('and', I18N_DOMAIN) . ' ' : ', '; } 
					$output .= '<strong><span title="' . $item['email'] . '">' . $item['name'] . '</span></strong>';
				}
				$output .= '</p></div>';
				echo $output;
				
			break; // FINE delete
			
			case 'show': 
			default:
			
				doShow(&$this);
			
			break; // FINE show/default
		}
		
		// rinizializzo le variabili temporanee
		unset($this->pending_notification); // torna in modalità "show"
		unset($this->pending_users_result); // cancello il risultato della query;
	}
	
	function dashboard_admin_init() { 	// controllo se c'è stato input da parte del plugin (ad es. chiamate GET). 
										// Elaboro i dati, gli output sono in dashboard_admin_notice
		
		$result = $this->refresh_pending_users_result();
		
		if (!$result) { return; }
			
		// se sto aggiungendo gli autori tutti in un colpo: "Allow all";
		if (isset($_GET['add_author']) && $_GET['add_author'] == 'all') {
			
			$this->pending_notification = 'add';
			
			foreach ($result as $item) {
				// http://codex.wordpress.org/Function_Reference/wp_create_user
				// http://stackoverflow.com/questions/5417316/register-author-type-user-in-wordpress
				$uid = username_exists($item['name']);
				if (!$uid) {
					$this->user_add($item['id']);
					
				} else { 	// NOTA: e se esiste già un nome utente così?
							// RISPOSTA: impossibile: il filtraggio avviene già in fase di input.
				}
			}
		}
	}
	
	// funzione wrapper, ricarica la variabile pending_users_result
	function refresh_pending_users_result() {
		global $wpdb;
		
		$query = 'SELECT id, name, email
		FROM ' . $this->table_records . '
		WHERE status LIKE "pending"';
		
		// NOTA: il formato come array lo utilizzo per adattarmi al comportamento della tabella, che produce array.
		// Verificare se funziona. 
		$result = $this->pending_users_result = $wpdb->get_results($wpdb->prepare($query), ARRAY_A);
		return $result;
	}
	
	/**
	 * Aggiungo l'utente al db ed invio la notifica via email.
	 * TODO (da verificare): quando aggiungo l'utente tengo da parte il record, oppure lo elimino? 
	 */
	function user_add($user_id) {
		global $wpdb;
								
		$user_id_pattern = '/^\d+$/';
		if (!preg_match($user_id_pattern, $user_id)) {
			wp_die('ERRORE! lo user_id può essere solo un numero. E\' stato ricevuto ' . $user_id);
		}
		
		// estraggo da db la riga corrispondente all'utente
		$query = 'SELECT id, name, email
		FROM ' . $this->table_records . '
		WHERE id LIKE ' . $user_id;
		$result = $wpdb->get_results($wpdb->prepare($query));
		
		foreach ($result as $item) { // uso un foreach, ma è per un elemento solo.
			
			$random_password = wp_generate_password($length = 12, $include_standard_special_chars=false);
			$uid = wp_create_user($item->name, $random_password, $item->email);
			wp_update_user(array('ID' => $uid, 'role' => 'author'));
			
			/* QUERY A: dopo aver aggiunto l'utente alla tabella ufficiale lo elimino dalla tabella del plugin */
			/* $query = 'DELETE FROM ' . $this->table_records . '
			WHERE id LIKE ' . $item->id . '
			'; */
			
			/* QUERY B: l'utente non viene eliminato dalla tabella del plugin, ma cambio lo status in "added" */
			$query = 'UPDATE ' . $this->table_records . '
			SET status = "added"
			WHERE id LIKE ' . $item->id . '
			';
			$wpdb->query($wpdb->prepare($query));
			
			// a questo punto invio la notifica email all'utente per il primo accesso.
			// http://codex.wordpress.org/Function_Reference/get_bloginfo
			$blog_name = get_bloginfo('name');
			$blog_url = home_url();
			
			$login_fastlink = get_bloginfo('wpurl') . '/wp-admin/';
					
			$wp_mail_headers = "MIME-Version: 1.0\n".
			"Content-type: text/html; charset=utf-8\n".
			
			$wp_mail_subject = sprintf(__('%s: Your request has been accepted', I18N_DOMAIN), $blog_name);
			
			$wp_mail_output = sprintf(__('Hi %s!', I18N_DOMAIN), $item->name);
			$wp_mail_output .= '<br/><br/>';
			$wp_mail_output .= sprintf(__('Now you can <a href="%s">login</a> to %s.', I18N_DOMAIN), $login_fastlink, $blog_name);
			$wp_mail_output .= '<br/>';
			$wp_mail_output .= __("Here's your account data:", I18N_DOMAIN);
			$wp_mail_output .= '<br/>';
			$wp_mail_output .= sprintf(__('Username: %s', I18N_DOMAIN), $item->name);
			$wp_mail_output .= '<br/>';
			$wp_mail_output .= sprintf(__('Password: %s', I18N_DOMAIN), $random_password);
			$wp_mail_output .= '<br/>';
			$wp_mail_output .= __("You can replace this automatic password with an easier one", I18N_DOMAIN);
			$wp_mail_output .= '<br/><br/>';
			$wp_mail_output .= __("Have fun!", I18N_DOMAIN);
			
			wp_mail(
				$item->email,
				$wp_mail_subject,
				$wp_mail_output,
				$wp_mail_headers
			);
			
			
			
		} // endforeach;
		
		// Qui rifaccio la query per recuperare gli utenti ancora avanzati, da stampare nella notification bar;
		// NOTA: questo punto, una volta eliminata la notifica dalla pagina delle opzioni, probabilmente non servirà più
		// $this->refresh_pending_users_result();
	}
	
	/**
	 * Respingo la richiesta, sposto l'utente richiedente in blacklist.
	 * NEXTDEV: la blacklist serve per impedire nuove richieste simili, e per fare un "cestino" da cui ripescare le richieste eliminate.
	 * */
 	function user_reject($user_id) {
 		global $wpdb;
 			 		
 		$user_id_pattern = '/^\d+$/';
		if (!preg_match($user_id_pattern, $user_id)) {
			wp_die('ERRORE! lo user_id può essere solo un numero. E\' stato ricevuto ' . $user_id);
		}
		
		$query = 'UPDATE ' . $this->table_records . '
		SET status = "rejected"
		WHERE id LIKE ' . $user_id . '
		';
		$wpdb->query($wpdb->prepare($query));
		
		// $this->refresh_pending_users_result();
 	}
	
	// http://codex.wordpress.org/Adding_Administration_Menus
	function options_menu() {
		$parent_slug = 'users.php';
		$page_title = __('Can I write for you?', I18N_DOMAIN);
		$menu_title = __('Can I write for you?', I18N_DOMAIN);
		$capability = 'add_users';
		$menu_slug = basename(__FILE__);
		$function = array(&$this, 'options_page');
		
		// add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function);
		$plugin_page = add_users_page( $page_title, $menu_title, $capability, $menu_slug, $function);
		add_action( 'admin_head-'. $plugin_page, array(&$this, 'options_page_head') );
		
		// http://www.whypad.com/posts/wordpress-add-settings-link-to-plugins-page/785/
	}
	
	function options_page() {
		global $wpdb;
		
		?>
		<div class="wrap">
			<h2><?php _e('Can I write for you?', I18N_DOMAIN); ?></h2>
			<p><?php _e('Here you can view and manage users awaiting requests.', I18N_DOMAIN); ?></p>
			<?php 
			
				// list_data
				
			?>
			<form action="" method="GET">
				
				<?php 
					// http://return-true.com/2009/02/creating-an-options-admin-page-for-your-wordpress-plugin/
					// http://net.tutsplus.com/tutorials/wordpress/how-to-integrate-an-options-page-into-your-wordpress-theme/ 
				?>
				
				<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
				<?php 
					$this->listTable->display();
				?>
				
			</form>
		</div>
		<?php
	}
	
	function options_page_link() { // restituisce l'url diretto della pagina delle opzioni
		$link = 'users.php?page=' . basename(__FILE__);
		return $link;
	} 
	
	var $listTable;
	function options_page_head() {
		global $wpdb;
		
		$query = 'SELECT id, date, name, email, message 
		FROM ' . $this->table_records . '
		WHERE status LIKE "pending"';
		
		$result = $wpdb->get_results($wpdb->prepare($query), ARRAY_A);
		
		// http://codex.wordpress.org/Class_Reference/WP_List_Table
		$this->listTable = new CanIWriteTable();
		$this->listTable->add_data($result);	// NOTA: i dati non vengono pescati dall'interno della tabella 
												// come suggerisce il plugin d'esempio, ma vengono passati da qui.
		$this->listTable->prepare_items();
	}
	
	// Internationalization
	// http://codex.wordpress.org/Writing_a_Plugin#Internationalizing_Your_Plugin
	// http://codex.wordpress.org/I18n_for_WordPress_Developers#I18n_for_theme_and_plugin_developers
	function i18n_init() {
		$plugin_dir = basename(dirname(__FILE__)) . '/lang';
		load_plugin_textdomain( I18N_DOMAIN, false, $plugin_dir );
	}
	
}

?>