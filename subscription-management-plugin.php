<?php
/**
 * Plugin Name: Subscription Management Plugin
 * Plugin URI: https://townsol.info
 * Description: A comprehensive subscription management plugin for WordPress with dashboard, plans, transactions, and payment gateway integration.
 * Version: 1.0.0
 * Author: TownSol Team
 * License: GPL v2 or later
 * Text Domain: subscription-management-plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SUBSCRIPTION_MANAGEMENT_PLUGIN_VERSION', '1.0.0');
define('SUBSCRIPTION_MANAGEMENT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SUBSCRIPTION_MANAGEMENT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SUBSCRIPTION_MANAGEMENT_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once SUBSCRIPTION_MANAGEMENT_PLUGIN_PATH . 'inc/admin-menu.php';
require_once SUBSCRIPTION_MANAGEMENT_PLUGIN_PATH . 'inc/database.php';
require_once SUBSCRIPTION_MANAGEMENT_PLUGIN_PATH . 'inc/functions.php';
require_once SUBSCRIPTION_MANAGEMENT_PLUGIN_PATH . 'inc/payment-gateways.php';
require_once SUBSCRIPTION_MANAGEMENT_PLUGIN_PATH . 'inc/shortcodes.php';
require_once SUBSCRIPTION_MANAGEMENT_PLUGIN_PATH . 'inc/cron-manager.php';
require_once SUBSCRIPTION_MANAGEMENT_PLUGIN_PATH . 'rest_api/subscription-api.php';

// Activation hook
register_activation_hook(__FILE__, 'subscription_management_plugin_activate');
function subscription_management_plugin_activate() {
    // Create "Subscription" page if not exists
    $page = get_page_by_path('subscription');
    if (!$page) {
        $page_id = wp_insert_post(array(
            'post_title'     => 'Subscription',
            'post_name'      => 'subscription',
            'post_content'   => '',
            'post_status'    => 'publish',
            'post_type'      => 'page'
        ));
        if ($page_id && !is_wp_error($page_id)) {
            update_post_meta($page_id, '_wp_page_template', 'subscription-plans.php');
        }
    } else {
        update_post_meta($page->ID, '_wp_page_template', 'subscription-plans.php');
    }

    // Create database tables (including plans)
    create_subscription_tables();
    
    // Set default options
    add_option('subscription_management_payment_gateway', 'razorpay');
    add_option('subscription_management_razorpay_key_id', '');
    add_option('subscription_management_razorpay_key_secret', '');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'subscription_management_plugin_deactivate');
function subscription_management_plugin_deactivate() {
    // Remove "Subscription" page
    $page = get_page_by_path('subscription');
    if ($page) {
        wp_delete_post($page->ID, true);
    }
    // Drop custom tables and cleanup options
    if (function_exists('drop_subscription_tables')) {
        drop_subscription_tables();
    }
    delete_option('subscription_management_payment_gateway');
    delete_option('subscription_management_razorpay_key_id');
    delete_option('subscription_management_razorpay_key_secret');
    delete_option('subscription_management_razorpay_webhook_secret');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Initialize plugin
add_action('init', 'subscription_management_plugin_init');
function subscription_management_plugin_init() {
    // Load text domain for translations
    load_plugin_textdomain('subscription-management-plugin', false, dirname(SUBSCRIPTION_MANAGEMENT_PLUGIN_BASENAME) . '/languages');
}

// Register page template shipped within plugin so WP recognizes and assigns it
add_filter('theme_page_templates', function($templates){
    $templates['subscription-plans.php'] = __('Subscription Plans Page Template', 'subscription-management-plugin');
    return $templates;
});

add_filter('template_include', function($template){
    if (is_page()) {
        $page_template = get_page_template_slug();
        if ($page_template === 'subscription-plans.php') {
            // Allow theme override first: wp-content/themes/<theme>/subscription-management-plugin/subscription-plans.php
            $theme_template = trailingslashit(get_stylesheet_directory()) . 'subscription-management-plugin/subscription-plans.php';
            $theme_template = apply_filters('smp_locate_template', $theme_template, 'subscription-plans.php');
            if (file_exists($theme_template)) {
                return $theme_template;
            }
            // Fallback to plugin template
            $plugin_template = SUBSCRIPTION_MANAGEMENT_PLUGIN_PATH . 'pages/subscription-plans.php';
            if (file_exists($plugin_template)) return $plugin_template;
        }
    }
    return $template;
});

// Enqueue admin scripts and styles
add_action('admin_enqueue_scripts', 'subscription_management_admin_scripts');
function subscription_management_admin_scripts($hook) {
    // Only load on our plugin pages
    if (strpos($hook, 'subscription-management') === false) {
        return;
    }
    wp_enqueue_script('jquery');
    // Bootstrap for admin modals
    wp_enqueue_style('bootstrap-5-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
    wp_enqueue_script('bootstrap-5-bundle', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', array('jquery'), null, true);
    // DataTables (Bootstrap 5)
    wp_enqueue_script('datatables-core', 'https://cdn.datatables.net/2.2.2/js/dataTables.min.js', array('jquery'), null, true);
    wp_enqueue_script('datatables-bs5', 'https://cdn.datatables.net/2.2.2/js/dataTables.bootstrap5.min.js', array('datatables-core', 'bootstrap-5-bundle'), null, true);
    wp_enqueue_style('datatables-css', 'https://cdn.datatables.net/2.2.2/css/dataTables.bootstrap5.min.css');
    // Subscription Management Admin Scripts
    wp_enqueue_script('subscription-management-admin', SUBSCRIPTION_MANAGEMENT_PLUGIN_URL . 'js/admin.js', array('jquery'), SUBSCRIPTION_MANAGEMENT_PLUGIN_VERSION, true);
    wp_enqueue_style('subscription-management-admin', SUBSCRIPTION_MANAGEMENT_PLUGIN_URL . 'css/admin.css', array(), SUBSCRIPTION_MANAGEMENT_PLUGIN_VERSION);
    // Localize admin ajax
    wp_localize_script('subscription-management-admin', 'subscription_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('subscription_management_nonce')
    ));
}

// Enqueue frontend scripts and styles
add_action('wp_enqueue_scripts', 'subscription_management_frontend_scripts');
function subscription_management_frontend_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script('subscription-management-frontend', SUBSCRIPTION_MANAGEMENT_PLUGIN_URL . 'js/subscription.js', array('jquery'), SUBSCRIPTION_MANAGEMENT_PLUGIN_VERSION, true);
    wp_enqueue_style('subscription-management-frontend', SUBSCRIPTION_MANAGEMENT_PLUGIN_URL . 'css/frontend.css', array(), SUBSCRIPTION_MANAGEMENT_PLUGIN_VERSION);
    
    // Localize script for AJAX
    wp_localize_script('subscription-management-frontend', 'subscription_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'rest_url' => rest_url('subscription-management/v1'),
        'nonce' => wp_create_nonce('subscription_management_nonce')
    ));

    // Swiper slider for subscription plans
    wp_enqueue_style('swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css');
    wp_enqueue_script('swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js', array(), null, true);
}
