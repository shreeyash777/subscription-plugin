<?php
/**
 * Helper functions for Subscription Management Plugin
 */
// Get payment gateway settings
function get_payment_gateway_settings($gateway = 'razorpay') {
    $settings = array();
    
    switch ($gateway) {
        case 'razorpay':
            $settings = array(
                'key_id' => get_option('subscription_management_razorpay_key_id', ''),
                'key_secret' => get_option('subscription_management_razorpay_key_secret', ''),
                'webhook_secret' => get_option('subscription_management_razorpay_webhook_secret', ''),
            );
            break;
        case 'stripe':
            $settings = array(
                'publishable_key' => get_option('subscription_management_stripe_publishable_key', ''),
                'secret_key' => get_option('subscription_management_stripe_secret_key', ''),
                'webhook_secret' => get_option('subscription_management_stripe_webhook_secret', ''),
            );
            break;
        case 'paypal':
            $settings = array(
                'client_id' => get_option('subscription_management_paypal_client_id', ''),
                'client_secret' => get_option('subscription_management_paypal_client_secret', ''),
                'sandbox_mode' => get_option('subscription_management_paypal_sandbox_mode', true)
            );
            break;
    }
    
    return $settings;
}

// Update payment gateway settings
function update_payment_gateway_settings($gateway, $settings) {
    switch ($gateway) {
        case 'razorpay':
            update_option('subscription_management_razorpay_key_id', sanitize_text_field($settings['key_id']));
            update_option('subscription_management_razorpay_key_secret', sanitize_text_field($settings['key_secret']));
            update_option('subscription_management_razorpay_webhook_secret', sanitize_text_field($settings['webhook_secret']));
            break;
        case 'stripe':
            update_option('subscription_management_stripe_publishable_key', sanitize_text_field($settings['publishable_key']));
            update_option('subscription_management_stripe_secret_key', sanitize_text_field($settings['secret_key']));
            update_option('subscription_management_stripe_webhook_secret', sanitize_text_field($settings['webhook_secret']));
            break;
        case 'paypal':
            update_option('subscription_management_paypal_client_id', sanitize_text_field($settings['client_id']));
            update_option('subscription_management_paypal_client_secret', sanitize_text_field($settings['client_secret']));
            update_option('subscription_management_paypal_sandbox_mode', (bool) $settings['sandbox_mode']);
            break;
    }
}

// Format currency
function format_currency($amount, $currency = 'INR') {
    switch ($currency) {
        case 'INR':
            return '₹ ' . number_format($amount, 2);
        case 'USD':
            return '$' . number_format($amount, 2);
        case 'EUR':
            return '€' . number_format($amount, 2);
        default:
            return $currency . ' ' . number_format($amount, 2);
    }
}

// Format date
function format_date($date, $format = 'M j, Y') {
    return date($format, strtotime($date));
}

// Get subscription status badge
function get_subscription_status_badge($status) {
    $badges = array(
        'active' => '<span class="badge bg-success">Active</span>',
        'inactive' => '<span class="badge bg-secondary">Inactive</span>',
        'expired' => '<span class="badge bg-danger">Expired</span>',
        'cancelled' => '<span class="badge bg-warning">Cancelled</span>',
        'pending' => '<span class="badge bg-info">Pending</span>',
        'upcoming' => '<span class="badge bg-info">Upcoming</span>'
    );
    
    return isset($badges[$status]) ? $badges[$status] : '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
}

// Get payment status badge
function get_payment_status_badge($status) {
    $badges = array(
        'captured' => '<span class="badge bg-success">Captured</span>',
        'success' => '<span class="badge bg-success">Success</span>',
        'failed' => '<span class="badge bg-danger">Failed</span>',
        'pending' => '<span class="badge bg-warning">Pending</span>',
        'cancelled' => '<span class="badge bg-secondary">Cancelled</span>',
        'refunded' => '<span class="badge bg-info">Refunded</span>'
    );
    
    return isset($badges[$status]) ? $badges[$status] : '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
}

// Check if user has active subscription
function user_has_active_subscription($user_id) {
    global $wpdb;
    
    $subscriptions_table = $wpdb->prefix . 'subscription_management_subscriptions';
    
    $count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) 
        FROM $subscriptions_table 
        WHERE user_id = %d 
        AND status = 'active' 
        AND is_active_plan = 1 
        AND processing_status = 1
    ", $user_id));
    
    return $count > 0;
}

// Get user's active subscription
function get_user_active_subscription($user_id) {
    global $wpdb;
    
    $subscriptions_table = $wpdb->prefix . 'subscription_management_subscriptions';
    $plans_table = $wpdb->prefix . 'subscription_management_plans';
    
    $subscription = $wpdb->get_row($wpdb->prepare("
        SELECT s.*, p.name as plan_name, p.description as plan_description, p.amount as plan_amount, p.expiry_in_months as plan_expiry_in_months
        FROM $subscriptions_table s
        LEFT JOIN $plans_table p ON s.plan_id = p.id
        WHERE s.user_id = %d 
        AND s.status = 'active' 
        AND s.is_active_plan = 1 
        AND s.processing_status = 1
        ORDER BY s.created_at DESC
        LIMIT 1
    ", $user_id));
    
    return $subscription;
}

// Get user's all subscriptions
function get_user_all_subscriptions($user_id) {
    global $wpdb;
    
    $subscriptions_table = $wpdb->prefix . 'subscription_management_subscriptions';
    $plans_table = $wpdb->prefix . 'subscription_management_plans';
    
    $subscriptions = $wpdb->get_results($wpdb->prepare("
        SELECT s.*, p.name as plan_name, p.description as plan_description, p.amount as plan_amount, p.expiry_in_months as plan_expiry_in_months
        FROM $subscriptions_table s
        LEFT JOIN $plans_table p ON s.plan_id = p.id
        WHERE s.user_id = %d 
        ORDER BY s.created_at DESC
    ", $user_id), ARRAY_A);
    return $subscriptions;
}

// Build a normalized subscription payload for external use (e.g., theme APIs)
function smp_build_subscription_payload($subscription_row) {
    if (!$subscription_row) return null;
    return array(
        'id' => intval($subscription_row->id),
        'user_id' => intval($subscription_row->user_id),
        
        'plan' => array(
            'id' => intval($subscription_row->plan_id),
            'name' => isset($subscription_row->plan_name) ? strval($subscription_row->plan_name) : null,
            'description' => isset($subscription_row->plan_description) ? strval($subscription_row->plan_description) : null,
            'amount' => isset($subscription_row->plan_amount) ? floatval($subscription_row->plan_amount) : floatval($subscription_row->plan_amount),
            'expiry_in_months' => isset($subscription_row->plan_expiry_in_months) ? intval($subscription_row->plan_expiry_in_months) : null,
        ),
        'status' => strval($subscription_row->status),
        'payment' => array(
            'payment_id' => isset($subscription_row->payment_id) ? strval($subscription_row->payment_id) : null,
            'payment_status' => isset($subscription_row->payment_status) ? strval($subscription_row->payment_status) : null,
        ),
        'created_at' => strval($subscription_row->created_at),
        'updated_at' => isset($subscription_row->updated_at) ? strval($subscription_row->updated_at) : null,
    );
}

// Log subscription activity
function log_subscription_activity($message, $level = 'info') {
    $log_file = SUBSCRIPTION_MANAGEMENT_PLUGIN_PATH . 'logs/subscription.log';
    $log_entry = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $message . PHP_EOL;
    
    // Ensure logs directory exists
    $logs_dir = dirname($log_file);
    if (!file_exists($logs_dir)) {
        wp_mkdir_p($logs_dir);
    }
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// Get subscription plans from custom table
function get_subscription_plans() {
    global $wpdb;
    $plans_table = $wpdb->prefix . 'subscription_management_plans';
    $rows = $wpdb->get_results("SELECT * FROM $plans_table WHERE status = 'active' ORDER BY sequence ASC", ARRAY_A);
    return array_map(function($r){ return (object) $r; }, $rows);
}

// Get subscription plan by ID from custom table
function get_subscription_plan($plan_id) {
    global $wpdb;
    $plans_table = $wpdb->prefix . 'subscription_management_plans';
    $plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM $plans_table WHERE id = %d", $plan_id), ARRAY_A);
    return $plan ? (object) $plan : false;
}

// Sanitize subscription data
function sanitize_subscription_data($data) {
    $sanitized = array();
    
    if (isset($data['user_id'])) {
        $sanitized['user_id'] = intval($data['user_id']);
    }
    
    if (isset($data['plan_id'])) {
        $sanitized['plan_id'] = intval($data['plan_id']);
    }
    
    if (isset($data['plan_amount'])) {
        $sanitized['plan_amount'] = floatval(base64_decode($data['plan_amount']));
    }
    
    if (isset($data['plan_expiry_in_months'])) {
        $sanitized['plan_expiry_in_months'] = intval(base64_decode($data['plan_expiry_in_months']));
    }
    // Allow optional notes field (JSON string or object). We'll normalize to an associative array.
    if (isset($data['notes'])) {
        $notes = $data['notes'];
        if (is_string($notes) && strlen($notes) > 0) {
            // Try decode JSON safely
            $decoded = json_decode($notes, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $sanitized['notes'] = array_map('sanitize_text_field', $decoded);
            } else {
                // If not JSON, store as raw string in notes._raw
                $sanitized['notes'] = array('_raw' => sanitize_text_field($notes));
            }
        } elseif (is_array($notes)) {
            $sanitized['notes'] = array_map('sanitize_text_field', $notes);
        } else {
            // unsupported format, drop
            $sanitized['notes'] = null;
        }
    }
    // Intentionally ignore project-specific fields like referral codes here
    
    return $sanitized;
}

/** Early renewal helpers */
function smp_allow_early_renewal() {
    return (bool) get_option('smp_allow_early_renewal', false);
}

function smp_early_renewal_days() {
    $days = intval(get_option('smp_early_renewal_days', 0));
    return $days > 0 ? $days : 0;
}

// Max number of future (upcoming) subscriptions a user can hold
function smp_max_future_subscriptions() {
    $n = intval(get_option('smp_max_future_subscriptions', 1));
    return $n >= 0 ? $n : 0;
}

// Returns true if user can purchase a new plan now (new or renewal)
function smp_user_can_purchase_subscription($user_id) {
    // If no active subscription, can purchase
    if (!user_has_active_subscription($user_id)) return true;

    // If early renewal disabled, block when active exists
    if (!smp_allow_early_renewal()) return false;

    // Check remaining days on active subscription and compare window
    global $wpdb;
    $subscriptions_table = $wpdb->prefix . 'subscription_management_subscriptions';
    $active = $wpdb->get_row($wpdb->prepare("SELECT * FROM $subscriptions_table WHERE user_id=%d AND status='active' AND is_active_plan=1 ORDER BY created_at DESC LIMIT 1", $user_id));
    if (!$active) return true;

    // Compute end date = created_at + expiry_in_months
    $now = time();
    $start = strtotime($active->subscription_starts_on);
    $months = intval($active->plan_expiry_in_months);
    $end_ts = strtotime($active->subscription_ends_on);

    $days_remaining = ceil( max(0, ($end_ts - $now)) / DAY_IN_SECONDS );

    if ($days_remaining > smp_early_renewal_days()) {
        return false;
    }

    // Also enforce future (upcoming) subscription limit
    $max_future = smp_max_future_subscriptions();
    if ($max_future === 0) {
        // Not allowed to hold any future subscriptions
        return false;
    }
    $future_count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $subscriptions_table WHERE user_id=%d AND status='upcoming' AND is_active_plan=0", $user_id)));
    return $future_count < $max_future;
}

/**
 * Email System Functions
 */

// Configure PHPMailer based on plugin settings
add_action('phpmailer_init', 'smp_configure_phpmailer');
function smp_configure_phpmailer($phpmailer) {
    // Only configure if email is enabled
    if (!get_option('smp_email_enabled', false)) {
        return;
    }
    
    // Configure SMTP settings
    $phpmailer->isSMTP();
    $phpmailer->Host = get_option('smp_smtp_host', '');
    $phpmailer->SMTPAuth = get_option('smp_smtp_auth', true);
    $phpmailer->Port = get_option('smp_smtp_port', 587);
    $phpmailer->Username = get_option('smp_smtp_username', '');
    $phpmailer->Password = get_option('smp_smtp_password', '');
    $phpmailer->SMTPSecure = get_option('smp_smtp_encryption', 'tls');
    
    // Set from name and email
    $phpmailer->From = get_option('smp_email_from_email', get_option('admin_email'));
    $phpmailer->FromName = get_option('smp_email_from_name', get_bloginfo('name'));
}

// Get email template with theme override support
function smp_get_email_template($template_name, $variables = array()) {
    // Allow theme override: wp-content/themes/<theme>/subscription-management-plugin/email-templates/
    $theme_template = trailingslashit(get_stylesheet_directory()) . 'subscription-management-plugin/email-templates/' . $template_name;
    $theme_template = apply_filters('smp_locate_email_template', $theme_template, $template_name);
    
    if (file_exists($theme_template)) {
        $template_path = $theme_template;
    } else {
        // Fallback to plugin template
        $plugin_template = SUBSCRIPTION_MANAGEMENT_PLUGIN_PATH . 'email-templates/' . $template_name;
        if (file_exists($plugin_template)) {
            $template_path = $plugin_template;
        } else {
            return false;
        }
    }
    
    // Read template content
    $content = file_get_contents($template_path);
    
    // Replace variables
    $default_variables = array(
        'site_name' => get_bloginfo('name'),
        'site_url' => home_url(),
        'current_year' => date('Y'),
        'admin_email' => get_option('admin_email')
    );
    
    $variables = array_merge($default_variables, $variables);
    
    foreach ($variables as $key => $value) {
        $content = str_replace('{' . $key . '}', $value, $content);
    }
    
    return $content;
}

// Send subscription success email
function smp_send_subscription_success_email($user_id, $subscription_data) {
    if (!get_option('smp_email_enabled', false)) {
        return false;
    }
    
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    $plan = get_subscription_plan($subscription_data['plan_id']);
    if (!$plan) {
        return false;
    }
    
    $variables = array(
        'user_name' => $user->display_name,
        'user_email' => $user->user_email,
        'plan_name' => $plan->name,
        'plan_amount' => format_currency($plan->amount),
        'plan_duration' => $plan->expiry_in_months . ' ' . _n('month', 'months', $plan->expiry_in_months, 'subscription-management-plugin'),
        'start_date' => format_date($subscription_data['created_at']),
        'end_date' => format_date($subscription_data['subscription_ends_on'])
    );
    
    $subject = get_option('smp_success_email_subject', 'Subscription Successful - {site_name}');
    $subject = str_replace(array('{site_name}', '{user_name}', '{plan_name}'), 
                         array(get_bloginfo('name'), $user->display_name, $plan->name), $subject);
    
    $message = smp_get_email_template('subscription-success.html', $variables);
    
    if (!$message) {
        return false;
    }
    
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    return wp_mail($user->user_email, $subject, $message, $headers);
}

// Send renewal reminder email
function smp_send_renewal_reminder_email($user_id, $subscription_data, $days_remaining) {
    if (!get_option('smp_email_enabled', false)) {
        return false;
    }
    
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }

    $plan = get_subscription_plan($subscription_data['plan_id']);
    if (!$plan) {
        return false;
    }
    
    $variables = array(
        'user_name' => $user->display_name,
        'user_email' => $user->user_email,
        'plan_name' => $plan->name,
        'plan_amount' => format_currency($plan->amount),
        'plan_duration' => $plan->expiry_in_months . ' ' . _n('month', 'months', $plan->expiry_in_months, 'subscription-management-plugin'),
        'end_date' => format_date($subscription_data['subscription_ends_on']),
        'days_remaining' => $days_remaining,
        'renewal_url' => home_url('/subscription')
    );
    
    $subject = get_option('smp_renewal_email_subject', 'Subscription Renewal Reminder - {site_name}');
    $subject = str_replace(array('{site_name}', '{user_name}', '{plan_name}', '{days_remaining}'), 
                         array(get_bloginfo('name'), $user->display_name, $plan->name, $days_remaining), $subject);    
    $message = smp_get_email_template('subscription-renewal.html', $variables);
    
    if (!$message) {
        return false;
    }
    
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    return wp_mail($user->user_email, $subject, $message, $headers);
}

// Test email AJAX handler
add_action('wp_ajax_smp_test_email', 'smp_handle_test_email');
function smp_handle_test_email() {
    check_ajax_referer('smp_test_email', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Unauthorized', 'subscription-management-plugin')));
    }
    
    // check email address from request, else show error
    $to_email = isset($_POST['test_email']) ? sanitize_email($_POST['test_email']) : '';
    if (!is_email($to_email)){
        wp_send_json_error(array('message' => __('Enter email address.', 'subscription-management-plugin')));
    }
    $subject = 'Test Email';
    $message = '<h2>Test Email</h2><p>This is a test email to verify your email configuration is working correctly.</p><p>If you received this email, your email settings are properly configured!</p>';
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    $sent = wp_mail($to_email, $subject, $message, $headers);
    
    if ($sent) {
        wp_send_json_success(array('message' => __('Test email sent successfully!', 'subscription-management-plugin')));
    } else {
        wp_send_json_error(array('message' => __('Failed to send test email. Please check your email settings.', 'subscription-management-plugin')));
    }
}

// Send subscription success email when subscription is created
add_action('smp_subscription_created', 'smp_send_subscription_created_email', 10, 2);
function smp_send_subscription_created_email($subscription_id, $subscription_data) {
    if (!get_option('smp_email_enabled', false)) {
        return;
    }
    
    // Get the full subscription data from database
    global $wpdb;
    $subscriptions_table = $wpdb->prefix . 'subscription_management_subscriptions';
    $subscription = $wpdb->get_row($wpdb->prepare("SELECT * FROM $subscriptions_table WHERE id = %d", $subscription_id));
    
    if (!$subscription) {
        return;
    }
    
    // Send success email
    $sent = smp_send_subscription_success_email($subscription->user_id, (array) $subscription);
    
    if ($sent) {
        log_subscription_activity("Subscription success email sent to user {$subscription->user_id} for subscription {$subscription_id}");
    } else {
        log_subscription_activity("Failed to send subscription success email to user {$subscription->user_id} for subscription {$subscription_id}", 'error');
    }
}
