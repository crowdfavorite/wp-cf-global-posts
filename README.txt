# CF Global Posts Readme 

## Description
Generates a 'shadow blog' where posts mu-install-wide are conglomorated into one posts table for fast data compilation and retrieval.

## Implementation
- Upload CF Global Posts Plugin to mu-plugins or plugins directory
- If plugin uploaded to wp-content/plugins directory, visit the <a href="plugins.php">Plugins Page</a> and activate the plugin.
- Visit the settings page, and click the "Set up Global Blog Now" button
- Import any existing blogs, by clicking the import button that appears next to the blog.  There is also an option for importing all blogs at one time -- but this will take the server "to its knees", so you probably don't want to do it on a production server.
- Any posts added since the activation will be added to the global posts.

## Dependancies 
None

## Example
None

## Available Filters
- `cfgp_define_import_increment`
	- Override the default of 10 posts per blog import request.
- `cfgp_big_admins`
	- Takes an array of usernames that are able to see the "Reset Global Posts" button.  
	- The button is purposefully hidden, because this is a *major* action, and shouldn't be done often.
- `cfgp_define_site_id`
	- Allows the ability to change the site ID for the global posts blog from its default of 999999.  
	- This should *NOT* be changed unless absolutely necessary
- `cfgp_define_domain_name`
	- Override the domain of the global posts blog from the default of "cf-global-posts.example.com"
	- This should *NOT* be done unless absolutely necessary -- and functionality hasn't been tested.
- `cfgp_exluded_post_meta_values`
	- Append to or override the array of `post_meta` keys to *exclude* from importing into the global posts blog. 
	`$excluded_values = array(
		'_edit_last',
		'_edit_lock',
		'_encloseme',
		'_pingme',
		'_cfgp_clone_id'
	);`