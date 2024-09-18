<?php
/*
Plugin Name: AI Art Description
Description: Generates AI descriptions for artworks upon product publishing.
Version: 1.0
Author: Sterling Digital
*/

add_action('woocommerce_new_product', 'generate_ai_art_description', 10, 1);

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

	// Get and sanitize the product title
	$product_title = sanitize_text_field($product->get_name());
	
	// Retrieve the custom prompt from the settings
	$custom_prompt = get_option('ai_art_description_prompt', 'Describe this image in detail to the visually impaired.');
	
	// Replace placeholders with actual values
	$author_name = get_the_author_meta('display_name', $product->get_post_data()->post_author);
	/*$prompt_text = str_replace(
		array('{artwork_title}', '{artist_name}'), 
		array($product_title, $author_name), 
		$custom_prompt
	);*/
	$prompt_text = 'Please see attached. My name is ' . $author_name . ', creator of ' . $product_title . '. I\'m uploading my art to an online gallery and I need a few variations of descriptions. First, I need a classy description to appear on the piece\'s web page. In consideration of the visually impaired, please write the classy description assuming the user cannot see the art. Second, I need an SEO friendly description appropriate for Rich Snippets. Third, I need a social media preview description that will be seen whenever a link to the piece is shared on facebook. Finally, I need copy for a tweet promoting the piece with hashtags. In all cases, please respond in plain text without linebreaks so that I can just copy and paste it as-is.';

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
						'text' => $prompt_text,
					),
					array(
						'type' => 'image_url',
						'image_url' => array(
							'url' => $image_url,
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
						'classy_description' => array(
							'type' => 'string',
							'description' => 'Describe this art in a classy manner for the blind.',
						),
						'rich_snippet_description' => array(
							'type' => 'string',
							'description' => 'Describe this art in short rich-snippet for SERPs.',
						),
						'social_share_preview_description' => array(
							'type' => 'string',
							'description' => 'Describe this art in a sentence for social media link-share previews.',
						),
						'tweet_description' => array(
							'type' => 'string',
							'description' => 'Describe this art in a tweet with hashtags.',
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
		'timeout' => 60,
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
		if ( isset( $descriptions['classy_description'] ) ) {
			// Prepare the post data to update the excerpt
			$post_data = array(
                'ID'           => $post_id,
                'post_excerpt' => wp_strip_all_tags( $descriptions['classy_description'] ), // Sanitize the description
            );

			// Update the post with the classy_description as the excerpt
			wp_update_post( $post_data );
		}

		 // Save rich_snippet_description as rank_math_description
        if ( isset( $descriptions['rich_snippet_description'] ) ) {
            update_post_meta( $post_id, 'rank_math_description', wp_strip_all_tags( $descriptions['rich_snippet_description'] ) );
        }

        // Save social_share_preview_description as rank_math_facebook_description
        if ( isset( $descriptions['social_share_preview_description'] ) ) {
            update_post_meta( $post_id, 'rank_math_facebook_description', wp_strip_all_tags( $descriptions['social_share_preview_description'] ) );
        }

        // Save tweet_description as rank_math_twitter_description
        if ( isset( $descriptions['tweet_description'] ) ) {
            update_post_meta( $post_id, 'rank_math_twitter_description', wp_strip_all_tags( $descriptions['tweet_description'] ) );
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

/*
 * Enable Bulk editing
 */
// Add the custom bulk action
add_filter('bulk_actions-edit-product', 'register_generate_ai_description_bulk_action');

function register_generate_ai_description_bulk_action($bulk_actions) {
	$bulk_actions['generate_ai_description'] = __('🤖 Generate AI Descriptions', 'your-text-domain');
	return $bulk_actions;
}

// Handle the bulk action
add_filter('handle_bulk_actions-edit-product', 'schedule_generate_ai_description_bulk_action', 10, 3);

function schedule_generate_ai_description_bulk_action($redirect_to, $action, $post_ids) {
	if ($action !== 'generate_ai_description') {
		return $redirect_to;
	}

	// Capability check
	if (!current_user_can('manage_woocommerce')) {
		wp_die(__('You do not have permission to perform this action.', 'your-text-domain'));
	}

	// Schedule the AI description generation for each product
		foreach ($post_ids as $post_id) {
	wp_schedule_single_event(time(), 'generate_ai_description_cron_event', array($post_id));
	}

	// Redirect with the notice of scheduling
	$redirect_to = add_query_arg('ai_descriptions_scheduled', count($post_ids), $redirect_to);
		return $redirect_to;
}

// Hook the custom cron event to the generate function
add_action('generate_ai_description_cron_event', 'generate_ai_art_description_in_cron');

function generate_ai_art_description_in_cron($product_id) {
	// Override the role check since this is a scheduled task
	generate_ai_art_description($product_id, true);
}

// Display an admin notice upon completion
add_action('admin_notices', 'ai_descriptions_generated_admin_notice');

function ai_descriptions_generated_admin_notice() {
	if (!empty($_REQUEST['ai_descriptions_generated'])) {
		$count = intval($_REQUEST['ai_descriptions_generated']);
		printf(
			'<div id="message" class="updated notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(sprintf(_n('%s AI description generated.', '%s AI descriptions generated.', $count, 'your-text-domain'), number_format_i18n($count)))
		);
	}
}

add_action('admin_notices', 'ai_descriptions_scheduled_admin_notice');

function ai_descriptions_scheduled_admin_notice() {
	if (!empty($_REQUEST['ai_descriptions_scheduled'])) {
		$count = intval($_REQUEST['ai_descriptions_scheduled']);
		printf(
			'<div id="message" class="updated notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(sprintf(_n('%s AI description scheduled.', '%s AI descriptions scheduled.', $count, 'your-text-domain'), number_format_i18n($count)))
		);
	}
}

/*
 * Add settings page to WP Admin Menu
 */
add_action('admin_menu', 'ai_art_description_settings_page');

function ai_art_description_settings_page() {
	add_options_page(
		'🤖 AI Art Description Settings', // Page title
		'🤖 AI Art Description',          // Menu title
		'manage_options',              // Capability required to access
		'ai-art-description',          // Slug of the settings page
		'ai_art_description_settings_page_html' // Callback function to render the page
	);
}

// Render the settings page HTML
function ai_art_description_settings_page_html() {
	// Check if the user has the necessary capability
	if (!current_user_can('manage_options')) {
		return;
	}
	
	// Check if the settings have been updated and display a success message
	if (isset($_GET['settings-updated'])) {
		add_settings_error('ai_art_description_messages', 'ai_art_description_message', 'Settings Saved', 'updated');
	}
	
	// Show error/update messages
	settings_errors('ai_art_description_messages');
	?>
	
	<div class="wrap">
		<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
			<form action="options.php" method="post">
			<?php
				// Output security fields for the settings page
				settings_fields('ai_art_description');
				
				// Output setting sections and their fields
				do_settings_sections('ai-art-description');
				
				// Output the save settings button
				submit_button('Save Settings');
			?>
		</form>
	</div>
	<?php
}

add_action('admin_init', 'ai_art_description_settings_init');

function ai_art_description_settings_init() {
	// Register a new setting for the API key
	register_setting('ai_art_description', 'ai_art_description_api_key');
	
	// Register the setting for the GPT model
	register_setting('ai_art_description', 'ai_description_gpt_model');

	// Add a new section to the settings page
	add_settings_section(
		'ai_art_description_section',        // Section ID
		'API Settings',                      // Section title
		'ai_art_description_section_cb',     // Callback function for section description
		'ai-art-description'                 // Page slug
	);
	
	// Add a field to enter the API key
	add_settings_field(
		'ai_art_description_api_key_field',  // Field ID
		'OpenAI API Key',                    // Field title
		'ai_art_description_api_key_cb',     // Callback function to display the input field
		'ai-art-description',                // Page slug
		'ai_art_description_section'         // Section ID
	);

	// Add a field to enter the Model
	add_settings_field(
		'chatgpt_model',  // Field ID
		'ChatGPT Model',                    // Field title
		'chatgpt_model_cb',    			    // Callback function to display the input field
		'ai-art-description',               // Page slug
		'ai_art_description_section'        // Section ID
	);
}

// Callback function for the section description
function ai_art_description_section_cb() {
	echo '<p>Configure the API call.</p>';
}

// Callback function for the API key input field
function ai_art_description_api_key_cb() {
	echo '<p>Enter your OpenAI API key to enable description generation.</p>';
	// Get the current value from the database
	$api_key = get_option('ai_art_description_api_key');
	// Render the input field
	echo '<input type="text" name="ai_art_description_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
}

// Callback function for the section description
function chatgpt_model_cb() {
	echo '<p>Enter the ChatGPT model in API format (example: gpt-4o-2024-08-06) .</p>';
	// Get the current value from the database
	$gpt_model = get_option('ai_description_gpt_model');
	// Render the input field
	echo '<input type="text" name="ai_description_gpt_model" value="' . esc_attr($gpt_model) . '" class="regular-text">';
}

add_action('admin_init', 'ai_art_description_prompt_setting_init');

function ai_art_description_prompt_setting_init() {
	// Register a new setting for the prompt
	register_setting('ai_art_description', 'ai_art_description_prompt');
	
	// Add a new section to the settings page if not already done
	add_settings_section(
		'ai_art_description_prompt_section',    // Section ID
		'AI Prompt Settings',                   // Section title
		'ai_art_description_prompt_section_cb', // Callback function for section description
		'ai-art-description'                    // Page slug
	);
	
	// Add a field to customize the AI prompt
	add_settings_field(
		'ai_art_description_prompt_field',      // Field ID
		'Custom AI Prompt',                     // Field title
		'ai_art_description_prompt_cb',         // Callback function to display the input field
		'ai-art-description',                   // Page slug
		'ai_art_description_prompt_section'     // Section ID
	);
}

// Callback function for the section description
function ai_art_description_prompt_section_cb() {
	echo '<p>Customize the AI prompt used for generating descriptions. Available placeholders include {artwork_title} and {artist_name}.</p><h3>Example prompt:</h3><p><i>I\'m uploading my artwork, "{artwork_title} by {artist_name}", to an online gallery for Millennials, but need your help writing the description. Please give me a classy description based on the image provided. Please limit your response to only the description alone in plain text so that I can just copy it and paste it into the gallery\'s online form. The description should be detailed and thorough. In consideration of the visually impaired, assume the user cannot see the artwork.</i></p>';
}

// Callback function for the prompt input field
function ai_art_description_prompt_cb() {
	// Get the current prompt value from the database
	$prompt = get_option('ai_art_description_prompt', 'I\'m uploading my artwork, {product_title}, to an online gallery...');
	echo '<textarea name="ai_art_description_prompt" rows="6" cols="50" class="large-text">' . esc_textarea($prompt) . '</textarea>';
}

/*
 * Add button to Product Edit page
 */
// Register the AJAX action for logged-in users
add_action('wp_ajax_generate_ai_art_description_ajax', 'handle_ai_art_description_ajax');

function handle_ai_art_description_ajax() {
	// Check nonce for security
	check_ajax_referer('generate-ai-description', 'security');
	
	// Check user capability
	if (!current_user_can('edit_product', $_POST['product_id'])) {
		wp_send_json_error('You do not have permission to edit this product.');
	}
	
	// Sanitize the product ID
	$product_id = intval($_POST['product_id']);
	
	// Call the generate_ai_art_description function
	$result = generate_ai_art_description($product_id);
	
	if ($result) {
		wp_send_json_success();
	} else {
		wp_send_json_error('Failed to generate AI description.');
	}
}

add_action('add_meta_boxes', 'ai_art_description_meta_box');

function ai_art_description_meta_box() {
	add_meta_box(
		'ai_art_description_meta',          // ID of the meta box
		'🤖 Generate AI Description',       // Title of the meta box
		'ai_art_description_meta_box_html', // Callback function to render the meta box content
		'product',                          // Post type (for WooCommerce products)
		'side',                             // Location (side panel)
		'high'                              // Priority
	);
}

// Callback function to display the button in the meta box
function ai_art_description_meta_box_html($post) {
	?>
	<div id="ai-description-box" style="text-align: right;">
		<p style="text-align: left;">
			Click the Generate button to get a new AI Art Description. This will replace whatever is currently in your AI Art Description field.
		</p>
		<button id="generate-ai-description" class="button button-primary">
			<?php esc_html_e('Generate AI Description', 'your-text-domain'); ?>
		</button>
		<p id="ai-description-status"></p>
	</div>
	
	<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#generate-ai-description').on('click', function(e) {
				e.preventDefault();
				
				// Disable button and show loading message
				$('#generate-ai-description').attr('disabled', 'disabled').text('Generating...');
				$('#ai-description-status').text('');
				
				// Send AJAX request
				var data = {
					action: 'generate_ai_art_description_ajax',
					product_id: <?php echo $post->ID; ?>,
					security: '<?php echo wp_create_nonce('generate-ai-description'); ?>'
				};
				
				$.post(ajaxurl, data, function(response) {
					// Handle response
					$('#generate-ai-description').removeAttr('disabled').text('Generate AI Description');
					if (response.success) {
						$('#ai-description-status').text('AI Description generated successfully! Refresh this page to see the new AI Description.');
					} else {
						$('#ai-description-status').text('Failed to generate AI description.');
					}
				});
			});
		});
	</script>
	<?php
}
