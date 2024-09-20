<?php
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
