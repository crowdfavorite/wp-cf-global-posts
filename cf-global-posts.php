<?php
/*
Plugin Name: CF Global Posts 
Plugin URI:  
Description: Generates a 'shadow blog' where posts mu-install-wide are conglomorated into one posts table for each data compilation and retrieval 
Version: 0.1 
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/

// ini_set('display_errors', '1'); ini_set('error_reporting', E_ALL);

if (!defined('PLUGINDIR')) {
	define('PLUGINDIR','wp-content/plugins');
}

if (is_file(trailingslashit(ABSPATH.PLUGINDIR).basename(__FILE__))) {
	define('CFGP_FILE', trailingslashit(ABSPATH.PLUGINDIR).basename(__FILE__));
}
else if (is_file(dirname(__FILE__).'/'.basename(__FILE__))) {
	define('CFGP_FILE', dirname(__FILE__).'/'.basename(__FILE__));
}






function cfgp_get_next_site_id() {
	/* Grab the next open site id */
	global $wpdb;
	$row = $wpdb->get_row("SHOW TABLE STATUS LIKE '".$wpdb->site."' ");
	return $row->Auto_increment;
}
function cfgp_install() {
	// error_log('inside the install function');
	/* Make domain a subdomain to example.com so there's 
	* 	no possible way to navigate to it from admin or
	* 	front-end */
	$domain = 'cf-global-posts.example.com';
	$path = '/';
	$site = cfgp_get_next_site_id();
	// error_log('checking if domain exists');
	if (!domain_exists($domain, $path, $site)) {
		// error_log('domain does not exists, making blog now');
		$new_blog_id = create_empty_blog( $domain, $path, 'CF Global Posts Blog', $site );
		update_site_option('cfgp_blog_id', $new_blog_id);
	}
	else {
		error_log('domain does exists');
	}
}
register_activation_hook(CFGP_FILE, 'cfgp_install');



// global $wpdb;
// echo '<pre>';
// print_r($wpdb);
// echo '</pre>';

function cfgp_save_post($post_id, $post) {
	error_log('in the save post function now');
	global $wpdb;
	
	
	/* If it's a draft, get the heck out of dodge */
	if ($post->post_status == 'draft') { error_log('ack, we\'re a draft'); return; }
	
	/* This is a revision, not something that needs to get cloned */
	if ($post->post_status == 'inherit') { error_log('Shoot, we\'re a revision'); return; }
	
	/* Get the shadow blog's id */
	$cfgp_blog_id = get_site_option('cfgp_blog_id');

	/* Get the current blog's id */
	$current_blog_id = $wpdb->blogid;
	
	/* Grab the shadow blog's post's clone id */
	$clone_post_id = get_post_meta($post_id, '_cfgp_clone_id', true);
	
	if ( $clone_post_id == '') {
		error_log('Woooo!  We\'re inserting a new one!');
		/* INSERTING NEW */
		/* This post has not yet been cloned,
		* 	time to insert the clone post into shadow blog */
		
		/* remove the original post_id so we can create the clone */
		unset($post->ID);
		
		remove_action('save_post', 'cfgp_save_post'); // If you remove this the world will stop

		switch_to_blog($cfgp_blog_id);
		$clone_id = wp_insert_post($post);
		ob_start();
		var_dump('Clone ID:'.$clone_id);
		error_log(ob_get_clean()); 
		restore_current_blog();

		add_action('save_post', 'cfgp_save_post', 10, 2);
		
		/* upon save, go back to original blog and add post_meta of 
		* 	the clone's post id */
		update_post_meta($post_id, '_cfgp_clone_id', $clone_id);
	}
	else {
		error_log('Alrighty we\'re going to be updating');
		/* UPDATING */
		/* This will be updating the clone's post with the 
		* 	post_id from the original blog's post's post_meta */
		error_log('Clone ID (pre update): '.$clone_post_id);
		/* Change the post ID to the clone per the orginal's post_meta */
		$post->ID = $clone_post_id;
		
		remove_action('save_post', 'cfgp_save_post'); // If you remove this the world will stop

		switch_to_blog($cfgp_blog_id);
		$clone_id = wp_update_post($post);
		ob_start();
		var_dump('Clone\'s update ID:'.$clone_id);
		error_log(ob_get_clean()); 
		restore_current_blog();

		add_action('save_post', 'cfgp_save_post', 10, 2);

	}

	


}
add_action('save_post', 'cfgp_save_post', 10, 2);




















function cfgp_init() {
// TODO
}
add_action('init', 'cfgp_init');

function cfgp_request_handler() {
	if (!empty($_GET['cf_action'])) {
		switch ($_GET['cf_action']) {

		}
	}
	if (!empty($_POST['cf_action'])) {
		switch ($_POST['cf_action']) {

			case 'cfgp_update_settings':
				cfgp_save_settings();
				wp_redirect(trailingslashit(get_bloginfo('wpurl')).'wp-admin/options-general.php?page='.basename(__FILE__).'&updated=true');
				die();
				break;
		}
	}
}
add_action('init', 'cfgp_request_handler');


wp_enqueue_script('jquery');





function cfgp_save_comment($comment_id) {
// TODO
}
add_action('comment_post', 'cfgp_save_comment');


/*
$example_settings = array(
	'key' => array(
		'type' => 'int',
		'label' => 'Label',
		'default' => 5,
		'help' => 'Some help text here',
	),
	'key' => array(
		'type' => 'select',
		'label' => 'Label',
		'default' => 'val',
		'help' => 'Some help text here',
		'options' => array(
			'value' => 'Display'
		),
	),
);
*/
$cfgp_settings = array(
	'cfgp_' => array(
		'type' => 'string',
		'label' => '',
		'default' => '',
		'help' => '',
	),
	'cfgp_' => array(
		'type' => 'int',
		'label' => '',
		'default' => 5,
		'help' => '',
	),
	'cfgp_' => array(
		'type' => 'select',
		'label' => '',
		'default' => '',
		'help' => '',
		'options' => array(
			'' => ''
		),
	),
	'cfgp_cat' => array(
		'type' => 'select',
		'label' => 'Category:',
		'default' => '',
		'help' => '',
		'options' => array(),
	),

);

function cfgp_setting($option) {
	$value = get_option($option);
	if (empty($value)) {
		global $cfgp_settings;
		$value = $cfgp_settings[$option]['default'];
	}
	return $value;
}

function cfgp_admin_menu() {
	if (current_user_can('manage_options')) {
		add_options_page(
			__('CF Global Posts Settings', '')
			, __('CF Global Posts', '')
			, 10
			, basename(__FILE__)
			, 'cfgp_settings_form'
		);
	}
}
add_action('admin_menu', 'cfgp_admin_menu');

function cfgp_plugin_action_links($links, $file) {
	$plugin_file = basename(__FILE__);
	if (basename($file) == $plugin_file) {
		$settings_link = '<a href="options-general.php?page='.$plugin_file.'">'.__('Settings', '').'</a>';
		array_unshift($links, $settings_link);
	}
	return $links;
}
add_filter('plugin_action_links', 'cfgp_plugin_action_links', 10, 2);

if (!function_exists('cf_settings_field')) {
	function cf_settings_field($key, $config) {
		$option = get_option($key);
		if (empty($option) && !empty($config['default'])) {
			$option = $config['default'];
		}
		$label = '<label for="'.$key.'">'.$config['label'].'</label>';
		$help = '<span class="help">'.$config['help'].'</span>';
		switch ($config['type']) {
			case 'select':
				$output = $label.'<select name="'.$key.'" id="'.$key.'">';
				foreach ($config['options'] as $val => $display) {
					$option == $val ? $sel = ' selected="selected"' : $sel = '';
					$output .= '<option value="'.$val.'"'.$sel.'>'.htmlspecialchars($display).'</option>';
				}
				$output .= '</select>'.$help;
				break;
			case 'textarea':
				$output = $label.'<textarea name="'.$key.'" id="'.$key.'">'.htmlspecialchars($option).'</textarea>'.$help;
				break;
			case 'string':
			case 'int':
			default:
				$output = $label.'<input name="'.$key.'" id="'.$key.'" value="'.htmlspecialchars($option).'" />'.$help;
				break;
		}
		return '<div class="option">'.$output.'<div class="clear"></div></div>';
	}
}

function cfgp_settings_form() {
	global $cfgp_settings;


	$cat_options = array();
	$categories = get_categories('hide_empty=0');
	foreach ($categories as $category) {
		$cat_options[$category->term_id] = htmlspecialchars($category->name);
	}
	$cfgp_settings['cfgp_cat']['options'] = $cat_options;


	print('
<div class="wrap">
	<h2>'.__('CF Global Posts Settings', '').'</h2>
	<form id="cfgp_settings_form" name="cfgp_settings_form" action="'.get_bloginfo('wpurl').'/wp-admin/options-general.php" method="post">
		<input type="hidden" name="cf_action" value="cfgp_update_settings" />
		<fieldset class="options">
	');
	foreach ($cfgp_settings as $key => $config) {
		echo cf_settings_field($key, $config);
	}
	print('
		</fieldset>
		<p class="submit">
			<input type="submit" name="submit" value="'.__('Save Settings', '').'" />
		</p>
	</form>
</div>
	');
}

function cfgp_save_settings() {
	if (!current_user_can('manage_options')) {
		return;
	}
	global $cfgp_settings;
	foreach ($cfgp_settings as $key => $option) {
		$value = '';
		switch ($option['type']) {
			case 'int':
				$value = intval($_POST[$key]);
				break;
			case 'select':
				$test = stripslashes($_POST[$key]);
				if (isset($option['options'][$test])) {
					$value = $test;
				}
				break;
			case 'string':
			case 'textarea':
			default:
				$value = stripslashes($_POST[$key]);
				break;
		}
		update_option($key, $value);
	}
}

//a:21:{s:11:"plugin_name";s:15:"CF Global Posts";s:10:"plugin_uri";N;s:18:"plugin_description";s:132:"Generates a 'shadow blog' where posts mu-install-wide are conglomorated into one posts table for each data compilation and retrieval";s:14:"plugin_version";s:3:"0.1";s:6:"prefix";s:4:"cfgp";s:12:"localization";N;s:14:"settings_title";s:24:"CF Global Posts Settings";s:13:"settings_link";s:15:"CF Global Posts";s:4:"init";s:1:"1";s:7:"install";s:1:"1";s:9:"post_edit";s:1:"1";s:12:"comment_edit";s:1:"1";s:6:"jquery";s:1:"1";s:6:"wp_css";b:0;s:5:"wp_js";b:0;s:9:"admin_css";b:0;s:8:"admin_js";b:0;s:15:"request_handler";s:1:"1";s:6:"snoopy";b:0;s:11:"setting_cat";s:1:"1";s:14:"setting_author";b:0;}

?>