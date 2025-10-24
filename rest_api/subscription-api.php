<?php
/**
 * REST API endpoints for Subscription Management Plugin
 */

// Register REST API routes
add_action('rest_api_init', function () {
    register_rest_route('subscription-management/v1', '/subscription/store-user-subscription', array(
        'methods' => 'POST',
        'callback' => 'store_user_subscription_api',
        'permission_callback' => '__return_true'
    ));
    
    register_rest_route('subscription-management/v1', '/subscription/complete-payment', array(
        'methods' => 'POST',
        'callback' => 'complete_subscription_payment_api',
        'permission_callback' => '__return_true'
    ));
    
    register_rest_route('subscription-management/v1', '/subscription/user-subscription', array(
        'methods' => 'GET',
        'callback' => 'get_user_subscription_api',
        'permission_callback' => '__return_true'
    ));
    
    register_rest_route('subscription-management/v1', '/webhook/(?P<gateway>[a-zA-Z0-9-]+)', array(
        'methods' => 'POST',
        'callback' => 'handle_payment_webhook_api',
        'permission_callback' => '__return_true'
    ));
});

// Store User Subscription API
function store_user_subscription_api($request) {
    $response = array();
    $requestData = $request->get_params();
    $APICallType = $request->get_header('x_api_call_type');
    try {
        if (isset($requestData['user_id']) && intval($requestData['user_id']) > 0 
            && isset($requestData['plan_id']) && intval($requestData['plan_id']) > 0) {

            // Sanitize data
            if (! function_exists('sanitize_subscription_data')) {
                throw new Exception('Missing function: sanitize_subscription_data');
            }
            $sanitized_data = sanitize_subscription_data($requestData);

            // Check if user can purchase now (honors early-renewal settings)
            if (! function_exists('smp_user_can_purchase_subscription')) {
                throw new Exception('Missing function: smp_user_can_purchase_subscription');
            }
            if (!smp_user_can_purchase_subscription($sanitized_data['user_id'])) {
                $response['status'] = -1;
                $response['message'] = __('You already have an active subscription. Renewal is not available yet.', 'subscription-management-plugin');
                return new WP_REST_Response($response, 200);
            }

            // Allow theme to inject project-specific data before processing
            $sanitized_data = apply_filters('smp_prepare_subscription_request', $sanitized_data, $requestData);
            // If notes present, set a global so payment initializer can include them in order creation
            if (isset($sanitized_data['notes']) && is_array($sanitized_data['notes'])) {
                $GLOBALS['smp_pending_notes'] = $sanitized_data['notes'];
            }
            // Process payment (project-specific fields are not part of the plugin call)
            if (! function_exists('process_subscription_payment')) {
                throw new Exception('Missing function: process_subscription_payment');
            }
            $payment_result = process_subscription_payment(
                $sanitized_data['user_id'],
                $sanitized_data['plan_id'],
                $sanitized_data['plan_amount'],
                $sanitized_data['plan_expiry_in_months']
            );
            // Clean up global notes after call
            if (isset($GLOBALS['smp_pending_notes'])) unset($GLOBALS['smp_pending_notes']);

            if (isset($payment_result['error'])) {
                $response['status'] = -1;
                $response['message'] = $payment_result['error'];
            } else {
                $response['status'] = 1;
                $response['payment_data'] = $payment_result['payment_data'];
                // For backward compatibility, keep subscription_id optional
                if (isset($payment_result['subscription_id'])) {
                    $response['subscription_id'] = $payment_result['subscription_id'];
                }
                // Trial flag and notes passthrough
                if (!empty($payment_result['trial'])) {
                    $response['trial'] = true;
                }
                if (isset($payment_result['notes'])) {
                    $response['notes'] = $payment_result['notes'];
                }
            }

            // Fire hook for site-specific cross-site sync. Theme or mu-plugin should implement.
            do_action('smp_after_store_user_subscription_api', $payment_result, $requestData, $APICallType);
        } else {
            $response['status'] = 0;
            $response['message'] = 'Request data is missing or invalid.';
        }

        return new WP_REST_Response($response, 200);
    } catch (Throwable $e) {
        // Log exhaustive info for debugging on the server
        $response = array(
            'status' => -1,
            'message' => 'Internal server error while processing subscription. Check server logs for details.',
            'error' => $e->getMessage()
        );
        return new WP_REST_Response($response, 500);
    }
}

// Complete Subscription Payment API
function complete_subscription_payment_api($request) {
    $response = array();
    $requestData = $request->get_params();

    try {
        // Trial completion path (no gateway fields). Accept when trial flag present.
        if (!empty($requestData['trial']) && !empty($requestData['subscription_id'])) {
            $subscription_id = intval($requestData['subscription_id']);
            $notes = isset($requestData['notes']) ? $requestData['notes'] : null;
            if (! function_exists('complete_trial_subscription')) {
                throw new Exception('Missing function: complete_trial_subscription');
            }
            $result = complete_trial_subscription($subscription_id, $notes);

            if (isset($result['error'])) {
                $response['status'] = -1;
                $response['message'] = $result['error'];
            } else {
                $response['status'] = 1;
                $response['transaction_id'] = $result['transaction_id'];
                $response['payment_status'] = $result['payment_status'];
                $response['trial'] = true;
            }

        // subscription_id may be optional (created only after payment); accept when missing
        } elseif (isset($requestData['payment_id']) && isset($requestData['order_id']) && isset($requestData['signature'])) {
            if (! function_exists('complete_subscription_payment')) {
                throw new Exception('Missing function: complete_subscription_payment');
            }
            $subscription_id = isset($requestData['subscription_id']) ? intval($requestData['subscription_id']) : null;
            $result = complete_subscription_payment(
                $subscription_id,
                sanitize_text_field($requestData['payment_id']),
                sanitize_text_field($requestData['order_id']),
                sanitize_text_field($requestData['signature'])
            );

            if (isset($result['error'])) {
                $response['status'] = -1;
                $response['message'] = $result['error'];
            } else {
                $response['status'] = 1;
                $response['transaction_id'] = $result['transaction_id'];
                $response['payment_status'] = $result['payment_status'];
            }
        } else {
            $response['status'] = 0;
            $response['message'] = 'Required payment data is missing.';
        }

        return new WP_REST_Response($response, 200);
    } catch (Throwable $e) {
        $response = array(
            'status' => -1,
            'message' => 'Internal server error while completing payment. Check server logs for details.',
            'error' => $e->getMessage()
        );
        return new WP_REST_Response($response, 500);
    }
}

// Get User Subscription API
function get_user_subscription_api($request) {
    $response = array();
    $requestData = $request->get_params();
    
    if (isset($requestData['user_id']) && intval($requestData['user_id']) > 0) {
        $subscription = get_user_active_subscription(intval($requestData['user_id']));
        
        if ($subscription) {
            $response['status'] = 1;
            $response['subscription'] = array(
                'id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
                'plan_name' => $subscription->plan_name,
                'plan_description' => $subscription->plan_description,
                'plan_amount' => $subscription->plan_amount,
                'plan_expiry_in_months' => $subscription->plan_expiry_in_months,
                'status' => $subscription->status,
                'created_at' => $subscription->created_at
            );
        } else {
            $response['status'] = 0;
            $response['message'] = 'No active subscription found for this user.';
        }
    } else {
        $response['status'] = -1;
        $response['message'] = 'User ID is required.';
    }
    
    return new WP_REST_Response($response, 200);
}

// Handle Payment Webhook API
function handle_payment_webhook_api($request) {
    $gateway = $request->get_param('gateway');
    $payload = file_get_contents('php://input');
    
    $result = handle_payment_webhook($gateway, $payload);
    
    if (isset($result['error'])) {
        return new WP_REST_Response($result, 400);
    }
    
    return new WP_REST_Response($result, 200);
}

// AJAX handler for frontend
add_action('wp_ajax_process_subscription_payment', 'ajax_process_subscription_payment');
add_action('wp_ajax_nopriv_process_subscription_payment', 'ajax_process_subscription_payment');
function ajax_process_subscription_payment() {
    check_ajax_referer('subscription_management_nonce', 'nonce');
    
    $user_id = intval($_POST['user_id']);
    
    $plan_id = intval($_POST['plan_id']);
    $plan_amount = floatval($_POST['plan_amount']);
    $plan_expiry_in_months = intval($_POST['plan_expiry_in_months']);
    $result = process_subscription_payment($user_id, $plan_id, $plan_amount, $plan_expiry_in_months);
    
    wp_send_json($result);
}

// AJAX handler for completing payment
add_action('wp_ajax_complete_subscription_payment', 'ajax_complete_subscription_payment');
add_action('wp_ajax_nopriv_complete_subscription_payment', 'ajax_complete_subscription_payment');
function ajax_complete_subscription_payment() {
    check_ajax_referer('subscription_management_nonce', 'nonce');
    
    $subscription_id = intval($_POST['subscription_id']);
    $payment_id = sanitize_text_field($_POST['payment_id']);
    $order_id = sanitize_text_field($_POST['order_id']);
    $signature = sanitize_text_field($_POST['signature']);
    
    $result = complete_subscription_payment($subscription_id, $payment_id, $order_id, $signature);
    
    wp_send_json($result);
}
