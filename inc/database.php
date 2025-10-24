<?php
/**
 * Database functions for Subscription Management Plugin
 */

// Create database tables
function create_subscription_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    $plans_table = $wpdb->prefix . 'subscription_management_plans';
    
    // Plans table (replaces custom post type for plans)
    $plans_sql = "CREATE TABLE $plans_table (
        id int(11) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        slug varchar(255) NOT NULL,
        description longtext NULL,
        amount decimal(10,2) NOT NULL DEFAULT 0.00,
        expiry_in_months int(11) NOT NULL DEFAULT 1,
        is_trial tinyint(1) NOT NULL DEFAULT 0,
        sequence int(11) NOT NULL DEFAULT 0,
        status varchar(20) NOT NULL DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug),
        KEY status (status),
        KEY sequence (sequence)
    ) $charset_collate;";
    
    // Subscriptions table
    $subscriptions_table = $wpdb->prefix . 'subscription_management_subscriptions';
    $subscriptions_sql = "CREATE TABLE $subscriptions_table (
        id int(11) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        plan_id int(11) NOT NULL,
        plan_amount decimal(10,2) NOT NULL,
        plan_expiry_in_months int(11) NOT NULL,
        currency_code varchar(10) DEFAULT 'INR',
        is_gst_applied tinyint(2) NOT NULL DEFAULT 0 COMMENT '1 = GST applied, 0 = GST not applied',
        gst_percentage int(11) DEFAULT NULL,
        gst_amount decimal(10,2) DEFAULT NULL,
        base_amount decimal(10,2) NOT NULL,
        amount_paid decimal(10,2) NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'active',
        processing_status tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Used plan, 0 = Unused plan',
        is_active_plan tinyint(2) NOT NULL DEFAULT 1 COMMENT '1 = Active plan, 0 = Future active plan, -1 = Expired plan',
        payment_gateway varchar(50) NOT NULL DEFAULT 'razorpay',
        payment_id varchar(255) DEFAULT NULL,
        order_id varchar(255) DEFAULT NULL,
        payment_status varchar(50) DEFAULT NULL,
        remark varchar(255) DEFAULT NULL,
        subscribed_on datetime DEFAULT NULL,
        subscription_starts_on datetime DEFAULT NULL,
        subscription_ends_on datetime DEFAULT NULL,
        renewal_reminder_sent tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = Renewal reminder sent, 0 = Not sent',
        created_by int(11) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        
        KEY plan_id (plan_id),
        KEY status (status),
        KEY is_active_plan (is_active_plan)
    ) $charset_collate;";
    
    // Transactions table
    $transactions_table = $wpdb->prefix . 'subscription_management_transactions';
    $transactions_sql = "CREATE TABLE $transactions_table (
        id int(11) NOT NULL AUTO_INCREMENT,
        plan_id int(11) DEFAULT NULL,
        subscription_id int(11) DEFAULT NULL,
        user_id int(11) NOT NULL,
        amount decimal(10,2) NOT NULL,
        currency varchar(3) NOT NULL DEFAULT 'INR',
        payment_gateway varchar(50) NOT NULL,
        payment_id varchar(255) NOT NULL,
        payment_status varchar(50) NOT NULL,
        gateway_response text DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY plan_id (plan_id),
        KEY subscription_id (subscription_id),
        KEY user_id (user_id),
        KEY payment_id (payment_id),
        KEY payment_status (payment_status)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($plans_sql);
    dbDelta($subscriptions_sql);
    dbDelta($transactions_sql);
}

// Drop all plugin tables (used on deactivation cleanup)
function drop_subscription_tables() {
    global $wpdb;
    $tables = array(
        $wpdb->prefix . 'subscription_management_transactions',
        $wpdb->prefix . 'subscription_management_subscriptions',
        $wpdb->prefix . 'subscription_management_plans'
    );
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }
}

// CRUD: Plans
function smp_create_plan($data) {
    global $wpdb;
    $plans_table = $wpdb->prefix . 'subscription_management_plans';
    $defaults = array(
        'name' => '',
        'slug' => '',
        'description' => '',
        'amount' => 0,
        'expiry_in_months' => 1,
        'is_trial' => 0,
        'sequence' => 0,
        'status' => 'active'
    );
    $data = wp_parse_args($data, $defaults);
    $wpdb->insert($plans_table, array(
        'name' => sanitize_text_field($data['name']),
        'slug' => sanitize_title($data['slug'] ?: $data['name']),
        'description' => wp_kses_post($data['description']),
        'amount' => floatval($data['amount']),
        'expiry_in_months' => intval($data['expiry_in_months']),
        'is_trial' => !empty($data['is_trial']) ? 1 : 0,
        'sequence' => intval($data['sequence']),
        'status' => in_array($data['status'], array('active','inactive')) ? $data['status'] : 'active',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ));
    return $wpdb->insert_id;
}

function smp_update_plan($id, $data) {
    global $wpdb;
    $plans_table = $wpdb->prefix . 'subscription_management_plans';
    $fields = array();
    if (isset($data['name'])) $fields['name'] = sanitize_text_field($data['name']);
    if (isset($data['slug'])) $fields['slug'] = sanitize_title($data['slug']);
    if (isset($data['description'])) $fields['description'] = wp_kses_post($data['description']);
    if (isset($data['amount'])) $fields['amount'] = floatval($data['amount']);
    if (isset($data['expiry_in_months'])) $fields['expiry_in_months'] = intval($data['expiry_in_months']);
    if (isset($data['is_trial'])) $fields['is_trial'] = !empty($data['is_trial']) ? 1 : 0;
    if (isset($data['sequence'])) $fields['sequence'] = intval($data['sequence']);
    if (isset($data['status'])) $fields['status'] = in_array($data['status'], array('active','inactive')) ? $data['status'] : 'active';
    if (empty($fields)) return false;
    $fields['updated_at'] = current_time('mysql');
    return $wpdb->update($plans_table, $fields, array('id' => intval($id))) !== false;
}

function smp_delete_plan($id) {
    global $wpdb;
    $plans_table = $wpdb->prefix . 'subscription_management_plans';
    return $wpdb->delete($plans_table, array('id' => intval($id))) !== false;
}

function smp_get_plan($id) {
    global $wpdb;
    $plans_table = $wpdb->prefix . 'subscription_management_plans';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $plans_table WHERE id = %d", intval($id)), ARRAY_A);
    return $row ? (object)$row : false;
}

// Get subscription statistics
function get_subscription_statistics() {
    global $wpdb;
    
    $subscriptions_table = $wpdb->prefix . 'subscription_management_subscriptions';
    $transactions_table = $wpdb->prefix . 'subscription_management_transactions';
    
    $stats = array();
    
    // Total subscriptions
    $stats['total_subscriptions'] = $wpdb->get_var("SELECT COUNT(*) FROM $subscriptions_table");
    
    // Active subscriptions
    $stats['active_subscriptions'] = $wpdb->get_var("SELECT COUNT(*) FROM $subscriptions_table WHERE status = 'active' AND is_active_plan = 1");
    
    // Total revenue
    $stats['total_revenue'] = $wpdb->get_var("SELECT SUM(amount) FROM $transactions_table WHERE payment_status = 'captured'");
    
    // Recent subscriptions (last 30 days)
    $stats['recent_subscriptions'] = $wpdb->get_var("SELECT COUNT(*) FROM $subscriptions_table WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    
    return $stats;
}

// Get recent subscriptions
function get_recent_subscriptions($limit = 10) {
    global $wpdb;
    
    $subscriptions_table = $wpdb->prefix . 'subscription_management_subscriptions';
    
    $plans_table = $wpdb->prefix . 'subscription_management_plans';
    $subscriptions = $wpdb->get_results($wpdb->prepare("
        SELECT s.*, u.display_name, p.name as plan_name
        FROM $subscriptions_table s
        LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
        LEFT JOIN $plans_table p ON s.plan_id = p.id
        ORDER BY s.created_at DESC
        LIMIT %d
    ", $limit));
    
    return $subscriptions;
}

// Get all subscriptions with pagination
function get_all_subscriptions($page = 1, $per_page = 20) {
    global $wpdb;
    
    $subscriptions_table = $wpdb->prefix . 'subscription_management_subscriptions';
    $offset = ($page - 1) * $per_page;
    
    $plans_table = $wpdb->prefix . 'subscription_management_plans';
    $subscriptions = $wpdb->get_results($wpdb->prepare("
        SELECT s.*, u.display_name, u.user_email, p.name as plan_name
        FROM $subscriptions_table s
        LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
        LEFT JOIN $plans_table p ON s.plan_id = p.id
        ORDER BY s.created_at DESC
        LIMIT %d OFFSET %d
    ", $per_page, $offset));
    
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $subscriptions_table");
    
    return array(
        'subscriptions' => $subscriptions,
        'total' => $total,
        'pages' => ceil($total / $per_page),
        'current_page' => $page
    );
}

// Get transactions with pagination
function get_all_transactions($page = 1, $per_page = 20) {
    global $wpdb;
    
    $transactions_table = $wpdb->prefix . 'subscription_management_transactions';
    $offset = ($page - 1) * $per_page;
    
    $plans_table = $wpdb->prefix . 'subscription_management_plans';
    $subscriptions_table = $wpdb->prefix . 'subscription_management_subscriptions';
    $transactions = $wpdb->get_results($wpdb->prepare(
        "SELECT t.*, u.display_name, u.user_email, COALESCE(pt.name, p.name) as plan_name, COALESCE(t.plan_id, s.plan_id) as resolved_plan_id
        FROM $transactions_table t
        LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
        LEFT JOIN $subscriptions_table s ON t.subscription_id = s.id
        LEFT JOIN $plans_table p ON s.plan_id = p.id
        LEFT JOIN $plans_table pt ON t.plan_id = pt.id
        ORDER BY t.created_at DESC
        LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ));
    
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $transactions_table");
    
    return array(
        'transactions' => $transactions,
        'total' => $total,
        'pages' => ceil($total / $per_page),
        'current_page' => $page
    );
}

// Store subscription
function store_user_subscription($user_id, $plan_id, $plan_amount, $plan_expiry_in_months, $gst_data = null) {
    global $wpdb;
    $subscriptions_table = $wpdb->prefix . 'subscription_management_subscriptions';
    // Compute subscription dates; if early renewal, create a future subscription starting after current ends
    $now_ts = current_time('timestamp');
    $now = current_time('mysql');
    $is_future = false;
    $starts_on_ts = $now_ts;
    // If user has active and early renewal is allowed, start after existing ends
    if (function_exists('user_has_active_subscription') && user_has_active_subscription($user_id) && function_exists('smp_allow_early_renewal') && smp_allow_early_renewal()) {
        // Load active subscription end date
        $active = $wpdb->get_row($wpdb->prepare("SELECT * FROM $subscriptions_table WHERE user_id=%d AND status='active' AND is_active_plan=1 ORDER BY created_at DESC LIMIT 1", $user_id));
        if ($active && !empty($active->subscription_ends_on)) {
            $active_end_ts = strtotime($active->subscription_ends_on);
            if ($active_end_ts && $active_end_ts > $now_ts) {
                $is_future = true;
                $starts_on_ts = $active_end_ts + 1; // start right after current ends
            }
        }
    }
    $starts_on = date('Y-m-d H:i:s', $starts_on_ts);
    $ends_on = date('Y-m-d H:i:s', strtotime("+".intval($plan_expiry_in_months)." months", $starts_on_ts));
    // Handle GST data - if not provided, calculate from plan amount
    $is_gst_applied = 0;
    $gst_percentage = null;
    $gst_amount = null;
    $base_amount = floatval($plan_amount);
    $amount_paid = floatval($plan_amount);
    if ($gst_data && is_array($gst_data)) {
        $is_gst_applied = isset($gst_data['is_gst_applied']) ? intval($gst_data['is_gst_applied']) : 0;
        $gst_percentage = isset($gst_data['gst_percentage']) ? intval($gst_data['gst_percentage']) : null;
        $gst_amount = isset($gst_data['gst_amount']) ? floatval($gst_data['gst_amount']) : null;
        $base_amount = isset($gst_data['base_amount']) ? floatval($gst_data['base_amount']) : floatval($plan_amount);
        $amount_paid = isset($gst_data['amount_paid']) ? floatval($gst_data['amount_paid']) : floatval($plan_amount);
    }
    $result = $wpdb->insert(
        $subscriptions_table,
        array(
            'user_id' => $user_id,
            'plan_id' => $plan_id,
            'plan_amount' => $base_amount, // Store the actual plan amount (base amount)
            'plan_expiry_in_months' => $plan_expiry_in_months,
            'is_gst_applied' => $is_gst_applied,
            'gst_percentage' => $gst_percentage,
            'gst_amount' => $gst_amount,
            'base_amount' => $base_amount,
            'amount_paid' => $amount_paid,
            'status' => $is_future ? 'upcoming' : 'active',
            'processing_status' => $is_future ? 0 : 1,
            'is_active_plan' => $is_future ? 0 : 1,
            'currency_code' => 'INR',
            'created_by' => $user_id,
            'subscribed_on' => $now,
            'subscription_starts_on' => $starts_on,
            'subscription_ends_on' => $ends_on,
            'created_at' => current_time('mysql')
        ),
        // Correct formats so string and datetime fields are saved correctly
        array('%d', '%d', '%f', '%d', '%d', '%d', '%f', '%f', '%f', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s')
    );
    return $result ? $wpdb->insert_id : false;
}

// Store transaction
function store_transaction($subscription_id, $user_id, $amount, $payment_gateway, $payment_id, $payment_status, $gateway_response = null, $plan_id = null) {
    global $wpdb;
    
    $transactions_table = $wpdb->prefix . 'subscription_management_transactions';
    
    $result = $wpdb->insert(
        $transactions_table,
        array(
            'plan_id' => $plan_id,
            'subscription_id' => $subscription_id,
            'user_id' => intval($user_id),
            'amount' => $amount,
            'payment_gateway' => $payment_gateway,
            'payment_id' => $payment_id,
            'payment_status' => $payment_status,
            'gateway_response' => $gateway_response,
            'created_at' => current_time('mysql')
        ),
        array('%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s')
    );
    
    return $result ? $wpdb->insert_id : false;
}
