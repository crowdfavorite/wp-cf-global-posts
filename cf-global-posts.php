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
	define('CFGB_FILE', trailingslashit(ABSPATH.PLUGINDIR).basename(__FILE__));
}
else if (is_file(trailingslashit(ABSPATH.PLUGINDIR).dirname(__FILE__).'/'.basename(__FILE__))) {
	define('CFGB_FILE', trailingslashit(ABSPATH.PLUGINDIR).dirname(__FILE__).'/'.basename(__FILE__));
}

register_activation_hook(CFGB_FILE, 'cfgb_install');

function cfgb_install() {
// TODO
}


function cfgb_init() {
// TODO
}
add_action('init', 'cfgb_init');


function cfgb_request_handler() {
	if (!empty($_GET['cf_action'])) {
		switch ($_GET['cf_action']) {

		}
	}
	if (!empty($_POST['cf_action'])) {
		switch ($_POST['cf_action']) {

			case 'cfgb_update_settings':
				cfgb_save_settings();
				wp_redirect(trailingslashit(get_bloginfo('wpurl')).'wp-admin/options-general.php?page='.basename(__FILE__).'&updated=true');
				die();
				break;
		}
	}
}
add_action('init', 'cfgb_request_handler');


wp_enqueue_script('jquery');


function cfgb_save_post($post_id, $post) {
// TODO
}
add_action('save_post', 'cfgb_save_post');


function cfgb_save_comment($comment_id) {
// TODO
}
add_action('comment_post', 'cfgb_save_comment');


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
$cfgb_settings = array(
	'cfgb_' => array(
		'type' => 'string',
		'label' => '',
		'default' => '',
		'help' => '',
	),
	'cfgb_' => array(
		'type' => 'int',
		'label' => '',
		'default' => 5,
		'help' => '',
	),
	'cfgb_' => array(
		'type' => 'select',
		'label' => '',
		'default' => '',
		'help' => '',
		'options' => array(
			'' => ''
		),
	),
	'cfgb_cat' => array(
		'type' => 'select',
		'label' => 'Category:',
		'default' => '',
		'help' => '',
		'options' => array(),
	),

);

function cfgb_setting($option) {
	$value = get_option($option);
	if (empty($value)) {
		global $cfgb_settings;
		$value = $cfgb_settings[$option]['default'];
	}
	return $value;
}

function cfgb_admin_menu() {
	if (current_user_can('manage_options')) {
		add_options_page(
			__('CF Global Posts Settings', '')
			, __('CF Global Posts', '')
			, 10
			, basename(__FILE__)
			, 'cfgb_settings_form'
		);
	}
}
add_action('admin_menu', 'cfgb_admin_menu');

function cfgb_plugin_action_links($links, $file) {
	$plugin_file = basename(__FILE__);
	if (basename($file) == $plugin_file) {
		$settings_link = '<a href="options-general.php?page='.$plugin_file.'">'.__('Settings', '').'</a>';
		array_unshift($links, $settings_link);
	}
	return $links;
}
add_filter('plugin_action_links', 'cfgb_plugin_action_links', 10, 2);

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

function cfgb_settings_form() {
	global $cfgb_settings;


	$cat_options = array();
	$categories = get_categories('hide_empty=0');
	foreach ($categories as $category) {
		$cat_options[$category->term_id] = htmlspecialchars($category->name);
	}
	$cfgb_settings['cfgb_cat']['options'] = $cat_options;


	print('
<div class="wrap">
	<h2>'.__('CF Global Posts Settings', '').'</h2>
	<form id="cfgb_settings_form" name="cfgb_settings_form" action="'.get_bloginfo('wpurl').'/wp-admin/options-general.php" method="post">
		<input type="hidden" name="cf_action" value="cfgb_update_settings" />
		<fieldset class="options">
	');
	foreach ($cfgb_settings as $key => $config) {
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

function cfgb_save_settings() {
	if (!current_user_can('manage_options')) {
		return;
	}
	global $cfgb_settings;
	foreach ($cfgb_settings as $key => $option) {
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

//a:21:{s:11:"plugin_name";s:15:"CF Global Posts";s:10:"plugin_uri";N;s:18:"plugin_description";s:132:"Generates a 'shadow blog' where posts mu-install-wide are conglomorated into one posts table for each data compilation and retrieval";s:14:"plugin_version";s:3:"0.1";s:6:"prefix";s:4:"cfgb";s:12:"localization";N;s:14:"settings_title";s:24:"CF Global Posts Settings";s:13:"settings_link";s:15:"CF Global Posts";s:4:"init";s:1:"1";s:7:"install";s:1:"1";s:9:"post_edit";s:1:"1";s:12:"comment_edit";s:1:"1";s:6:"jquery";s:1:"1";s:6:"wp_css";b:0;s:5:"wp_js";b:0;s:9:"admin_css";b:0;s:8:"admin_js";b:0;s:15:"request_handler";s:1:"1";s:6:"snoopy";b:0;s:11:"setting_cat";s:1:"1";s:14:"setting_author";b:0;}

?>