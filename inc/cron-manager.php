<?php
/**
 * WordPress Cron Manager for Subscription Management Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize WordPress cron jobs
 */
add_action('init', 'smp_init_cron_jobs');
function smp_init_cron_jobs() {
    // Only initialize if cron is enabled
    if (!get_option('smp_cron_enabled', false)) {
        return;
    }
    
    // Schedule expiry check
    $expiry_frequency = get_option('smp_expiry_cron_frequency', 'hourly');
    if (!wp_next_scheduled('smp_subscription_expiry_check')) {
        if ($expiry_frequency === 'hourly') {
            wp_schedule_event(time(), 'hourly', 'smp_subscription_expiry_check');
        } else {
            $at = get_option('smp_expiry_cron_time', '00:00');
            $next = smp_get_next_daily_timestamp($at);
            if ($next) wp_schedule_event($next, 'daily', 'smp_subscription_expiry_check');
        }
    }
    
    // Schedule renewal reminders
    $renewal_frequency = get_option('smp_renewal_cron_frequency', 'daily');
    if (!wp_next_scheduled('smp_renewal_reminders')) {
        if ($renewal_frequency === 'hourly') {
            wp_schedule_event(time(), 'hourly', 'smp_renewal_reminders');
        } else {
            $at = get_option('smp_renewal_cron_time', '00:00');
            $next = smp_get_next_daily_timestamp($at);
            if ($next) wp_schedule_event($next, 'daily', 'smp_renewal_reminders');
        }
    }
}

/**
 * Handle subscription expiry check
 */
add_action('smp_subscription_expiry_check', 'smp_handle_subscription_expiry_cron');
function smp_handle_subscription_expiry_cron() {
    // Include the cron functions from the existing file
    if (file_exists(SUBSCRIPTION_MANAGEMENT_PLUGIN_PATH . 'cron-jobs/subscription-cron.php')) {
        require_once SUBSCRIPTION_MANAGEMENT_PLUGIN_PATH . 'cron-jobs/subscription-cron.php';
        
        // Call the expiry function
        if (function_exists('smp_handle_subscription_expiry')) {
            $expired_count = smp_handle_subscription_expiry();
            log_subscription_activity("WordPress Cron: Marked {$expired_count} subscriptions as expired");
        }
    }
}

/**
 * Handle renewal reminders
 */
add_action('smp_renewal_reminders', 'smp_handle_renewal_reminders_cron');
function smp_handle_renewal_reminders_cron() {
    // Include the cron functions from the existing file
    if (file_exists(SUBSCRIPTION_MANAGEMENT_PLUGIN_PATH . 'cron-jobs/subscription-cron.php')) {
        require_once SUBSCRIPTION_MANAGEMENT_PLUGIN_PATH . 'cron-jobs/subscription-cron.php';
        
        // Call the renewal function
        if (function_exists('smp_handle_renewal_reminders')) {
            $emails_sent = smp_handle_renewal_reminders();
            log_subscription_activity("WordPress Cron: Sent {$emails_sent} renewal reminder emails");
        }
    }
}

/**
 * Update cron schedules when settings change
 */
add_action('update_option_smp_cron_enabled', 'smp_update_cron_schedules');
add_action('update_option_smp_expiry_cron_frequency', 'smp_update_cron_schedules');
add_action('update_option_smp_expiry_cron_time', 'smp_update_cron_schedules');
add_action('update_option_smp_renewal_cron_frequency', 'smp_update_cron_schedules');
add_action('update_option_smp_renewal_cron_time', 'smp_update_cron_schedules');
function smp_update_cron_schedules() {
    // Clear existing schedules
    wp_clear_scheduled_hook('smp_subscription_expiry_check');
    wp_clear_scheduled_hook('smp_renewal_reminders');
    
    // Reinitialize if cron is enabled
    if (get_option('smp_cron_enabled', false)) {
        smp_init_cron_jobs();
    }
}

/**
 * Test cron functionality
 */
add_action('wp_ajax_smp_test_cron', 'smp_test_cron_handler');
function smp_test_cron_handler() {
    check_ajax_referer('smp_test_cron', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unauthorized', 'subscription-management-plugin')]);
    }
    
    $results = [];
    
    // Test expiry check
    if (file_exists(SUBSCRIPTION_MANAGEMENT_PLUGIN_PATH . 'cron-jobs/subscription-cron.php')) {
        require_once SUBSCRIPTION_MANAGEMENT_PLUGIN_PATH . 'cron-jobs/subscription-cron.php';
        
        if (function_exists('smp_handle_subscription_expiry')) {
            $expired_count = smp_handle_subscription_expiry();
            $results[] = "Expired subscriptions: {$expired_count}";
        }
        
        if (function_exists('smp_handle_renewal_reminders')) {
            $emails_sent = smp_handle_renewal_reminders();
            $results[] = "Renewal reminder emails sent: {$emails_sent}";
        }
    }
    
    // Log test run
    log_subscription_activity("Test cron run: " . implode(', ', $results));
    
    $message = __('Test cron completed successfully!', 'subscription-management-plugin');
    if (!empty($results)) {
        $message .= ' ' . implode(', ', $results);
    }
    
    wp_send_json_success(['message' => $message]);
}

/**
 * Get cron status information
 */
function smp_get_cron_status() {
    $status = [
        'enabled' => get_option('smp_cron_enabled', false),
        'expiry_frequency' => get_option('smp_expiry_cron_frequency', 'hourly'),
        'expiry_time' => get_option('smp_expiry_cron_time', '00:00'),
        'renewal_frequency' => get_option('smp_renewal_cron_frequency', 'daily'),
        'renewal_time' => get_option('smp_renewal_cron_time', '00:00'),
        'next_expiry_check' => wp_next_scheduled('smp_subscription_expiry_check'),
        'next_renewal_check' => wp_next_scheduled('smp_renewal_reminders'),
    ];
    
    return $status;
}

/**
 * Compute next daily timestamp in UTC from a HH:MM time in site timezone
 */
function smp_get_next_daily_timestamp($hhmm) {
    if (!preg_match('/^\d{2}:\d{2}$/', $hhmm)) {
        $hhmm = '00:00';
    }
    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(wp_timezone_string());
    try {
        $now = new DateTime('now', $tz);
        list($h, $m) = array_map('intval', explode(':', $hhmm));
        $next = (new DateTime('today', $tz))->setTime($h, $m);
        if ($next <= $now) {
            $next = $next->modify('+1 day');
        }
        $nextUtc = (clone $next)->setTimezone(new DateTimeZone('UTC'));
        return $nextUtc->getTimestamp();
    } catch (Exception $e) {
        return time() + DAY_IN_SECONDS;
    }
}

/**
 * Clean up cron jobs on plugin deactivation
 */
add_action('subscription_management_plugin_deactivate', 'smp_cleanup_cron_jobs');
function smp_cleanup_cron_jobs() {
    wp_clear_scheduled_hook('smp_subscription_expiry_check');
    wp_clear_scheduled_hook('smp_renewal_reminders');
}
