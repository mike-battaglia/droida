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
	$bulk_actions['generate_ai_description'] = __('🤖 Generate AI Descriptions', 'your-text-domain');
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

// generate_ai_art_description function
function generate_ai_art_description($product_id, $override_role_check = false) {
    error_log('AI Art - Starting description generation for product ID: ' . $product_id);

    $product = wc_get_product($product_id);

    if (!$product) {
        error_log('AI Art - Failed to retrieve product for ID: ' . $product_id);
        return false;
    }

    // Get and sanitize the product title
    $product_title = sanitize_text_field($product->get_name());

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

    $prompt_text = "I'm uploading my artwork, " . $product_title . ", to an online gallery for millenials, but need your help writing the description. Please give me a classy description based on the image provided. Please limit your response to only the description alone in plain text so that I can just copy it and paste it into the gallery's online form. The description should be detailed and thurough. In consideration of the visually impared, assume the user cannot see the artwork.";

    // Construct the request body
    $body_array = array(
        'model' => 'gpt-4o',
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


// ⚙️ ADD SETTINGS PAGE
// Add a settings page to the WordPress admin menu
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
}

// Callback function for the section description
function ai_art_description_section_cb() {
    echo '<p>Enter your OpenAI API key to enable description generation.</p>';
}

// Callback function for the API key input field
function ai_art_description_api_key_cb() {
    // Get the current value from the database
    $api_key = get_option('ai_art_description_api_key');
    // Render the input field
    echo '<input type="text" name="ai_art_description_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
}

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
