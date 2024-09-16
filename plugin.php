<?php
/*
Plugin Name: AI Art Description
Description: Generates AI descriptions for artworks upon product publishing.
Version: 1.0
Author: Sterling Digital
*/

add_action('woocommerce_new_product', 'generate_ai_art_description', 10, 1);

// Add the custom bulk action
add_filter('bulk_actions-edit-product', 'register_generate_ai_description_bulk_action');

function register_generate_ai_description_bulk_action($bulk_actions) {
    $bulk_actions['generate_ai_description'] = __('Generate AI Descriptions', 'your-text-domain');
    return $bulk_actions;
}

// Handle the bulk action
add_filter('handle_bulk_actions-edit-product', 'handle_generate_ai_description_bulk_action', 10, 3);

function handle_generate_ai_description_bulk_action($redirect_to, $action, $post_ids) {
    if ($action !== 'generate_ai_description') {
        return $redirect_to;
    }

    // Capability check
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('You do not have permission to perform this action.', 'your-text-domain'));
    }

    $processed = 0;

    foreach ($post_ids as $post_id) {
        $result = generate_ai_art_description($post_id, true); // Pass true to override role check
        if ($result) {
            $processed++;
        }
    }

    // Add a query argument to display an admin notice
    $redirect_to = add_query_arg('ai_descriptions_generated', $processed, $redirect_to);
    return $redirect_to;
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

// Modify the generate_ai_art_description function
function generate_ai_art_description($product_id, $override_role_check = false) {
    $product = wc_get_product($product_id);
    $author_id = $product->get_post_data()->post_author; // Updated to get_post()
    $user = get_userdata($author_id);

    if (!$override_role_check && !in_array('ai-premium', $user->roles)) {
        return false; // Exit if the user doesn't have the 'ai-premium' role and override is not allowed
    }

    $image_id = $product->get_image_id();
    $image_url = wp_get_attachment_url($image_id);

    if (!$image_url) {
        error_log('No image found for product ID ' . $product_id);
        return false;
    }

    // Assuming OpenAI API supports image analysis via an image parameter
    $api_key = OPENAI_API_KEY; // Securely stored
    $api_url = 'https://api.openai.com/v1/chat/completions';
    $headers = array(
        'Content-Type'  => 'application/json',
        'Authorization' => 'Bearer ' . $api_key,
    );

    $prompt_text = "Describe the artwork in this image as it would be described in a gallery showroom.";

    // Construct the request body
	$body_array = array(
		'model' => 'gpt-4o',
		'messages' => array(
			array( // Wrap the message object in an array
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
		'max_tokens' => 1000
	);

    // Log the request body for debugging
    error_log('AI Art - Body: ' . json_encode($body_array));

    $response = wp_remote_post($api_url, array(
        'headers' => $headers,
        'body'    => json_encode($body_array),
        'timeout' => 60,
    ));

    if (is_wp_error($response)) {
        error_log('OpenAI API error: ' . $response->get_error_message());
        return false;
    }

    $response_body = wp_remote_retrieve_body($response);
    $result = json_decode($response_body, true);

    // Log the response for debugging
    error_log('AI Art - Response: ' . print_r($result, true));

    if (isset($result['choices'][0]['text'])) {
        $ai_description = sanitize_text_field($result['choices'][0]['text']);
        update_post_meta($product_id, 'ai_description', $ai_description);
        return true;
    } elseif (isset($result['error'])) {
        error_log('OpenAI API error: ' . $result['error']['message']);
        return false;
    } else {
        error_log('OpenAI API did not return a description for product ID ' . $product_id);
        return false;
    }
}
