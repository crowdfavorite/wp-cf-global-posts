<div class="wrap">
	<?php screen_icon(); ?>
	<h2><?php echo __('CF Global Posts Operations', 'cf-global-posts'); ?></h2>
	<?php
	if (!cfgp_is_installed()) {
		cfgp_load_view('install');
	}
	else {
		cfgp_load_view('import-table-ajax-receipt');
		cfgp_load_view('import-table', compact('results', 'blog_ids'));
		
		// Conditional as only *very few* users should have access to this
		if ($show_reset_button) {
			cfgp_load_view('reset-all-button');
		}
	}
	?>
</div><!--/wrap-->
