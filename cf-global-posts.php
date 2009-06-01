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
	/* Make domain a subdomain to example.com so there's 
	* 	no possible way to navigate to it from admin or
	* 	front-end */
	$domain = 'cf-global-posts.example.com';
	$path = '/';
	$site = cfgp_get_next_site_id();
	if (!domain_exists($domain, $path, $site)) {
		$new_blog_id = create_empty_blog( $domain, $path, 'CF Global Posts Blog', $site );

		/* Store the shadow blog's id for future reference */
		update_site_option('cfgp_blog_id', $new_blog_id);
		
		/* Make the blog private */
		update_blog_status( $new_blog_id, 'public', 0 );
	}
	else {
		error_log('domain does exists');
	}
}
register_activation_hook(CFGP_FILE, 'cfgp_install');


function cfgp_do_categories($cfgp_blog_id, $clone_id) {
	/* Get's the submitted post's categories, 
	* then pushes to shadow blog's clone post */

	/* Grab category names that the current post belongs to. */
	if (isset($_POST['post_category']) && is_array($_POST['post_category']) && count($_POST['post_category']) > 0) {
		/* Post has categories */
		$cur_cats = $_POST['post_category'];
	}
	else {
		/* Post doesn't have any categories, assign to 'Uncategorized' */
		$cur_cats = array( get_cat_ID('Uncategorized') );
	}
	
	/* We have id's, now get the names */
	foreach ($cur_cats as $cat) {
		$cur_cats_names[] = get_catname( $cat );		
	}
	
	/* Now go to the shadow blog and do the category business...*/
	switch_to_blog($cfgp_blog_id);
	
	/* This function creates the cats if they don't exist, and 
	* 	then assigns them to the post ID that's passed. */ 	
	wp_create_categories($cur_cats_names, $clone_id);

	restore_current_blog();
}
function cfgp_do_tags($cfgp_blog_id, $clone_id) {
	/* Add or remove tags as needed.  We aren't
	* 	doing checking, b/c WP does it for us */
	$tags = $_POST['tags_input'];
	
	switch_to_blog($cfgp_blog_id);
	wp_set_post_tags($clone_id, $tags);
	restore_current_blog();
}
function cfgp_push_all_post_meta($all_post_meta, $clone_id) {
	/* We should already be switched to blog!! */
	$excluded_values = array(
		'_edit_last',
		'_edit_lock',
		'_encloseme',
		'_pingme'
	);
	$excluded_values = apply_filters('cfgp_exluded_post_meta_values', $excluded_values);
	if (is_array($all_post_meta)) {
		foreach ($all_post_meta as $key => $value) {
			if (in_array($key, $excluded_values)) { 
				/* we don't need to update that key */
				continue; 
			}

			if (is_array($value) && count($value) > 1) {
				/* The original value was an array, so store it as such */
				update_post_meta($clone_id, $key, $value);
			}
			else {
				/* The original value wasn't an array, so store it as $value's first value */
				update_post_meta($clone_id, $key, $value[0]);
			}
		}
	}
}
function cfgp_do_post_meta($post_id, $cfgp_blog_id, $clone_id) {
	/* first add post_meta to the original 
	* 	post of the clone's post id */
	update_post_meta($post_id, '_cfgp_clone_id', $clone_id);
	
	
	/* Get all the post_meta for current post */
	$all_post_meta = get_post_custom($post_id);

	/* Now add all post_meta to clone post */
	switch_to_blog($cfgp_blog_id);
	cfgp_push_all_post_meta($all_post_meta, $clone_id);
	restore_current_blog();
}
function cfgp_save_post($post_id, $post) {
	global $wpdb;
	
	/* If it's a draft, get the heck out of dodge */
	if ($post->post_status == 'draft') { return; }
	
	/* This is a revision, not something that needs to get cloned */
	if ($post->post_status == 'inherit') { return; }

	do_action('cfgp_switch_to_site'); // If you're doing multiple sites, hook in here

	/* Get the shadow blog's id */
	$cfgp_blog_id = get_site_option('cfgp_blog_id');
	
	do_action('cfgp_restore_current_site'); // ...again, multiple sites, hook in here 
	
	/* Get the current blog's id */
	$current_blog_id = $wpdb->blogid;
	
	/* Grab the clone's id */
	$clone_post_id = get_post_meta($post_id, '_cfgp_clone_id', true);
	
	/* if no clone id, then we're inserting a new post*/
	($clone_post_id == '')? $inserting = true: $inserting = false;
	
	remove_action('save_post', 'cfgp_save_post'); // If you remove this the world will stop (it goes into an infinite loop if this isn't here...possibly a black hole will suck you inside)
	remove_action('publish_post', '_publish_post_hook', 5, 1); // This *does* require the '5', '1' parameters
	switch_to_blog($cfgp_blog_id);
	if ($inserting) {
		/* INSERTING NEW */
		/* This post has not yet been cloned,
		* 	time to insert the clone post into shadow blog */
		
		/* remove the original post_id so we can create the clone */
		unset($post->ID);

		$clone_id = wp_insert_post($post);
	}
	else {
		/* UPDATING */
		/* This will be updating the clone's post with the 
		* 	post_id from the original blog's post's post_meta */

		/* Change the post ID to the clone per the orginal's post_meta */
		$post->ID = $clone_post_id;

		$clone_id = wp_update_post($post);
	}
	restore_current_blog();		


	/****************
	* CATEGORY WORK *
	* **************/
	cfgp_do_categories($cfgp_blog_id, $clone_id);


	/***********
	* TAG WORK *
	***********/
	cfgp_do_tags($cfgp_blog_id, $clone_id);

	/*****************
	* POST META WORK *
	*****************/
	cfgp_do_post_meta($post_id, $cfgp_blog_id, $clone_id);

		
	/* put actions back */
	add_action('publish_post', '_publish_post_hook', 5, 1);
	add_action('save_post', 'cfgp_save_post', 10, 2);
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