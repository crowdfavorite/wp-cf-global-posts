<?php
/*
Plugin Name: CF Global Posts 
Plugin URI:  
Description: Generates a 'shadow blog' where posts mu-install-wide are conglomorated into one posts table for data compilation and retrieval 
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



/*************************
* Installation Functions *
*************************/
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




/**************************
* Post Updating Functions *
**************************/
function cfgp_remove_post_save_actions() {
	remove_action('save_post', 'cfgp_clone_post_on_publish'); // If you remove this the world will stop (it goes into an infinite loop if this isn't here)
	remove_action('publish_post', '_publish_post_hook', 5, 1); // This *does* require the '5', '1' parameters
}
function cfgp_add_post_save_actions() {
	add_action('publish_post', '_publish_post_hook', 5, 1);
	add_action('save_post', 'cfgp_clone_post_on_publish', 10, 2);
}
function cfgp_get_shadow_blog_id() {
	do_action('cfgp_switch_to_site'); // If you're doing multiple sites, hook in here

	/* Get the shadow blog's id */
	$cfgp_blog_id = get_site_option('cfgp_blog_id');

	do_action('cfgp_restore_current_site'); // ...again, multiple sites, hook in here
	
	return $cfgp_blog_id;
}
function cfgp_are_we_inserting($post_id) {
	/* Grab the clone's id */
	return get_post_meta($post_id, '_cfgp_clone_id', true);
}
function cfgp_do_the_post($cfgp_blog_id) {
	global $post;
	/* Check to see if we're inserting the post, or updating an existing */
	$clone_post_id = cfgp_are_we_inserting($post->ID);

	switch_to_blog($cfgp_blog_id);
	cfgp_remove_post_save_actions();
	if ($clone_post_id == '') {
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
		$post->ID = $clone_post_id;
		$clone_id = wp_update_post($post);
	}
	restore_current_blog();
	cfgp_add_post_save_actions();
	return $clone_id; 
}
function cfgp_do_categories($cfgp_blog_id, $clone_id, $cur_cats_names) {
	/* $cur_cats_names should be an array of category names only */
	
	switch_to_blog($cfgp_blog_id);
	
	if (!function_exists('wp_create_categories')) {
		/* INCLUDE ALL ADMIN FUNCTIONS */
		require_once(ABSPATH . 'wp-admin/includes/admin.php');
	}
	/* This function creates the cats if they don't exist, and 
	* 	then assigns them to the post ID that's passed. */ 	
	$cats_results = wp_create_categories($cur_cats_names, $clone_id);

	restore_current_blog();

	if (is_array($cats_results) && !empty($cats_results)) {
		return true;
	}
	else {
		return false;
	}
}
function cfgp_do_tags($cfgp_blog_id, $clone_id, $tags) {
	/* $tags should a comma-seperated string of tags */
	
	/* Add or remove tags as needed.  We aren't
	* 	doing checking, b/c WP does it for us */
	
	switch_to_blog($cfgp_blog_id);
	$result = wp_set_post_tags($clone_id, $tags);
	restore_current_blog();
	if ($result === false) {
		return false;
	}
	else {
		return true;
	}
}
function cfgp_push_all_post_meta($all_post_meta, $clone_id) {
	/* We should already be switched to blog!! */
	if (!is_array($all_post_meta)) {
		/* Require an array */
		return false;
	}
	
	$excluded_values = array(
		'_edit_last',
		'_edit_lock',
		'_encloseme',
		'_pingme'
	);
	$excluded_values = apply_filters('cfgp_exluded_post_meta_values', $excluded_values);
	foreach ($all_post_meta as $key => $value) {
		if (in_array($key, $excluded_values)) { 
			/* we don't need to update that key */
			continue; 
		}

		if (is_array($value) && count($value) > 1) {
			/* The original value was an array, so store it as such */
			$results[$key] = update_post_meta($clone_id, $key, $value);
		}
		else {
			/* The original value wasn't an array, so store it as $value's first value */
			$results[$key] = update_post_meta($clone_id, $key, $value[0]);
		}
	}
	return $results;
}
function cfgp_do_post_meta($post_id, $cfgp_blog_id, $clone_id) {
	global $wpdb;
	
	/* first add post_meta to the original 
	* 	post of the clone's post id */
	update_post_meta($post_id, '_cfgp_clone_id', $clone_id);
	
	/* Get all the post_meta for current post */
	$all_post_meta = get_post_custom($post_id);

	/* Assign original blog's id to a variable to be used in post_meta later */
	$original_blog_id = $wpdb->blogid;

	/* Now add all post_meta to clone post */
	switch_to_blog($cfgp_blog_id);
	$results = cfgp_push_all_post_meta($all_post_meta, $clone_id);
	
	/* Add the original blog's id to the clone's post meta */
	$results['_original_blog_id'] = update_post_meta($clone_id, '_original_blog_id', $original_blog_id);
	
	restore_current_blog();
	
	return $results;
}



/***************************
* Functions called from WP *
***************************/
function cfgp_clone_post_on_publish($post_id, $post) {
	global $wpdb;
	
	/* If it's a draft, get the heck out of dodge */
	if ($post->post_status == 'draft') { return; }
	
	/* This is a revision, not something that needs to get cloned */
	if ($post->post_status == 'inherit') { return; }

	/* Get the Shadow Blog's ID */
	$cfgp_blog_id = cfgp_get_shadow_blog_id();
	
	/* Get the current blog's id */
	$current_blog_id = $wpdb->blogid;
	

	/************
	* POST WORK *
	************/
	$old_post_id = $post->ID;
	$clone_id = cfgp_do_the_post($cfgp_blog_id);
	$post->ID = $old_post_id;
	
	
	/****************
	* CATEGORY WORK *
	****************/
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
	$cat_results = cfgp_do_categories($cfgp_blog_id, $clone_id, $cur_cats_names);


	/***********
	* TAG WORK *
	***********/
	$tags = $_POST['tags_input'];
	$tag_results = cfgp_do_tags($cfgp_blog_id, $clone_id, $tags);

	/*****************
	* POST META WORK *
	*****************/
	$post_meta_results = cfgp_do_post_meta($post->ID, $cfgp_blog_id, $clone_id);



	/* This is a handy array of results, for troubleshooting
	* 	they're not returned on post publish, but can be put
	* 	out to the error log */
	$single_post_results[] = array(
		'original_post' => $post->ID,
		'clone_id' => $clone_id,
		'cat_results' => $cat_results, 
		'tag_results' => $tag_results, 
		'post_meta_results' => $post_meta_results
	);
}
add_action('save_post', 'cfgp_clone_post_on_publish', 10, 2);

function batch_import_blog($blog_id, $offset, $increment) {
	switch_to_blog($blog_id);
	
	/* http://codex.wordpress.org/Template_Tags/query_posts#Offset_Parameter */
	$args = array(
		'offset' => $offset,
		'showposts' => $increment
	);


	$cfgp_blog_id = cfgp_get_shadow_blog_id();


	/* Grab posts */
	query_posts($args);
	
	if (have_posts()) {
		global $post;
		$batch_status = 'running';
		while (have_posts()) {
			/************
			* POST WORK *
			************/
			/* Setup post data */
			the_post(); 
			
			$old_post_id = $post->ID;
			$clone_id = cfgp_do_the_post($cfgp_blog_id);
			$post->ID = $old_post_id;
	
			
			/****************
			* CATEGORY WORK *
			****************/
			
			/* Get the category names into array */
			$categories = get_the_category($post->ID);
			if (is_array($categories)) {
				$cur_cat_names = array();
				foreach ($categories as $cat) {
					$cur_cats_names[] = $cat->name;
				}
				$cat_results = cfgp_do_categories($cfgp_blog_id, $clone_id, $cur_cats_names);
			}

			/***********
			* TAG WORK *
			***********/

			/* Get the tag information */
			$tags = get_the_tags($post->ID);
			if (is_array($tags)) {
				foreach ($tags as $tag) {
					$tag_names[] = $tag->name;
				}
				$tag_name_string = implode(', ', $tag_names);
				$tag_results = cfgp_do_tags($cfgp_blog_id, $clone_id, $tag_name_string);
			}



			
			/*****************
			* POST META WORK *
			*****************/
			$post_meta_results = cfgp_do_post_meta($post->ID, $cfgp_blog_id, $clone_id);
			
			
			/* Add the return values for this post */
			$single_post_results[] = array(
				'original_post' => $post->ID,
				'clone_id' => $clone_id,
				'cat_results' => $cat_results, 
				'tag_results' => $tag_results, 
				'post_meta_results' => $post_meta_results
			);
		}
	}
	else {
		$batch_status = 'finished';
	}

	$results = array(
		'status' => $batch_status, 
		'blog' => $blog_id, 
		'posts' => $my_posts, 
		'result_details' => $single_post_results,
		'next_offset' => ($offset + $increment),
	);
	return $results;
}


function cfgp_is_installed() {
	$cfgp_blog_id = cfgp_get_shadow_blog_id();
	if (empty($cfgp_blog_id)) {
		return false;
	}
	return true;
}
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
				
			case 'add_blog_to_shadow_blog':
				/* Set how many blog posts to do at once */
				$increment = 2;
				
				/* Grab the ID of the blog we're pulling from */
				$blog_id =  (int) $_POST['blog_id'];
				
				/* Grab our offset */
				$offset = (int) $_POST['offset'];
				
				/* Admin page won't let somebody into this functionality,
				* 	but in case someone hacks the url, don't try to do
				* 	the import w/o the cf-compat plugin */
				if (!function_exists('cf_json_encode')) { exit(); }
				
				echo cf_json_encode( batch_import_blog( $blog_id, $offset, $increment ) );
				
				exit();
				break;
			case 'cfgp_setup_shadow_blog':
				cfgp_install();
				/* We don't want to exit, b/c we want the page to refresh */
				break;
		}
	}
}
add_action('init', 'cfgp_request_handler');


wp_enqueue_script('jquery');


function cfgp_operations_form() {
	global $wpdb;
	if (!cfgp_is_installed()) {
		?>
		<div class="wrap">
			<?php screen_icon(); ?><h2><?php echo __('CF Global Posts Setup', ''); ?></h2>
			<p>Welcome to the Global Posts Operations page; click the button below to set up the 'Global Blog'</p>
			<form method="post">
				<input type="hidden" name="cf_action" value="cfgp_setup_shadow_blog" />
				<button type="submit">Setup Global Blog Now</button>
			</form>
		</div>
		<?php
		return;
	}
	if (!function_exists('cf_json_encode')) {
		?>
		<div class="wrap">
			<?php screen_icon(); ?><h2><?php echo __('CF Global Posts Operations', ''); ?></h2>
			<p>This plugin requires functionality contained in the 'cf-compat' plugin.  This plugin must be activated before utilizing this page.</p>
		</div>
		<?php
		return;
	}
	?>
	<div class="wrap">
		<?php screen_icon(); ?><h2><?php echo __('CF Global Posts Operations', ''); ?></h2>
		<script type="text/javascript">
			jQuery(function($) {
				import_box = $("#doing-import");
				import_box.hide();
				
				import_buttons = $("button[id^='start_import_blog_']");
				
				import_buttons.click(function(){
					$(document).scrollTop(0);
					blogId = $(this).siblings("input[name='blog_id']").val();
					import_buttons.attr('disabled','disabled');
					do_batch(blogId, 0);
					import_box.show().removeClass('updated fade').children('h2').text('Import in progress, do not navigate away from this page...').siblings("#import-ticks").text('');
					return false;
				});
				function do_batch(blogId, offset_amount) {
					$.post(
						'index.php',
						{
							cf_action:'add_blog_to_shadow_blog',
							blog_id: blogId,
							offset: offset_amount
						},
						function(r){
							if (r.status == 'finished') {
								import_box.addClass('updated fade').children('h2').text('Finished Importing!').siblings("#import-ticks").text('');
								import_buttons.removeAttr('disabled');
								return;
							}
							else {
								import_box.children("#import-ticks").text(import_box.children("#import-ticks").text()+' # ');
								do_batch(blogId, r.next_offset);
							}
						},
						'json'
					);
				}
			});
		</script>
		<div id="doing-import" style="border: 1px solid #464646; margin: 20px 0; padding: 10px 20px;">
			<h2></h2>
			<p id="import-ticks"></p>
		</div>
		<table class="widefat" style="width: 300px; margin: 20px 0">
			<thead>
				<tr>
					<th scope="col">Blog</th>
					<th scope="col" style="width: 30px;">Action</th>
				</tr>
			</thead>
			<tbody>
			<?php
			global $wpdb;
			$shadow_blog = get_site_option('cfgp_blog_id');
			$sql = 'SELECT * FROM '.$wpdb->blogs.' ORDER BY site_id, blog_id';
		
			$results = $wpdb->get_results($sql);
			if (is_array($results)) {
				foreach ($results as $blog) {
					if ($blog->blog_id == $shadow_blog) { continue; }
					echo '
						<tr>
							<th style="text-align: right; padding-top: 11px;">'.$blog->domain.'</th>
							<td>
								<form method="post" name="blog_import_'.attribute_escape($blog->blog_id).'" id="blog_import_'.attribute_escape($blog->blog_id).'">
								<input type="hidden" name="blog_id" value="'.attribute_escape($blog->blog_id).'" />
								<input type="hidden" name="cf_action" value="add_blog_to_shadow_blog">
								<button class="button-primary" id="start_import_blog_'.attribute_escape($blog->blog_id).'"/>Import</button>
								</form>
							</td>
						</tr>
					';
				}
			}
			else {
				echo 'No Blogs available';
			}
			?>
			</tbody>
		</table>

	</div><!--/wrap-->
	<?php
}

function cfgp_admin_menu() {
	global $wpdb;
	
	// force this to be only visible to site admins
	if (!is_site_admin()) { return; }
	
	if (current_user_can('manage_options')) {
		add_options_page(
			__('CF Global Posts Functions', '')
			, __('CF Global Posts', '')
			, 10
			, basename(__FILE__)
			, 'cfgp_operations_form'
		);
	}
}
add_action('admin_menu', 'cfgp_admin_menu');






























/********************************************************************
* Below are the default functions with the plugin, and till we get  *
* this finalize a little more, we'll leave them in until we know we *
* don't need them.                                                  *
* ******************************************************************/



function cfgp_init() {
// TODO
}
add_action('init', 'cfgp_init');







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