<?php
/**
 * CRON Jobs for Subscription Management Plugin
 * 
 * This file contains CRON functions that should be called externally
 * on production sites to handle subscription expiry and renewal reminders.
 * 
 * For production sites, add a CRON job that calls this file:
 * Example: Every 5 minutes - curl -s https://yoursite.com/wp-content/plugins/subscription-management-plugin/cron-jobs/subscription-cron.php >/dev/null 2>&1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // Load WordPress if not already loaded
    // $wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
    $wp_load_path = dirname(__FILE__, 5) . '/wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once $wp_load_path;
    } else {
        die('WordPress not found');
    }
}

// Ensure plugin functions are loaded
if (!function_exists('log_subscription_activity')) {
    die('Subscription Management Plugin not loaded');
}

/**
 * Handle subscription expiry CRON job
 * Marks subscriptions as expired when their end date has passed
 */
function smp_handle_subscription_expiry() {
    global $wpdb;
    $subscriptions_table = $wpdb->prefix . 'subscription_management_subscriptions';
    $now = current_time('mysql');

    // Find subscriptions that have expired (select rows so we can handle follow-up activation per user)
    $expired_subs = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $subscriptions_table 
        WHERE subscription_ends_on IS NOT NULL 
        AND subscription_ends_on < %s 
        AND is_active_plan <> -1
    ", $now));

    $expired_count = 0;
    if (!empty($expired_subs)) {
        foreach ($expired_subs as $sub) {
            // Mark this subscription as expired
            $updated = $wpdb->update(
                $subscriptions_table,
                array('is_active_plan' => -1, 'status' => 'expired'),
                array('id' => $sub->id),
                array('%d', '%s'),
                array('%d')
            );

            if ($updated !== false) {
                $expired_count++;
                log_subscription_activity("CRON: Marked subscription {$sub->id} (user {$sub->user_id}) as expired");

                // After expiring, check for any upcoming purchased subscription for the same user
                // that starts after this subscription ends and activate the earliest one
                $future_sub = $wpdb->get_row($wpdb->prepare("
                    SELECT * FROM $subscriptions_table 
                    WHERE user_id = %d 
                    AND status = 'upcoming' 
                    AND is_active_plan = 0 
                    AND processing_status = 0 
                    AND subscription_starts_on >= %s 
                    ORDER BY subscription_starts_on ASC 
                    LIMIT 1
                ", $sub->user_id, $sub->subscription_ends_on));

                if ($future_sub) {
                    $act = $wpdb->update(
                        $subscriptions_table,
                        array('is_active_plan' => 1, 'processing_status' => 1, 'status' => 'active'),
                        array('id' => $future_sub->id),
                        array('%d', '%d', '%s'),
                        array('%d')
                    );
                    if ($act !== false) {
                        log_subscription_activity("CRON: Activated future subscription {$future_sub->id} for user {$future_sub->user_id} (replaced expired {$sub->id})");
                    } else {
                        log_subscription_activity("CRON: Failed to activate future subscription {$future_sub->id} for user {$future_sub->user_id}", 'error');
                    }
                }
            } else {
                log_subscription_activity("CRON: Failed to mark subscription {$sub->id} as expired", 'error');
            }
        }
    }

    if ($expired_count > 0) {
        log_subscription_activity("CRON: Total subscriptions marked as expired: {$expired_count}");
    }

    return $expired_count;
}

/**
 * Handle renewal reminder CRON job
 * Sends renewal reminder emails to users whose subscriptions are expiring soon
 */
function smp_handle_renewal_reminders() {
    if (!get_option('smp_email_enabled', false)) {
        return 0;
    }
    
    $early_renewal_days = intval(get_option('smp_early_renewal_days', 0));
    if ($early_renewal_days <= 0) {
        return 0;
    }

    global $wpdb;
    $subscriptions_table = $wpdb->prefix . 'subscription_management_subscriptions';
    
    // Find subscriptions that are expiring within the renewal window
    $reminder_date = date('Y-m-d H:i:s', strtotime("+{$early_renewal_days} days"));
    $now = current_time('mysql');
    $subscriptions = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $subscriptions_table 
        WHERE status = 'active' 
        AND is_active_plan = 1 
        AND processing_status = 1
        AND subscription_ends_on <= %s 
        AND subscription_ends_on > %s
        AND renewal_reminder_sent = 0
    ", $reminder_date, $now), ARRAY_A);
    $emails_sent = 0;
    foreach ($subscriptions as $subscription) {
        $end_timestamp = strtotime($subscription['subscription_ends_on']);
        $now_timestamp = time();
        $days_remaining = ceil(($end_timestamp - $now_timestamp) / DAY_IN_SECONDS);
        
        if ($days_remaining <= $early_renewal_days && $days_remaining > 0) {
            // Send renewal reminder email
            if (function_exists('smp_send_renewal_reminder_email')) {
                $sent = smp_send_renewal_reminder_email($subscription['user_id'], $subscription, $days_remaining);
                if ($sent) {
                    // Mark reminder as sent
                    $wpdb->update(
                        $subscriptions_table,
                        array('renewal_reminder_sent' => 1),
                        array('id' => $subscription['id']),
                        array('%d'),
                        array('%d')
                    );
                    log_subscription_activity("Renewal reminder sent to user {$subscription->user_id} for subscription {$subscription->id}");
                    $emails_sent++;
                }
            }
        }
    }
    
    return $emails_sent;
}

// Handle CRON job execution
if (isset($_GET['action'])) {
    $action = sanitize_text_field($_GET['action']);
    
    // Check for admin authentication if admin_only flag is set
    $admin_only = get_option('smp_cron_admin_only', false);
    if ($admin_only && !current_user_can('manage_options')) {
        http_response_code(403);
        die('Access denied. Admin authentication required.');
    }
    
    switch ($action) {
        case 'expire_subscriptions':
            $expired_count = smp_handle_subscription_expiry();
            echo "Expired subscriptions: {$expired_count} <br>";
            break;
            
        case 'send_renewal_reminders':
            $emails_sent = smp_handle_renewal_reminders();
            echo "Renewal reminder emails sent: {$emails_sent} <br>";
            break;
            
        case 'run_all':
            $expired_count = smp_handle_subscription_expiry();
            $emails_sent = smp_handle_renewal_reminders();
            echo "CRON Job Results: <br>";
            echo "Expired subscriptions: {$expired_count} <br>";
            echo "Renewal reminder emails sent: {$emails_sent} <br>";
            break;
            
        default:
            echo "Invalid action. Available actions: expire_subscriptions, send_renewal_reminders, run_all <br>";
    }
} else {
    echo "Date :: ".date('Y-m-d h:i:s')."<br>";
    echo "Subscription Management Plugin CRON Jobs <br>";
    echo "Available actions: <br>";
    echo "- ?action=expire_subscriptions - Mark expired subscriptions <br>";
    echo "- ?action=send_renewal_reminders - Send renewal reminder emails <br>";
    echo "- ?action=run_all - Run both jobs <br>";
    echo "For production sites, set up CRON to call this file with appropriate action. <br>";
}
