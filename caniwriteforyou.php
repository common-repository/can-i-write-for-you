<?php
/*
Plugin Name: Can I write for you?
Plugin URI: https://github.com/freshdevelop/wp-caniwriteforyou
Description: Allows users to put themselves to become blog authors. Widget visualization and email notifications added.
Version: 1.0
Author: Luca Fresc
Author URI: https://github.com/freshdevelop
License: GPL2

Copyright 2012  Luca Fresc  (email : freshdevelop@gmail.com)
	
	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as 
	published by the Free Software Foundation.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// http://www.webdesigncrate.com/tutorial/wordpress-plugin-template-shortcodes-toolbox
if(!defined('WP_PLUGIN_PATH'))
	define( 'WP_PLUGIN_PATH', plugin_dir_path(__FILE__) );

if(!defined('PLUGIN_PREFIX'))
	define('PLUGIN_PREFIX', 'ciwfy_');
	
if(!defined('I18N_DOMAIN'))
	define('I18N_DOMAIN', 'caniwriteforyou');

if (!class_exists('CanIWritePlugin')) {
	$class_url = WP_PLUGIN_PATH . '/includes/CanIWritePlugin.php';
	include($class_url);
} // END class CanIWritePlugin

// http://codex.wordpress.org/Class_Reference/WP_List_Table
if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
    
    $class_url = WP_PLUGIN_PATH . '/includes/CanIWriteTable.php';
	include($class_url);
} // END class CanIWriteTable 

// http://codex.wordpress.org/Widgets_API
// http://wpengineer.com/1023/wordpress-built-a-widget/
// http://www.doitwithwp.com/how-to-create-wordpress-widget-step-by-step/
// http://wp.tutsplus.com/tutorials/creative-coding/building-custom-wordpress-widgets/
if (!class_exists('CanIWriteWidget')) {
	$class_url = WP_PLUGIN_PATH . '/includes/CanIWriteWidget.php';
	include($class_url);
} // END class CanIWriteWidget

// Class instantiation
if (class_exists('CanIWritePlugin')) {
	$fd_caniwrite = new CanIWritePlugin();
}

// Actions & Filters
if (isset($fd_caniwrite)) {
	
	// Hooks
	register_activation_hook(__FILE__, array(&$fd_caniwrite, 'widget_install'));
	register_deactivation_hook(__FILE__, array(&$fd_caniwrite, 'widget_uninstall'));
	
	// Actions
 	add_action('widgets_init', array(&$fd_caniwrite, 'widget_init'));
 	add_action('wp_print_styles', array('CanIWriteWidget', 'print_styles'));	// frontend css
 	add_action('widgets_init', array('CanIWriteWidget', 'print_admin_scripts'));	// backend js
 	
	add_action('plugins_loaded', array(&$fd_caniwrite, 'i18n_init'));

 	if (is_admin()) { 
 		add_action('admin_notices', array(&$fd_caniwrite, 'dashboard_admin_notice')); // dashboard notice.
 		add_action('admin_init', array(&$fd_caniwrite, 'dashboard_admin_init'));
	}
	add_action( 'admin_menu', array(&$fd_caniwrite, 'options_menu'));
	
	// Filters
    
}
?>