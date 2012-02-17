<?php
/* Display Reset Global Post Button here */
$acceptable_big_admins = apply_filters('cfgp_big_admins', array());
if (in_array($userdata->user_login, $acceptable_big_admins)) {

	/* This button will:
	* 		1) delete the shadow blog
	* 		2) remove all post_meta keys ('_cfgp_clone_id')
	*/
	?>
	<button class="button-primary" id="reset_shadow_blog_button" name="reset_shadow_blog_button">Reset Entire Shadow Blog</button>
	<?php
}
