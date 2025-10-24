<?php
/**
 * Admin menu and pages for Subscription Management Plugin
 */

/** Add admin menu configuration */
add_action('admin_menu', 'subscription_management_admin_menu');
function subscription_management_admin_menu() {
    add_menu_page(
        __('Subscription Management', 'subscription-management-plugin'),
        __('Subscriptions', 'subscription-management-plugin'),
        'manage_options',
        'subscription-management',
        'subscription_management_dashboard_page',
        'dashicons-money-alt',
        30
    );
    
    add_submenu_page(
        'subscription-management',
        __('Dashboard', 'subscription-management-plugin'),
        __('Dashboard', 'subscription-management-plugin'),
        'manage_options',
        'subscription-management',
        'subscription_management_dashboard_page'
    );
    
    add_submenu_page(
        'subscription-management',
        __('Subscription Plans', 'subscription-management-plugin'),
        __('Plans', 'subscription-management-plugin'),
        'manage_options',
        'subscription-management-plans',
        'subscription_management_plans_page'
    );
    
    add_submenu_page(
        'subscription-management',
        __('Subscriptions', 'subscription-management-plugin'),
        __('Subscriptions', 'subscription-management-plugin'),
        'manage_options',
        'subscription-management-subscriptions',
        'subscription_management_subscriptions_page'
    );
    
    add_submenu_page(
        'subscription-management',
        __('Transactions', 'subscription-management-plugin'),
        __('Transactions', 'subscription-management-plugin'),
        'manage_options',
        'subscription-management-transactions',
        'subscription_management_transactions_page'
    );
    
    add_submenu_page(
        'subscription-management',
        __('Settings', 'subscription-management-plugin'),
        __('Settings', 'subscription-management-plugin'),
        'manage_options',
        'subscription-management-settings',
        'subscription_management_settings_page'
    );
}

// 1. Dashboard page
function subscription_management_dashboard_page() {
    $stats = get_subscription_statistics();
    $recent_subscriptions = get_recent_subscriptions(10);
    ?>
    <div class="wrap">
        <h1><?php _e('Subscription Management Dashboard', 'subscription-management-plugin'); ?></h1>
        <div class="subscription-dashboard-stats">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_subscriptions']); ?></h3>
                        <p><?php _e('Total Subscriptions', 'subscription-management-plugin'); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['active_subscriptions']); ?></h3>
                        <p><?php _e('Active Subscriptions', 'subscription-management-plugin'); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo format_currency($stats['total_revenue']); ?></h3>
                        <p><?php _e('Total Revenue', 'subscription-management-plugin'); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['recent_subscriptions']); ?></h3>
                        <p><?php _e('Recent Subscriptions (30 days)', 'subscription-management-plugin'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="subscription-dashboard-content">
            <div class="dashboard-widgets">
                <div class="widget">
                    <h2><?php _e('Recent Subscriptions', 'subscription-management-plugin'); ?></h2>
                    <div class="recent-subscriptions">
                        <?php if (!empty($recent_subscriptions)): ?>
                            <table id="smp-recent-subscriptions" class="wp-list-table widefat fixed striped smp-datatable">
                                <thead>
                                    <tr>
                                        <th style="text-align: left;"><?php _e('User ID', 'subscription-management-plugin'); ?></th>
                                        <th><?php _e('User', 'subscription-management-plugin'); ?></th>
                                        <th><?php _e('Plan', 'subscription-management-plugin'); ?></th>
                                        <th><?php _e('Amount', 'subscription-management-plugin'); ?></th>
                                        <th><?php _e('Status', 'subscription-management-plugin'); ?></th>
                                        <th><?php _e('Date', 'subscription-management-plugin'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_subscriptions as $subscription): ?>
                                        <tr>
                                            <td style="text-align: left;"><?php echo intval($subscription->user_id); ?></td>
                                            <td><?php echo esc_html($subscription->display_name); ?></td>
                                            <td><?php echo esc_html($subscription->plan_name); ?></td>
                                            <td><?php echo format_currency($subscription->plan_amount); ?></td>
                                            <td><?php echo get_subscription_status_badge($subscription->status); ?></td>
                                            <td><?php echo format_date($subscription->created_at); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p><?php _e('No recent subscriptions found.', 'subscription-management-plugin'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// 2. Subscription Plans page
function subscription_management_plans_page() {
    $plans = get_subscription_plans();
    ?>
    <div class="wrap">
        <h1><?php _e('All Subscription Plans', 'subscription-management-plugin'); ?></h1>
        <div class="plans-header" style="margin-bottom:12px;">
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#smpPlanModal" id="smpAddPlanBtn"><?php _e('Add New Plan', 'subscription-management-plugin'); ?></button>
        </div>
        <div class="subscription-plans-content">
            <?php if (!empty($plans)): ?>
                <table id="smp-plans" class="wp-list-table widefat fixed striped smp-datatable">
                    <thead>
                        <tr>
                            <th><?php _e('Plan Name', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('Amount', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('Duration', 'subscription-management-plugin'); ?></th>
                            <?php if ((bool) get_option('smp_trial_enabled', false)) : ?>
                                <th><?php _e('Trial', 'subscription-management-plugin'); ?></th>
                            <?php endif; ?>
                            <th><?php _e('Sequence', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('Status', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('Actions', 'subscription-management-plugin'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plans as $plan): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($plan->name); ?></strong>
                                    <div class="plan-description"><?php echo wp_trim_words($plan->description, 20); ?></div>
                                </td>
                                <td><?php echo format_currency($plan->amount); ?></td>
                                <td><?php echo intval($plan->expiry_in_months) . ' ' . _n('month', 'months', intval($plan->expiry_in_months), 'subscription-management-plugin'); ?></td>
                                <?php if ((bool) get_option('smp_trial_enabled', false)) : ?>
                                    <td><?php echo intval($plan->is_trial) ? '<span class="badge bg-info text-dark">' . __('Trial', 'subscription-management-plugin') . '</span>' : '-'; ?></td>
                                <?php endif; ?>
                                <td><?php echo intval($plan->sequence); ?></td>
                                <td><?php echo $plan->status === 'active' ? '<span class="badge bg-success">' . __('Active', 'subscription-management-plugin') . '</span>' : '<span class="badge bg-secondary">' . __('Inactive', 'subscription-management-plugin') . '</span>'; ?></td>
                                <td>
                                    <a href="javascript:void(0);" class="btn btn-primary btn-sm smp-edit-plan" data-plan='{"id":<?php echo intval($plan->id); ?>,"name":<?php echo json_encode($plan->name); ?>,"slug":<?php echo json_encode($plan->slug); ?>,"description":<?php echo json_encode($plan->description); ?>,"amount":<?php echo json_encode($plan->amount); ?>,"expiry_in_months":<?php echo intval($plan->expiry_in_months); ?>,"is_trial":<?php echo intval($plan->is_trial); ?>,"sequence":<?php echo intval($plan->sequence); ?>,"status":<?php echo json_encode($plan->status); ?>}'><?php _e('Edit', 'subscription-management-plugin'); ?></a>
                                    <a href="javascript:void(0);" class="btn btn-danger btn-sm smp-delete-plan" data-id="<?php echo intval($plan->id); ?>"><?php _e('Delete', 'subscription-management-plugin'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No subscription plans found.', 'subscription-management-plugin'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
add_action('admin_footer', function(){
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || strpos($screen->id, 'subscription-management') === false) return;
    ?>
    <div class="modal fade" id="smpPlanModal" tabindex="-1" aria-labelledby="smpPlanModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="smpPlanModalLabel"><?php _e('Add Plan', 'subscription-management-plugin'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="smp-plan-form">
                        <input type="hidden" name="id" id="smp_plan_id" value="">
                        <div class="row" style="margin-bottom:12px;">
                            <div class="col-md-6">
                                <label class="form-label"><?php _e('Name', 'subscription-management-plugin'); ?> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" id="smp_plan_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?php _e('Slug', 'subscription-management-plugin'); ?> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="slug" id="smp_plan_slug">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php _e('Description', 'subscription-management-plugin'); ?></label>
                            <?php wp_editor('', 'smp_plan_description', array('textarea_name' => 'description', 'media_buttons' => false, 'teeny' => true, 'textarea_rows' => 8)); ?>
                        </div>
                        <div class="row" style="margin-bottom:12px;">
                            <div class="col-md-4">
                                <label class="form-label"><?php _e('Amount', 'subscription-management-plugin'); ?> <span class="text-danger">*</span></label>
                                <input type="number" min="0" class="form-control" name="amount" id="smp_plan_amount" value="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?php _e('Expiry (months)', 'subscription-management-plugin'); ?> <span class="text-danger">*</span></label>
                                <input type="number" min="1" class="form-control" name="expiry_in_months" id="smp_plan_expiry" value="1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?php _e('Sequence', 'subscription-management-plugin'); ?> <span class="text-danger">*</span></label>
                                <input type="number" min="0" class="form-control" name="sequence" id="smp_plan_sequence" value="0">
                            </div>
                        </div>
                        <?php if ((bool) get_option('smp_trial_enabled', false)) : ?>
                            <div class="mb-3">
                                <label class="form-label">
                                    <input type="checkbox" name="is_trial" id="smp_plan_is_trial" value="1"> <?php _e('Mark as Trial Plan', 'subscription-management-plugin'); ?>
                                </label>
                            </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label"><?php _e('Status', 'subscription-management-plugin'); ?></label>
                            <select class="form-select" name="status" id="smp_plan_status">
                                <option value="active"><?php _e('Active', 'subscription-management-plugin'); ?></option>
                                <option value="inactive"><?php _e('Inactive', 'subscription-management-plugin'); ?></option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-danger btn-sm px-5" data-bs-dismiss="modal"><?php _e('Close', 'subscription-management-plugin'); ?></button>
                    <button type="button" class="btn btn-primary btn-sm px-5" id="smpSavePlanBtn"><?php _e('Save', 'subscription-management-plugin'); ?></button>
                </div>
            </div>
        </div>
    </div>
    <?php
}); // Modal markup in admin footer on plugin screens
add_action('wp_ajax_smp_save_plan', function(){
    check_ajax_referer('subscription_management_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized'], 403);
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $data = array(
        'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
        'slug' => isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '',
        'description' => isset($_POST['description']) ? wp_kses_post($_POST['description']) : '',
        'amount' => isset($_POST['amount']) ? floatval($_POST['amount']) : 0,
        'expiry_in_months' => isset($_POST['expiry_in_months']) ? intval($_POST['expiry_in_months']) : 1,
            'is_trial' => isset($_POST['is_trial']) ? 1 : 0,
        'sequence' => isset($_POST['sequence']) ? intval($_POST['sequence']) : 0,
        'status' => isset($_POST['status']) && in_array($_POST['status'], array('active','inactive')) ? $_POST['status'] : 'active',
    );
    if ($id > 0) {
        $ok = smp_update_plan($id, $data);
        if ($ok) wp_send_json_success(['message' => 'Updated']);
        wp_send_json_error(['message' => 'Update failed']);
    } else {
        $new_id = smp_create_plan($data);
        if ($new_id) wp_send_json_success(['message' => 'Created', 'id' => $new_id]);
        wp_send_json_error(['message' => 'Create failed']);
    }
}); // Admin AJAX handlers for plan CRUD
add_action('wp_ajax_smp_delete_plan', function(){
    check_ajax_referer('subscription_management_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized'], 403);
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) wp_send_json_error(['message' => 'Invalid ID']);
    $ok = smp_delete_plan($id);
    if ($ok) wp_send_json_success(['message' => 'Deleted']);
    wp_send_json_error(['message' => 'Delete failed']);
}); // Admin AJAX handler for plan delete

// 3. All Subscriptions page
function subscription_management_subscriptions_page() {
    // CSV export handler
    if (isset($_GET['smp_export']) && $_GET['smp_export'] === 'subscriptions' && current_user_can('manage_options')) {
        check_admin_referer('smp_export_subscriptions');

        // Prevent any previous HTML output
        if (ob_get_length()) ob_end_clean();

        $all = get_all_subscriptions(1, PHP_INT_MAX);
        $rows = isset($all['subscriptions']) ? $all['subscriptions'] : array();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=subscriptions-export-' . date('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        
        fputcsv($out, array('ID','User ID','User','Email','Plan','Amount','Currency','Status','Active State','Processing','Starts On','Ends On','Order ID','Created'));
        
        foreach ($rows as $r) {
            fputcsv($out, array(
                intval($r->id),
                intval($r->user_id),
                $r->display_name,
                $r->user_email,
                $r->plan_name,
                $r->plan_amount,
                $r->currency_code ? $r->currency_code : 'INR',
                $r->status,
                intval($r->is_active_plan),
                intval($r->processing_status),
                $r->subscription_starts_on,
                $r->subscription_ends_on,
                $r->order_id,
                $r->created_at,
            ));
        }
        fclose($out);
        exit;
    }
    $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    $data = get_all_subscriptions($page, 1000);
    ?>
    <div class="wrap">
        <h1><?php _e('All Subscriptions', 'subscription-management-plugin'); ?></h1>
        <?php if (!empty($data['subscriptions'])): ?>
            <p>
                <a href="<?php echo esc_url( wp_nonce_url( admin_url('admin.php?page=subscription-management-subscriptions&smp_export=subscriptions'), 'smp_export_subscriptions') ); ?>" class="btn btn-primary btn-sm"><?php _e('Export CSV', 'subscription-management-plugin'); ?></a>
            </p>
        <?php endif; ?>
        <div class="subscriptions-content">
            <?php if (!empty($data['subscriptions'])): ?>
                <table id="smp-subscriptions" class="wp-list-table widefat fixed striped smp-datatable">
                    <thead>
                        <tr>
                            <th style="text-align: left;"><?php _e('User ID', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('User', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('Email', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('Plan', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('Amount', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('Created', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('Order ID', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('Currency', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('Starts On', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('Ends On', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('Active State', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('Processing', 'subscription-management-plugin'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['subscriptions'] as $subscription): ?>
                            <tr>
                                <td style="text-align: left;"><?php echo intval($subscription->user_id); ?></td>
                                <td><?php echo esc_html($subscription->display_name); ?></td>
                                <td><?php echo esc_html($subscription->user_email); ?></td>
                                <td><?php echo esc_html($subscription->plan_name); ?></td>
                                <td><?php echo format_currency($subscription->plan_amount); ?></td>
                                <td><?php echo format_date($subscription->created_at); ?></td>
                                <td><?php echo esc_html($subscription->order_id); ?></td>
                                <td><?php echo esc_html($subscription->currency_code ? $subscription->currency_code : 'INR'); ?></td>
                                <td><?php echo $subscription->subscription_starts_on ? format_date($subscription->subscription_starts_on) : '-'; ?></td>
                                <td><?php echo $subscription->subscription_ends_on ? format_date($subscription->subscription_ends_on) : '-'; ?></td>
                                <td>
                                    <?php 
                                    $state = intval($subscription->is_active_plan);
                                    if ($state === 1) {
                                        echo '<span class="badge bg-success">' . __('Active', 'subscription-management-plugin') . '</span>';
                                    } elseif ($state === 0) {
                                        echo '<span class="badge bg-warning">' . __('Future', 'subscription-management-plugin') . '</span>';
                                    } else {
                                        echo '<span class="badge bg-secondary">' . __('Expired', 'subscription-management-plugin') . '</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $proc = intval($subscription->processing_status);
                                    echo $proc === 1 
                                        ? '<span class="badge bg-success">' . __('Used', 'subscription-management-plugin') . '</span>'
                                        : '<span class="badge bg-primary">' . __('Unused', 'subscription-management-plugin') . '</span>';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No subscriptions found.', 'subscription-management-plugin'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// 4. Transactions page
function subscription_management_transactions_page() {
    // CSV export handler
    if (isset($_GET['smp_export']) && $_GET['smp_export'] === 'transactions' && current_user_can('manage_options')) {
        check_admin_referer('smp_export_transactions');

        // Prevent any previous HTML output
        if (ob_get_length()) ob_end_clean();

        $all = get_all_transactions(1, PHP_INT_MAX);
        $rows = isset($all['transactions']) ? $all['transactions'] : array();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=transactions-export-' . date('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');

        fputcsv($out, array('ID','User','Email','Plan','Amount','Currency','Gateway','Payment ID','Status','Subscription ID','Date'));

        foreach ($rows as $r) {
            fputcsv($out, array(
                intval($r->id),
                $r->display_name,
                $r->user_email,
                $r->plan_name,
                $r->amount,
                $r->currency,
                $r->payment_gateway,
                $r->payment_id,
                $r->payment_status,
                $r->subscription_id,
                $r->created_at,
            ));
        }
        fclose($out);
        exit;
    }
    $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    $data = get_all_transactions($page, 1000);
    ?>
    <div class="wrap">
        <h1><?php _e('All Transactions', 'subscription-management-plugin'); ?></h1>
        <?php if (!empty($data['transactions'])): ?>
            <p>
                <a href="<?php echo esc_url( wp_nonce_url( admin_url('admin.php?page=subscription-management-transactions&smp_export=transactions'), 'smp_export_transactions') ); ?>" class="btn btn-primary btn-sm"><?php _e('Export CSV', 'subscription-management-plugin'); ?></a>
            </p>
        <?php endif; ?>
        <div class="transactions-content">
            <?php if (!empty($data['transactions'])): ?>
                <table id="smp-transactions" class="wp-list-table widefat fixed striped smp-datatable">
                    <thead>
                        <tr>
                            <th><?php _e('User', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('Email', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('Plan', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('Amount', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('Currency', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('Payment Gateway', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('Payment ID', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('Status', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('Subscription ID', 'subscription-management-plugin'); ?></th>
                            <th><?php _e('Date', 'subscription-management-plugin'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['transactions'] as $transaction): ?>
                            <tr>
                                <td><?php echo esc_html($transaction->display_name); ?></td>
                                <td><?php echo esc_html($transaction->user_email); ?></td>
                                <td><?php echo esc_html($transaction->plan_name); ?></td>
                                <td><?php echo format_currency($transaction->amount); ?></td>
                                <td><?php echo esc_html($transaction->currency); ?></td>
                                <td><?php echo esc_html(ucfirst($transaction->payment_gateway)); ?></td>
                                <td><?php echo esc_html($transaction->payment_id); ?></td>
                                <td><?php echo get_payment_status_badge($transaction->payment_status); ?></td>
                                <td><?php echo $transaction->subscription_id ? intval($transaction->subscription_id) : '-'; ?></td>
                                <td><?php echo format_date($transaction->created_at); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No transactions found.', 'subscription-management-plugin'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// 5. Settings page
function subscription_management_settings_page() {
    if (isset($_POST['submit'])) {
        $gateway = sanitize_text_field($_POST['payment_gateway']);
        update_option('subscription_management_payment_gateway', $gateway);
        
        // Update gateway-specific settings
        if ($gateway === 'razorpay') {
            update_option('subscription_management_razorpay_key_id', sanitize_text_field($_POST['razorpay_key_id']));
            update_option('subscription_management_razorpay_key_secret', sanitize_text_field($_POST['razorpay_key_secret']));
            update_option('subscription_management_razorpay_webhook_secret', sanitize_text_field($_POST['razorpay_webhook_secret']));
        }

        // Early renewal settings
        update_option('smp_allow_early_renewal', isset($_POST['smp_allow_early_renewal']));
        $days_before = isset($_POST['smp_early_renewal_days']) ? intval($_POST['smp_early_renewal_days']) : 0;
        if ($days_before < 0) { $days_before = 0; }
        update_option('smp_early_renewal_days', $days_before);

        // Future subscriptions limit
        $max_future = isset($_POST['smp_max_future_subscriptions']) ? intval($_POST['smp_max_future_subscriptions']) : 1;
        if ($max_future < 0) { $max_future = 0; }
        update_option('smp_max_future_subscriptions', $max_future);

        // Trial settings
        update_option('smp_trial_enabled', isset($_POST['smp_trial_enabled']));

        // GST settings
        update_option('smp_gst_enabled', isset($_POST['smp_gst_enabled']));
        $gst_percentage = isset($_POST['smp_gst_percentage']) ? floatval($_POST['smp_gst_percentage']) : 0;
        if ($gst_percentage < 0) { $gst_percentage = 0; }
        update_option('smp_gst_percentage', $gst_percentage);
        update_option('smp_gst_label', sanitize_text_field(isset($_POST['smp_gst_label']) ? $_POST['smp_gst_label'] : ''));
        
        // Email settings
        update_option('smp_email_enabled', isset($_POST['smp_email_enabled']));
        update_option('smp_smtp_host', sanitize_text_field($_POST['smp_smtp_host']));
        update_option('smp_smtp_port', intval($_POST['smp_smtp_port']));
        update_option('smp_smtp_username', sanitize_text_field($_POST['smp_smtp_username']));
        update_option('smp_smtp_password', sanitize_text_field($_POST['smp_smtp_password']));
        update_option('smp_smtp_encryption', sanitize_text_field($_POST['smp_smtp_encryption']));
        update_option('smp_smtp_auth', isset($_POST['smp_smtp_auth']));
        update_option('smp_email_from_name', sanitize_text_field($_POST['smp_email_from_name']));
        update_option('smp_email_from_email', sanitize_email($_POST['smp_email_from_email']));
        update_option('smp_success_email_subject', sanitize_text_field($_POST['smp_success_email_subject']));
        update_option('smp_renewal_email_subject', sanitize_text_field($_POST['smp_renewal_email_subject']));
        
        // Cron settings
        update_option('smp_cron_enabled', isset($_POST['smp_cron_enabled']));
        update_option('smp_expiry_cron_frequency', isset($_POST['smp_expiry_cron_frequency']) ? sanitize_text_field($_POST['smp_expiry_cron_frequency']) : 'hourly');
        update_option('smp_expiry_cron_time', isset($_POST['smp_expiry_cron_time']) ? sanitize_text_field($_POST['smp_expiry_cron_time']) : '00:00');
        update_option('smp_renewal_cron_frequency', isset($_POST['smp_renewal_cron_frequency']) ? sanitize_text_field($_POST['smp_renewal_cron_frequency']) : 'daily');
        update_option('smp_renewal_cron_time', isset($_POST['smp_renewal_cron_time']) ? sanitize_text_field($_POST['smp_renewal_cron_time']) : '00:00');
        update_option('smp_cron_admin_only', isset($_POST['smp_cron_admin_only']));
        
        echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'subscription-management-plugin') . '</p></div>';
    }
    $current_gateway = get_option('subscription_management_payment_gateway', 'razorpay');
    $razorpay_settings = get_payment_gateway_settings('razorpay');
    ?>
    <div class="wrap">
        <form method="post" action="">
            <!-- Payment Gateway settings -->
            <h2 style="margin-top:24px;"><?php _e('Payment Gateway Settings', 'subscription-management-plugin'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Payment Gateway', 'subscription-management-plugin'); ?></th>
                    <td>
                        <select name="payment_gateway" id="payment_gateway">
                            <option value="razorpay" <?php selected($current_gateway, 'razorpay'); ?>><?php _e('Razorpay', 'subscription-management-plugin'); ?></option>
                            <option value="stripe" <?php selected($current_gateway, 'stripe'); ?> disabled><?php _e('Stripe', 'subscription-management-plugin'); ?> (<?php _e('Coming Soon', 'subscription-management-plugin'); ?>)</option>
                            <option value="paypal" <?php selected($current_gateway, 'paypal'); ?> disabled><?php _e('PayPal', 'subscription-management-plugin'); ?> (<?php _e('Coming Soon', 'subscription-management-plugin'); ?>)</option>
                        </select>
                        <p class="description"><?php _e('Select the payment gateway to use for subscription payments.', 'subscription-management-plugin'); ?></p>
                    </td>
                </tr>
            </table>
            <div id="razorpay-settings" class="gateway-settings">
                <h2><?php _e('Razorpay Settings', 'subscription-management-plugin'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Key ID', 'subscription-management-plugin'); ?></th>
                        <td>
                            <input type="text" name="razorpay_key_id" value="<?php echo esc_attr($razorpay_settings['key_id']); ?>" class="regular-text" />
                            <p class="description"><?php _e('Your Razorpay Key ID', 'subscription-management-plugin'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Key Secret', 'subscription-management-plugin'); ?></th>
                        <td>
                            <input type="password" name="razorpay_key_secret" value="<?php echo esc_attr($razorpay_settings['key_secret']); ?>" class="regular-text" />
                            <p class="description"><?php _e('Your Razorpay Key Secret', 'subscription-management-plugin'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Webhook Secret', 'subscription-management-plugin'); ?></th>
                        <td>
                            <input type="password" name="razorpay_webhook_secret" value="<?php echo esc_attr($razorpay_settings['webhook_secret']); ?>" class="regular-text" />
                            <p class="description"><?php _e('Your Razorpay Webhook Secret', 'subscription-management-plugin'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            <!-- Renewal settings -->
            <h2 style="margin-top:24px;"><?php _e('Renewal Settings', 'subscription-management-plugin'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Allow Early Renewal', 'subscription-management-plugin'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" id="smp_allow_early_renewal" name="smp_allow_early_renewal" value="1" <?php checked( (bool) get_option('smp_allow_early_renewal', false) ); ?> />
                            <?php _e('Users can renew an active subscription shortly before it ends', 'subscription-management-plugin'); ?>
                        </label>
                        <p class="description"><?php _e('Enable to let users purchase a renewal while their current plan is still active.', 'subscription-management-plugin'); ?></p>
                    </td>
                </tr>
                <tr id="smp_early_renewal_days_row" style="display: none;">
                    <th scope="row"><?php _e('Early Renewal Window (days)', 'subscription-management-plugin'); ?></th>
                    <td>
                        <input type="number" min="1" step="1" name="smp_early_renewal_days" value="<?php echo esc_attr( intval(get_option('smp_early_renewal_days', 1)) ); ?>" class="small-text" />
                        <p class="description"><?php _e('How many days before the current subscription ends a user may renew.', 'subscription-management-plugin'); ?></p>
                    </td>
                </tr>
                <tr id="smp_max_future_sub_row" style="display: none;">
                    <th scope="row"><?php _e('Max Future Subscriptions', 'subscription-management-plugin'); ?></th>
                    <td>
                        <input type="number" min="0" step="1" name="smp_max_future_subscriptions" value="<?php echo esc_attr( intval(get_option('smp_max_future_subscriptions', 1)) ); ?>" class="small-text" />
                        <p class="description"><?php _e('How many upcoming subscriptions a user can hold at once (0 = none).', 'subscription-management-plugin'); ?></p>
                    </td>
                </tr>
            </table>
            <!-- Trial settings -->
            <h2 style="margin-top:24px;"><?php _e('Trial Settings', 'subscription-management-plugin'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable Trial Plan', 'subscription-management-plugin'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="smp_trial_enabled" value="1" <?php checked( (bool) get_option('smp_trial_enabled', false) ); ?> />
                            <?php _e('Allow marking a plan as free trial (once per user)', 'subscription-management-plugin'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            <!-- GST settings -->
            <h2 style="margin-top:24px;"><?php _e('GST Settings', 'subscription-management-plugin'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable GST', 'subscription-management-plugin'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" id="smp_gst_enabled" name="smp_gst_enabled" value="1" <?php checked( (bool) get_option('smp_gst_enabled', false) ); ?> />
                            <?php _e('Apply GST on subscription price', 'subscription-management-plugin'); ?>
                        </label>
                    </td>
                </tr>
                <tr id="smp_gst_percentage_row" style="display: none;">
                    <th scope="row"><?php _e('GST Percentage', 'subscription-management-plugin'); ?></th>
                    <td>
                        <input type="number" min="0" step="0.01" name="smp_gst_percentage" value="<?php echo esc_attr( floatval(get_option('smp_gst_percentage', 0)) ); ?>" class="small-text" />
                        <p class="description"><?php _e('Percentage to apply on base plan amount', 'subscription-management-plugin'); ?></p>
                    </td>
                </tr>
                <tr id="smp_gst_label_row" style="display: none;">
                    <th scope="row"><?php _e('GST Label', 'subscription-management-plugin'); ?></th>
                    <td>
                        <input type="text" name="smp_gst_label" value="<?php echo esc_attr( get_option('smp_gst_label', __('Inclusive of GST', 'subscription-management-plugin')) ); ?>" class="regular-text" />
                        <p class="description"><?php _e('Label to show on plan cards (e.g., Inclusive of GST)', 'subscription-management-plugin'); ?></p>
                    </td>
                </tr>
            </table>
            <!-- Email settings -->
            <h2 style="margin-top:24px;"><?php _e('Email Settings', 'subscription-management-plugin'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable Email Notifications', 'subscription-management-plugin'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" id="smp_email_enabled" name="smp_email_enabled" value="1" <?php checked( (bool) get_option('smp_email_enabled', false) ); ?> />
                            <?php _e('Send email notifications for subscription events', 'subscription-management-plugin'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            <div id="email-settings" style="<?php echo get_option('smp_email_enabled', false) ? '' : 'display: none;'; ?>">
                <h1><?php _e('SMTP Settings', 'subscription-management-plugin'); ?></h1>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('SMTP Host', 'subscription-management-plugin'); ?></th>
                        <td>
                            <input type="text" name="smp_smtp_host" value="<?php echo esc_attr( get_option('smp_smtp_host', '') ); ?>" class="regular-text" />
                            <p class="description"><?php _e('SMTP server hostname (e.g., smtp.gmail.com)', 'subscription-management-plugin'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('SMTP Port', 'subscription-management-plugin'); ?></th>
                        <td>
                            <input type="number" name="smp_smtp_port" value="<?php echo esc_attr( get_option('smp_smtp_port', 587) ); ?>" class="small-text" />
                            <p class="description"><?php _e('SMTP port (587 for TLS, 465 for SSL)', 'subscription-management-plugin'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('SMTP Authentication', 'subscription-management-plugin'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="smp_smtp_auth" value="1" <?php checked( (bool) get_option('smp_smtp_auth', true) ); ?> />
                                <?php _e('Use SMTP authentication', 'subscription-management-plugin'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('SMTP Username', 'subscription-management-plugin'); ?></th>
                        <td>
                            <input type="text" name="smp_smtp_username" value="<?php echo esc_attr( get_option('smp_smtp_username', '') ); ?>" class="regular-text" />
                            <p class="description"><?php _e('SMTP username (usually your email address)', 'subscription-management-plugin'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('SMTP Password', 'subscription-management-plugin'); ?></th>
                        <td>
                            <input type="password" name="smp_smtp_password" value="<?php echo esc_attr( get_option('smp_smtp_password', '') ); ?>" class="regular-text" />
                            <p class="description"><?php _e('SMTP password or app password', 'subscription-management-plugin'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('SMTP Encryption', 'subscription-management-plugin'); ?></th>
                        <td>
                            <select name="smp_smtp_encryption">
                                <option value="tls" <?php selected( get_option('smp_smtp_encryption', 'tls'), 'tls' ); ?>><?php _e('TLS', 'subscription-management-plugin'); ?></option>
                                <option value="ssl" <?php selected( get_option('smp_smtp_encryption', 'tls'), 'ssl' ); ?>><?php _e('SSL', 'subscription-management-plugin'); ?></option>
                                <option value="" <?php selected( get_option('smp_smtp_encryption', 'tls'), '' ); ?>><?php _e('None', 'subscription-management-plugin'); ?></option>
                            </select>
                            <p class="description"><?php _e('Encryption method for SMTP connection', 'subscription-management-plugin'); ?></p>
                        </td>
                    </tr>
                </table>
                <h1><?php _e('Email Sender Settings', 'subscription-management-plugin'); ?></h1>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('From Name', 'subscription-management-plugin'); ?></th>
                        <td>
                            <input type="text" name="smp_email_from_name" value="<?php echo esc_attr( get_option('smp_email_from_name', get_bloginfo('name') ) ); ?>" class="regular-text" />
                            <p class="description"><?php _e('Name that appears in the "From" field', 'subscription-management-plugin'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('From Email', 'subscription-management-plugin'); ?></th>
                        <td>
                            <input type="email" name="smp_email_from_email" value="<?php echo esc_attr( get_option('smp_email_from_email', get_option('admin_email') ) ); ?>" class="regular-text" />
                            <p class="description"><?php _e('Email address that appears in the "From" field', 'subscription-management-plugin'); ?></p>
                        </td>
                    </tr>
                </table>
                <h1><?php _e('Email Templates', 'subscription-management-plugin'); ?></h1>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Success Email Subject', 'subscription-management-plugin'); ?></th>
                        <td>
                            <input type="text" name="smp_success_email_subject" value="<?php echo esc_attr( get_option('smp_success_email_subject', 'Subscription Successful - {site_name}' ) ); ?>" class="regular-text" />
                            <p class="description"><?php _e('Available placeholders: {site_name}, {user_name}, {plan_name}', 'subscription-management-plugin'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Renewal Email Subject', 'subscription-management-plugin'); ?></th>
                        <td>
                            <input type="text" name="smp_renewal_email_subject" value="<?php echo esc_attr( get_option('smp_renewal_email_subject', 'Subscription Renewal Reminder - {site_name}' ) ); ?>" class="regular-text" />
                            <p class="description"><?php _e('Available placeholders: {site_name}, {user_name}, {plan_name}, {days_remaining}', 'subscription-management-plugin'); ?></p>
                        </td>
                    </tr>
                </table>
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h3><?php _e('Test Email Configuration', 'subscription-management-plugin'); ?></h3>
                    </div>
                    <div class="card-body">
                        <p><?php _e('Send a test email to verify your email configuration:', 'subscription-management-plugin'); ?></p>
                        <input type="email" name="smp_test_email" id="smp_test_email">
                        <button type="button" class="button button-secondary" id="test-email-btn"><?php _e('Send Test Email', 'subscription-management-plugin'); ?></button>
                        <div id="test-email-result" style="margin-top: 10px;"></div>
                    </div>
                </div>
            </div>
            <!-- Cron settings -->
            <h2 style="margin-top:24px;"><?php _e('Cron Settings', 'subscription-management-plugin'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable WordPress Cron', 'subscription-management-plugin'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="smp_cron_enabled" value="1" <?php checked( (bool) get_option('smp_cron_enabled', false) ); ?> />
                            <?php _e('Use WordPress cron for subscription management tasks', 'subscription-management-plugin'); ?>
                        </label>
                        <p class="description"><?php _e('Enable this to use WordPress cron instead of external cron jobs.', 'subscription-management-plugin'); ?></p>
                    </td>
                </tr>
            </table>
            <div id="cron-settings" style="<?php echo get_option('smp_cron_enabled', false) ? '' : 'display: none;'; ?>">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Admin-Only Cron Testing', 'subscription-management-plugin'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="smp_cron_admin_only" value="1" <?php checked( (bool) get_option('smp_cron_admin_only', false) ); ?> />
                                <?php _e('Require admin authentication for cron testing URLs', 'subscription-management-plugin'); ?>
                            </label>
                            <p class="description"><?php _e('Enable this to restrict cron testing URLs to admin users only.', 'subscription-management-plugin'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Subscription Expiry Check', 'subscription-management-plugin'); ?></th>
                        <td>
                            <select id="smp_expiry_cron_frequency" name="smp_expiry_cron_frequency">
                                <option value="hourly" <?php selected( get_option('smp_expiry_cron_frequency', 'hourly'), 'hourly' ); ?>><?php _e('Every Hour', 'subscription-management-plugin'); ?></option>
                                <option value="daily" <?php selected( get_option('smp_expiry_cron_frequency', 'hourly'), 'daily' ); ?>><?php _e('Daily', 'subscription-management-plugin'); ?></option>
                            </select>
                            <div class="daily_time_field" style="margin-top:10px; display: none;">
                                <label for="smp_expiry_cron_time"><?php _e('Run daily at:', 'subscription-management-plugin'); ?></label>
                                <input type="time" id="smp_expiry_cron_time" name="smp_expiry_cron_time" value="<?php echo esc_attr( get_option('smp_expiry_cron_time', '00:00') ); ?>">
                            </div>
                            <p class="description"><?php _e('How often to check for expired subscriptions', 'subscription-management-plugin'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Renewal Reminders', 'subscription-management-plugin'); ?></th>
                        <td>
                            <select id="smp_renewal_cron_frequency" name="smp_renewal_cron_frequency">
                                <option value="hourly" <?php selected( get_option('smp_renewal_cron_frequency', 'hourly'), 'hourly' ); ?>><?php _e('Every Hour', 'subscription-management-plugin'); ?></option>
                                <option value="daily" <?php selected( get_option('smp_renewal_cron_frequency', 'hourly'), 'daily' ); ?>><?php _e('Daily', 'subscription-management-plugin'); ?></option>
                            </select>
                            <div class="daily_time_field" style="margin-top:10px; display: none;">
                                <label for="smp_renewal_cron_time"><?php _e('Run daily at:', 'subscription-management-plugin'); ?></label>
                                <input type="time" id="smp_renewal_cron_time" name="smp_renewal_cron_time" value="<?php echo esc_attr( get_option('smp_renewal_cron_time', '00:00') ); ?>">
                            </div>
                            <p class="description"><?php _e('How often to send renewal reminder emails', 'subscription-management-plugin'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php
                    // Display next scheduled run times in site's timezone
                    $status = function_exists('smp_get_cron_status') ? smp_get_cron_status() : array();
                    $expiry_ts = isset($status['next_expiry_check']) ? intval($status['next_expiry_check']) : 0;
                    $renewal_ts = isset($status['next_renewal_check']) ? intval($status['next_renewal_check']) : 0;
                    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone( wp_timezone_string() );
                    $expiry_local = $expiry_ts ? wp_date('Y-m-d H:i:s', $expiry_ts, $tz) : __('Not scheduled', 'subscription-management-plugin');
                    $renewal_local = $renewal_ts ? wp_date('Y-m-d H:i:s', $renewal_ts, $tz) : __('Not scheduled', 'subscription-management-plugin');
                ?>
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h3><?php _e('Next Scheduled Runs', 'subscription-management-plugin'); ?></h3>
                    </div>
                    <div class="card-body" style="padding:0px;padding-top: 20px;">
                        <p style="margin:0 0 6px 0;">
                            <strong><?php _e('Subscription expiry check cron run:', 'subscription-management-plugin'); ?></strong>
                            <?php echo esc_html($expiry_local); ?>
                        </p>
                        <p style="margin:0;">
                            <strong><?php _e('Subscription renewal reminders cron run:', 'subscription-management-plugin'); ?></strong>
                            <?php echo esc_html($renewal_local); ?>
                        </p>
                    </div>
                </div>
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h3><?php _e('Test Cron Jobs', 'subscription-management-plugin'); ?></h3>
                    </div>
                    <div class="card-body" style="padding:0px;padding-top: 20px;">
                        <p><?php _e('Test cron jobs manually to verify they are working correctly:', 'subscription-management-plugin'); ?></p>
                        <button type="button" class="button button-secondary" id="test-cron-btn"><?php _e('Run Test Cron', 'subscription-management-plugin'); ?></button>
                        <div id="test-cron-result" style="margin-top: 10px;"></div>
                    </div>
                </div>
            </div>
            <?php submit_button(); ?>
        </form>
    </div>
    <script>
    jQuery(document).ready(function($) {
        // Early renewal toggle
        $('#smp_allow_early_renewal').change(function() {
            $('#smp_early_renewal_days_row, #smp_max_future_sub_row').toggle(this.checked);
        }).trigger('change');

        // GST settings toggle
        $('#smp_gst_enabled').change(function() {
            $('#smp_gst_percentage_row, #smp_gst_label_row').toggle(this.checked);
        }).trigger('change');
        
        // Email settings toggle
        $('#smp_email_enabled').change(function() {
            $('#email-settings').toggle(this.checked);
        }).trigger('change');
        
        // Cron settings toggle
        $('input[name="smp_cron_enabled"]').change(function() {
            $('#cron-settings').toggle(this.checked);
        }).trigger('change');
        
        // Test email functionality
        $('#test-email-btn').click(function() {
            var $btn = $(this);
            var $result = $('#test-email-result');
            
            $btn.prop('disabled', true).text('<?php _e('Sending...', 'subscription-management-plugin'); ?>');
            $result.html('');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'smp_test_email',
                    test_email: $('#smp_test_email').val(),
                    nonce: '<?php echo wp_create_nonce('smp_test_email'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    $result.html('<div class="notice notice-error inline"><p><?php _e('Error sending test email.', 'subscription-management-plugin'); ?></p></div>');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('<?php _e('Send Test Email', 'subscription-management-plugin'); ?>');
                }
            });
        });
        
        // Cron time field toggle functionality
        function toggleTimeField($select){
            var $timeField = $select.closest('td').find('.daily_time_field');
            if ($select.val() === 'daily') {
                $timeField.show();
            } else {
                $timeField.hide();
            }
        }
        $('#smp_expiry_cron_frequency, #smp_renewal_cron_frequency').each(function(){
            var $this = $(this);
            toggleTimeField($this);
            $this.on('change', function(){ toggleTimeField($(this)); });
        });

        // Test cron functionality
        $('#test-cron-btn').click(function() {
            var $btn = $(this);
            var $result = $('#test-cron-result');
            
            $btn.prop('disabled', true).text('<?php _e('Running...', 'subscription-management-plugin'); ?>');
            $result.html('');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'smp_test_cron',
                    nonce: '<?php echo wp_create_nonce('smp_test_cron'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    $result.html('<div class="notice notice-error inline"><p><?php _e('Error running test cron.', 'subscription-management-plugin'); ?></p></div>');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('<?php _e('Run Test Cron', 'subscription-management-plugin'); ?>');
                }
            });
        });
        
        // Razorpay validation before form submit
        $('form').on('submit', function(e) {
            var gateway = $('#payment_gateway').val();
            if (gateway === 'razorpay') {
                var keyId = $('input[name="razorpay_key_id"]').val().trim();
                var keySecret = $('input[name="razorpay_key_secret"]').val().trim();
                var webhookSecret = $('input[name="razorpay_webhook_secret"]').val().trim();
                var missing = [];
                if (!keyId) missing.push('Key ID');
                if (!keySecret) missing.push('Key Secret');
                if (!webhookSecret) missing.push('Webhook Secret');
                if (missing.length > 0) {
                    e.preventDefault();
                    alert('Please fill the following required Razorpay fields: ' + missing.join(', '));
                    return false;
                }
            }
            // otherwise continue to PHP
        });
    });
    </script>
    <?php
}
