<?php
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
		'chatgpt_model',  					// Field ID
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
	// Register settings for the prompt
	register_setting('ai_art_description', 'ai_art_description_context');
	register_setting('ai_art_description', 'ai_art_description_excerpt');
	register_setting('ai_art_description', 'ai_art_description_serp');
	register_setting('ai_art_description', 'ai_art_description_facebook');
	register_setting('ai_art_description', 'ai_art_description_twitter');
	
	// Add a new section to the settings page if not already done
	add_settings_section(
		'ai_art_description_prompt_section',    // Section ID
		'AI Prompt Settings',                   // Section title
		'ai_art_description_prompt_section_cb', // Callback function for section description
		'ai-art-description'                    // Page slug
	);
	
	// Add a field to customize the Context prompt
	add_settings_field(
		'ai_art_description_context_field',      // Field ID
		'Context',                              // Field title
		'ai_art_description_context_cb',         // Callback function to display the input field
		'ai-art-description',                   // Page slug
		'ai_art_description_prompt_section'     // Section ID
	);

	// Add a field to customize the Excerpt prompt
	add_settings_field(
		'ai_art_description_excerpt_field',      // Field ID
		'Gallery Excerpt',                      // Field title
		'ai_art_description_excerpt_cb',         // Callback function to display the input field
		'ai-art-description',                   // Page slug
		'ai_art_description_prompt_section'     // Section ID
	);

	// Add a field to customize the Google prompt
	add_settings_field(
		'ai_art_description_serp_field',      // Field ID
		'Rank Math: Search Engine Results Page', // Field title
		'ai_art_description_serp_cb',           // Callback function to display the input field
		'ai-art-description',                   // Page slug
		'ai_art_description_prompt_section'     // Section ID
	);

	// Add a field to customize the Preview prompt
	add_settings_field(
		'ai_art_description_prompt_field',      // Field ID
		'Rank Math: Facebook Share',            // Field title
		'ai_art_description_facebook_cb',       // Callback function to display the input field
		'ai-art-description',                   // Page slug
		'ai_art_description_prompt_section'     // Section ID
	);

	// Add a field to customize the Post prompt
	add_settings_field(
		'ai_art_description_twitter_field',      // Field ID
		'Rank Math: Twitter Post',                     // Field title
		'ai_art_description_twitter_cb',         // Callback function to display the input field
		'ai-art-description',                   // Page slug
		'ai_art_description_prompt_section'     // Section ID
	);
}

// Callback function for the section description
function ai_art_description_prompt_section_cb() {
	echo '<p>Customize the AI prompt used for generating descriptions. Available placeholders include:<b> {artwork_title}, {artwork_category}, {artist_name}</b>.</p>';
}

// Callback function for the prompt input field
function ai_art_description_context_cb() {
	echo '<p>Give ChatGPT context about the piece.</p>';
	// Get the current prompt value from the database
	$prompt_context = get_option('ai_art_description_context', 'Give a classy description of the artwork entitled {artwork_title} for the visually impaired. Please give all responses in plain text with no line-breaks so that I can just copy and paste it where I need.');
	echo '<textarea name="ai_art_description_context" rows="6" cols="50" class="large-text">' . esc_textarea($prompt_context) . '</textarea>';
}

// Callback function for the prompt input field
function ai_art_description_excerpt_cb() {
	echo '<p>This excerpt is used within Wordpress and WooCommerce.</p>';
	// Get the current prompt value from the database
	$prompt_excerpt = get_option('ai_art_description_excerpt', 'Briefly describe this artwork in the form of a Wordpress Post Excerpt.');
	echo '<textarea name="ai_art_description_excerpt" rows="6" cols="50" class="large-text">' . esc_textarea($prompt_excerpt) . '</textarea>';
}

// Callback function for the prompt input field
function ai_art_description_serp_cb() {
	echo '<p>This appears on SERPs (Google searches).</p>';
	// Get the current prompt value from the database
	$prompt_serp = get_option('ai_art_description_serp', 'Describe this artwork in an extremely short Rank Math SERP sentence. The sentence needs to be able to display on the SERP page without getting cut off.');
	echo '<textarea name="ai_art_description_serp" rows="6" cols="50" class="large-text">' . esc_textarea($prompt_serp) . '</textarea>';
}

// Callback function for the prompt input field
function ai_art_description_facebook_cb() {
	echo '<p>This appears as a preview when a link is shared on social media.</p>';
	// Get the current prompt value from the database
	$prompt_facebook = get_option('ai_art_description_facebook', 'Write a short sentence describing this artwork for Rank Math\'s Facebook share field.');
	echo '<textarea name="ai_art_description_facebook" rows="6" cols="50" class="large-text">' . esc_textarea($prompt_facebook) . '</textarea>';
}

// Callback function for the prompt input field
function ai_art_description_twitter_cb() {
	echo '<p>This would be useful as a social media post, like on Twitter or LinkedIn.</p>';
	// Get the current prompt value from the database
	$prompt_twitter = get_option('ai_art_description_twitter', 'Write a tweet about this artwork, including hashtags.');
	echo '<textarea name="ai_art_description_twitter" rows="6" cols="50" class="large-text">' . esc_textarea($prompt_twitter) . '</textarea>';
}

// Function to replace placeholders dynamically
function replace_placeholders( $prompt, $product_title, $author_name, $category_name ) {
    return str_replace(
        array('{artwork_title}', '{artist_name}', '{artwork_category}'), 
        array($product_title, $author_name, $category_name), 
        $prompt
    );
}

