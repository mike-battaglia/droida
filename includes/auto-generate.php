<?php
function custom_function_on_new_product($new_status, $old_status, $post) {
	error_log('New Product: 1 - Start');
    // Check if the post type is 'product' and the new status is 'publish'
    if ($post->post_type === 'product' && $new_status === 'publish') {
        error_log('New Product: 2 - Is product, published.');
		
		// Retrieve the ACF field for the post
		$ai_description = get_field('ai_description', $post->ID);
		// Get the post's published date
        $published_date = get_the_date('Y-m-d', $post);
        // Get the current date
        $current_date = current_time('Y-m-d');
		// Get the post excerpt directly from the post object
		$post_excerpt = $post->post_excerpt;
		
		if(empty($ai_description)) {
	        error_log('New Product: 3 - AI Description is empty.');
			if(empty($post_excerpt)){
		        error_log('New Product: 4 - Excerpt is empty.');
				if(($published_date == $current_date)){
			        error_log('New Product: 5 - Published ' . $published_date . ', is now ' . $current_date . '.');
					if(has_post_thumbnail( $post->ID)) {
				        error_log('New Product: 6 - Do ai function.');
            			generate_ai_art_description($post->ID); // Use $post->ID instead of $post_id
					}
				}
			}
		}
	}
}
add_action('transition_post_status', 'custom_function_on_new_product', 10, 3);
