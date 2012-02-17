<table class="widefat" style="width: 450px; margin: 20px 0">
	<thead>
		<tr>
			<th scope="col"><?php _e('Blog Name', 'cf-global-posts'); ?></th>
			<th scope="col" style="width: 50px; text-align:center;"><?php _e('Action', 'cf-global-posts'); ?></th>
			<th scope="col" style="width: 150px; text-align:center;"><?php _e('Status', 'cf-global-posts'); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php
	if (is_array($results)) {
		foreach ($results as $blog) {
			if ($blog->blog_id == $shadow_blog) { continue; }
			
			$details = get_blog_details($blog->blog_id);
			$blog_ids[] = $blog->blog_id;
			?>
			<tr id="blogrow-<?php echo $blog->blog_id; ?>">
				<td style="vertical-align:middle;"><?php echo $details->blogname; ?></th>
				<td>
					<form method="post" name="blog_import_<?php echo attribute_escape($blog->blog_id); ?>" id="blog_import_<?php echo attribute_escape($blog->blog_id); ?>">
					<input type="hidden" name="blog_id" value="<?php echo attribute_escape($blog->blog_id); ?>" />
					<input type="hidden" name="cf_action" value="add_blog_to_shadow_blog">
					<button class="button" id="start_import_blog_<?php echo attribute_escape($blog->blog_id); ?>"/><?php _e('Import', 'cf-global-posts'); ?></button>
					</form>
				</td>
				<td class="cfgp_status" style="vertical-align:middle;">
					<div id="status-<?php echo $blog->blog_id; ?>">
						<?php _e('Click Import to proceed', 'cf-global-posts'); ?>
					</div>
				</td>
			</tr>
			<?php
		}
		?>
		<tr>
			<td colspan="3">
				<input type="hidden" id="all_blog_ids" name="all_blog_ids" value="<?php echo implode(',',$blog_ids); ?>" />
				<p>
					<strong><?php _e('NOTE: Doing this operation during peak server loads may cause undesired effects!','cf-global-posts'); ?></strong>
				</p>
				<button class="button-primary" id="start_import_all_blogs"><?php _e('Import All','cf-global-posts'); ?></button>
			</td>
		</tr>
		<?php
	}
	else {
		_e('No Blogs available', 'cf-global-posts');
	}
	?>
	</tbody>
</table>
