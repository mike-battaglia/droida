<?php
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
    // Get the product image URL
    $product_image_url = get_the_post_thumbnail_url($post->ID, 'full'); // Gets the full-sized product image
	

    ?>
    <div id="ai-description-box" style="text-align: right;">
        <p style="text-align: left;">
			<? echo('Short tag'); ?>
			<?php echo('Long tag'); ?>
            Click the Generate button to get a new AI-vision descriptions for SEO and social media sharing. This will replace whatever is currently in your SEO and social media fields.
        </p>
        <button id="generate-ai-description" class="button button-primary">
            <?php esc_html_e('Generate AI Description', 'your-text-domain'); ?>
        </button>
        <p id="ai-description-status"></p>
    </div>

    <!-- Loader Overlay -->
    <div id="ai-description-loader" style="display: none">
		<div class="loader-text">
			<h2>
				Generating AI descriptions for search engines and social media...
			</h2>
		</div>
        <div class="ai-loader-content">
          	<div class="loader"></div>
		</div>
		<div class="loader-text">
			<h2>
				This usually takes less than a minute.
			</h2>
		</div>
    </div>

	<style>
		/* Loader overlay styles */
		#ai-description-loader {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background-color: rgba(255, 255, 255, 0.9); /* Slightly transparent white background */
			z-index: 9999; /* On top of everything */
			display: flex;
			flex-direction: column;
			justify-content: center!important;
			align-items: center!important;
		}

		.ai-loader-content {
			background-image: url("<?php echo esc_url($product_image_url); ?>");
			background-size: cover;      
		}

		/*Animation Imported*/
		/* HTML: <div class="loader"></div> */
		.loader {
		  width: 300px;
		  background:
			linear-gradient(#000 0 0),
			linear-gradient(#000 0 0),
			linear-gradient(#000 0 0),
			linear-gradient(#000 0 0),
			linear-gradient(#ccc 0 0),
			linear-gradient(#ccc 0 0),
			linear-gradient(#f00 0 0),
			linear-gradient(#f00 0 0);
		  background-size: 25% 25%,25% 25%,25% 25%,25% 25%,25% 50%,25% 50%,50% 25%,50% 25%;
		  background-repeat: no-repeat;
		  animation: l20 1.5s infinite alternate;
		}
		@keyframes l20 {
		  0%,
		  10%  {background-position: 
					calc(1*100%/3) calc(1*100%/3),calc(2*100%/3) calc(1*100%/3),calc(1*100%/3) calc(2*100%/3),calc(2*100%/3) calc(2*100%/3),
					calc(1*100%/3) 50%,calc(2*100%/3) 50%,50% calc(1*100%/3),50% calc(2*100%/3)}
		  33%  {background-position: 
					calc(0*100%/3) calc(0*100%/3),calc(3*100%/3) calc(0*100%/3),calc(0*100%/3) calc(3*100%/3),calc(3*100%/3) calc(3*100%/3),
					calc(1*100%/3) 50%,calc(2*100%/3) 50%,50% calc(1*100%/3),50% calc(2*100%/3)}
		  66%  {background-position: 
					calc(0*100%/3) calc(0*100%/3),calc(3*100%/3) calc(0*100%/3),calc(0*100%/3) calc(3*100%/3),calc(3*100%/3) calc(3*100%/3),
					calc(0*100%/3) 50%,calc(3*100%/3) 50%,50% calc(1*100%/3),50% calc(2*100%/3)}
		  90%,
		  100%  {background-position: 
					calc(0*100%/3) calc(0*100%/3),calc(3*100%/3) calc(0*100%/3),calc(0*100%/3) calc(3*100%/3),calc(3*100%/3) calc(3*100%/3),
					calc(0*100%/3) 50%,calc(3*100%/3) 50%,50% calc(0*100%/3),50% calc(3*100%/3)}
		}
	</style>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#generate-ai-description').on('click', function(e) {
                e.preventDefault();

                // Disable button and show loader overlay
                $('#generate-ai-description').attr('disabled', 'disabled').text('Generating...');
                $('#ai-description-status').text('');
                $('#ai-description-loader').fadeIn(); // Show loader

                // Send AJAX request
                var data = {
                    action: 'generate_ai_art_description_ajax',
                    product_id: <?php echo $post->ID; ?>,
                    security: '<?php echo wp_create_nonce('generate-ai-description'); ?>'
                };

                $.post(ajaxurl, data, function(response) {
					// Hide loader when response is received
					$('#ai-description-loader').fadeOut();

					// Handle response
					$('#generate-ai-description').removeAttr('disabled').text('Generate AI Description');
					if (response.success) {
						$('#ai-description-status').text('AI Description generated successfully! Refreshing the page...');
						setTimeout(function() {
							window.location.reload(); // Reload the page on success
						}, 2000); // Optional delay before reloading (2 seconds)
					} else {
						$('#ai-description-status').text('Failed to generate AI description.');
					}
                });
            });
        });
    </script>
    <?php
}
