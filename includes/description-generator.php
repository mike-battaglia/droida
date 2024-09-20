<?
/*
 * Generate_ai_art_description function
 */
function generate_ai_art_description($product_id, $override_role_check = false) {
	error_log('AI Art - Starting description generation for product ID: ' . $product_id);
	
	$product = wc_get_product($product_id);
	$post_id = $product->get_id();
	
	if (!$product) {
	error_log('AI Art - Failed to retrieve product for ID: ' . $product_id);
	return false;
	}

	// Get contextual variables
	$product_title = sanitize_text_field($product->get_name());
	$author_name = get_the_author_meta('display_name', $product->get_post_data()->post_author);
	$product_categories = wp_get_post_terms( $product_id, 'product_cat' );

	// Check if the product has categories and assign the first category to a variable
	if ( ! is_wp_error( $product_categories ) && ! empty( $product_categories ) ) {
		$first_category = $product_categories[0]; // Assign the first category term object
		$category_name = $first_category->name;   // Get the name of the category
		$category_slug = $first_category->slug;   // Get the slug of the category
		$category_id = $first_category->term_id;  // Get the ID of the category
	} else {
		$category_name = 'Art'; // Fallback if no category is found
	}

	
	// Retrieve the custom prompt from the settings
	$prompt_context = get_option('ai_art_description_context', 'Describe this image in detail to the visually impaired.');
	$prompt_serp = get_option('ai_art_description_serp', 'Describe this image in detail to the visually impaired.');
	$prompt_excerpt = get_option('ai_art_description_excerpt', 'Describe this image in detail to the visually impaired.');
	$prompt_facebook = get_option('ai_art_description_facebook', 'Describe this image in detail to the visually impaired.');
	$prompt_twitter = get_option('ai_art_description_twitter', 'Describe this image in detail to the visually impaired.');
	
	// Replace placeholders with actual values
	$dynamic_context = replace_placeholders($prompt_context, $product_title, $author_name, $category_name);
	$dynamic_serp  = replace_placeholders($prompt_serp, $product_title, $author_name, $category_name);
	$dynamic_excerpt = replace_placeholders($prompt_excerpt, $product_title, $author_name, $category_name);
	$dynamic_twitter  = replace_placeholders($prompt_twitter, $product_title, $author_name, $category_name);
	$dynamic_facebook = replace_placeholders($prompt_facebook, $product_title, $author_name, $category_name);

	// Get the author ID using get_post_field()
	$author_id = get_post_field('post_author', $product_id);
	if (!$author_id) {
		error_log('AI Art - Could not retrieve author ID for product ID ' . $product_id);
		return false;
	}
	
	$user = get_userdata($author_id);
	if (!$user) {
		error_log('AI Art - Could not retrieve user data for author ID ' . $author_id);
		return false;
	}
	
	if (!$override_role_check && !in_array('ai-premium', $user->roles) && !current_user_can('manage_options')) {
		error_log('AI Art - User does not have the required role for product ID ' . $product_id);
		return false; // Exit if the user doesn't have the 'ai-premium' role and override is not allowed
	}
	
	$image_id = $product->get_image_id();
	$image_url = wp_get_attachment_url($image_id);
	if (!$image_url) {
		error_log('AI Art - No image found for product ID ' . $product_id);
		return false;
	}
	
	error_log('AI Art - Image URL: ' . $image_url);
	
	// Assuming OPENAI_API_KEY is defined securely
	$api_key = get_option('ai_art_description_api_key');
	if (!$api_key) {
		error_log('AI Art - OpenAI API key is missing.');
		return false;
	}
	
	$api_url = 'https://api.openai.com/v1/chat/completions';
	$headers = array(
		'Content-Type'  => 'application/json',
		'Authorization' => 'Bearer ' . $api_key,
	);
	
	// Construct the request body
	$body_array = array(
		'model' => 'gpt-4o-mini',
		'messages' => array(
			array(
				'role' => 'user',
				'content' => array(
					array(
						'type' => 'text',
						'text' => $dynamic_context,
					),
					array(
						'type' => 'image_url',
						'image_url' => array(
							'url' => $image_url,
							'detail' => 'high',
						),
					),
				),
			),
		),
		'response_format' => array(
			'type' => 'json_schema',
			'json_schema' => array(
				'name' => 'artwork_descriptions',
				'schema' => array(
					'type' => 'object',
					'properties' => array(
						'gallery_description' => array(
							'type' => 'string',
							'description' => $dynamic_excerpt,
						),
						'short_serp_sentence' => array(
							'type' => 'string',
							'description' => $dynamic_serp,
						),
						'shared_link_preview' => array(
							'type' => 'string',
							'description' => $dynamic_facebook,
						),
						'post_with_hashtags' => array(
							'type' => 'string',
							'description' => $dynamic_twitter,
						),
					),
				),
			),
		),
		'max_tokens' => 1000,
	);
	
	error_log('AI Art - Body: ' . print_r($body_array, true));
	
	$response = wp_remote_post($api_url, array(
		'headers' => $headers,
		'body'    => json_encode($body_array),
		'timeout' => 120,
	));
	
	if (is_wp_error($response)) {
		error_log('AI Art - OpenAI API error: ' . $response->get_error_message());
		return false;
	}
	
	$response_body = wp_remote_retrieve_body($response);
	$result = json_decode($response_body, true);
	
	error_log('AI Art - Response: ' . print_r($result, true));
	
	// Check if AI description is returned
	if (isset($result['choices'][0]['message']['content'])) {
		$ai_description = $result['choices'][0]['message']['content'];
		
		$descriptions = json_decode( $ai_description, true );
		
		// Check if classy_description exists in the decoded array
		if ( isset( $descriptions['gallery_description'] ) ) {
			// Prepare the post data to update the excerpt
			$post_data = array(
                'ID'           => $post_id,
                'post_excerpt' => wp_strip_all_tags( $descriptions['gallery_description'] ), // Sanitize the description
            );

			// Update the post with the classy_description as the excerpt
			wp_update_post( $post_data );
		}

		 // Save rich_snippet_description as rank_math_description
        if ( isset( $descriptions['short_serp_sentence'] ) ) {
            update_post_meta( $post_id, 'rank_math_description', wp_strip_all_tags( $descriptions['short_serp_sentence'] ) );
        }

        // Save social_share_preview_description as rank_math_facebook_description
        if ( isset( $descriptions['shared_link_preview'] ) ) {
            update_post_meta( $post_id, 'rank_math_facebook_description', wp_strip_all_tags( $descriptions['shared_link_preview'] ) );
        }

        // Save tweet_description as rank_math_twitter_description
        if ( isset( $descriptions['post_with_hashtags'] ) ) {
            update_post_meta( $post_id, 'rank_math_twitter_description', wp_strip_all_tags( $descriptions['post_with_hashtags'] ) );
        }
		
		// Save the AI description to the ACF custom field 'ai_description'
		update_field('ai_description', $ai_description, $product_id);
		
		error_log('AI Art - Successfully generated description for product ID ' . $product_id);
		return true;
		} elseif (isset($result['error'])) {
			error_log('AI Art - OpenAI API error: ' . $result['error']['message']);
			return false;
		} else {
			error_log('AI Art - OpenAI API did not return a description for product ID ' . $product_id);
			return false;
		}
	}
