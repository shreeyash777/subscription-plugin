<?php
/**
 * Payment gateway functions for Subscription Management Plugin
 */

// Internal: Build Razorpay auth header
function smp_razorpay_auth_header($key_id, $key_secret) {
    return 'Basic ' . base64_encode($key_id . ':' . $key_secret);
}

// Initialize Razorpay via HTTP (create order)
function init_razorpay_payment($amount, $currency = 'INR', $order_id = null, $notes = null) {
    $settings = get_payment_gateway_settings('razorpay');
    
    if (empty($settings['key_id']) || empty($settings['key_secret'])) {
        return array('error' => 'Razorpay credentials not configured');
    }

    $endpoint = 'https://api.razorpay.com/v1/orders';
    $body = array(
        'amount' => intval(round($amount * 100)), // paise
        'currency' => $currency,
        'receipt' => $order_id ?: ('order_' . time()),
    );
    // Merge default notes with provided notes (if any)
    $default_notes = array('source' => 'subscription-management-plugin');
    if (is_array($notes) && !empty($notes)) {
        $sanitized_notes = array();
        foreach ($notes as $k => $v) {
            $sanitized_notes[sanitize_key($k)] = sanitize_text_field($v);
        }
        $body['notes'] = array_merge($default_notes, $sanitized_notes);
    } else {
        $body['notes'] = $default_notes;
    }
    $args = array(
        'headers' => array(
            'Authorization' => smp_razorpay_auth_header($settings['key_id'], $settings['key_secret']),
            'Content-Type' => 'application/json'
        ),
        'timeout' => 20,
        'body' => wp_json_encode($body)
    );
    $response = wp_remote_post($endpoint, $args);
    if (is_wp_error($response)) {
        return array('error' => $response->get_error_message());
    }
    $code = wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if ($code < 200 || $code >= 300) {
        $message = isset($data['error']['description']) ? $data['error']['description'] : 'Failed to create order';
        return array('error' => $message);
    }
    return array(
        'success' => true,
        'order_id' => isset($data['id']) ? $data['id'] : null,
        'amount' => isset($data['amount']) ? ($data['amount']) : intval(round($amount * 100)),
        'currency' => isset($data['currency']) ? $data['currency'] : $currency,
        'key_id' => $settings['key_id']
    );
}

// Verify Razorpay payment (HMAC + fetch payment)
function verify_razorpay_payment($payment_id, $order_id, $signature) {
    $settings = get_payment_gateway_settings('razorpay');
    
    if (empty($settings['key_id']) || empty($settings['key_secret'])) {
        return array('error' => 'Razorpay credentials not configured');
    }

    // Verify signature: HMAC SHA256 of order_id|payment_id with key_secret
    $payload = $order_id . '|' . $payment_id;
    $expected_signature = hash_hmac('sha256', $payload, $settings['key_secret']);
    if (!hash_equals($expected_signature, $signature)) {
        return array('error' => 'Invalid payment signature');
    }

    // Fetch payment details
    $endpoint = 'https://api.razorpay.com/v1/payments/' . rawurlencode($payment_id);
    $args = array(
        'headers' => array(
            'Authorization' => smp_razorpay_auth_header($settings['key_id'], $settings['key_secret'])
        ),
        'timeout' => 20
    );
    $response = wp_remote_get($endpoint, $args);
    if (is_wp_error($response)) {
        return array('error' => $response->get_error_message());
    }
    $code = wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if ($code < 200 || $code >= 300) {
        $message = isset($data['error']['description']) ? $data['error']['description'] : 'Failed to fetch payment details';
        return array('error' => $message);
    }

    return array(
        'success' => true,
        'payment_id' => isset($data['id']) ? $data['id'] : $payment_id,
        'amount' => isset($data['amount']) ? ($data['amount'] / 100) : 0,
        'currency' => isset($data['currency']) ? $data['currency'] : 'INR',
        'status' => isset($data['status']) ? $data['status'] : '',
        'method' => isset($data['method']) ? $data['method'] : '',
        'gateway_response' => json_encode($data)
    );
}

// Process payment
function process_subscription_payment($user_id, $plan_id, $plan_amount, $plan_expiry_in_months) {
    // Guard: block purchase if not allowed due to active subscription and early-renewal window
    if (!smp_user_can_purchase_subscription($user_id)) {
        return array('error' => __('You already have an active subscription. Renewal is not available yet.', 'subscription-management-plugin'));
    }

    // Detect trial purchase by plan flag and enforce once-per-user
    $is_trial = false;
    $plan_row = function_exists('smp_get_plan') ? smp_get_plan($plan_id) : null; // fallback below if not defined
    if (!$plan_row) {
        if (function_exists('get_subscription_plan')) {
            $plan_row = get_subscription_plan($plan_id);
        }
    }
    if ($plan_row && !empty($plan_row->is_trial) && (bool) get_option('smp_trial_enabled', false)) {
        $is_trial = true;
        global $wpdb;
        $subscriptions_table = $wpdb->prefix . 'subscription_management_subscriptions';
        $used = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $subscriptions_table WHERE user_id=%d AND plan_id=%d", $user_id, $plan_id)));
        if ($used > 0) {
            return array('error' => __('You have already used your free trial.', 'subscription-management-plugin'));
        }
    }

    // Initialize payment FIRST (do not create subscription row yet)
    $payment_gateway = get_option('subscription_management_payment_gateway', 'razorpay');
    
    if ($is_trial) {
        // Create subscription immediately with zero charge
        $trial_gst_data = array(
            'is_gst_applied' => 0,
            'gst_percentage' => null,
            'gst_amount' => null,
            'base_amount' => 0,
            'amount_paid' => 0
        );
        $subscription_id = store_user_subscription($user_id, $plan_id, 0, $plan_expiry_in_months, $trial_gst_data);
        if (!$subscription_id) {
            return array('error' => __('Failed to create trial subscription.', 'subscription-management-plugin'));
        }
        // Mark as trial in remark for clarity
        global $wpdb;
        $subscriptions_table = $wpdb->prefix . 'subscription_management_subscriptions';
        $wpdb->update(
            $subscriptions_table, 
            array(
                'payment_gateway' => 'trial',
                'payment_id' => 'trial-' . $subscription_id,
                'payment_status' => 'success',
                'remark' => 'Trial subscription',
            ),
            array('id' => $subscription_id), 
            array('%s','%s','%s','%s'), 
            array('%d')
        );
        /**
         * Fire creation hook so emails/integrations run
         */
        do_action('smp_subscription_created', $subscription_id, array(
            'user_id' => $user_id,
            'plan_id' => $plan_id,
            'plan_amount' => 0,
            'plan_expiry_in_months' => $plan_expiry_in_months,
        ));

        // Expose any notes back to the client so they can be echoed/used
        $notes = !empty($GLOBALS['smp_pending_notes']) && is_array($GLOBALS['smp_pending_notes']) ? $GLOBALS['smp_pending_notes'] : null;

        return array(
            'success' => true,
            'subscription_id' => $subscription_id,
            'payment_data' => array('order_id' => null, 'key_id' => null, 'amount' => 0, 'currency' => 'INR'),
            'trial' => true,
            'notes' => $notes
        );
    } elseif ($payment_gateway === 'razorpay') {
        // GST: compute total and prepare notes
        $gstEnabled = (bool) get_option('smp_gst_enabled', false);
        $gstPercent = floatval(get_option('smp_gst_percentage', 0));
        $base_amount = floatval($plan_amount);
        $gst_amount = 0;
        $charge_amount = $base_amount;
        if ($gstEnabled && $base_amount > 0 && $gstPercent > 0) {
            $gst_amount = ($base_amount * $gstPercent) / 100.0;
            $charge_amount = $base_amount + $gst_amount;
        }
        $temp_order_ref = 'sub_init_' . time() . '_' . $user_id . '_' . $plan_id;
        // If caller set sanitized notes on global, pick them up
        $notes_for_init = null;
        if (!empty($GLOBALS['smp_pending_notes'])) {
            $notes_for_init = $GLOBALS['smp_pending_notes'];
        }
        // Merge GST notes
        $gst_notes = array(
            'gst_rate' => strval($gstPercent),
            'gst_amount' => number_format($gst_amount, 2, '.', ''),
            'base_amount' => number_format($base_amount, 2, '.', ''),
        );
        $notes_for_init = is_array($notes_for_init) ? array_merge($notes_for_init, $gst_notes) : $gst_notes;
        $payment_init = init_razorpay_payment($charge_amount, 'INR', $temp_order_ref, $notes_for_init);
        
        if (isset($payment_init['error'])) {
            return $payment_init;
        }

        // Stash intended subscription details keyed by order_id (transient ~15 minutes)
        if (!empty($payment_init['order_id'])) {
            set_transient('smp_pending_sub_' . $payment_init['order_id'], array(
                'user_id' => $user_id,
                'plan_id' => $plan_id,
                'plan_amount' => $base_amount, // Store base amount, not total with GST
                'plan_expiry_in_months' => $plan_expiry_in_months,
                'notes' => !empty($notes_for_init) ? $notes_for_init : null,
                'created' => time(),
            ), 15 * MINUTE_IN_SECONDS);
        }

        return array(
            'success' => true,
            'payment_data' => $payment_init
        );
    }
    
    return array('error' => 'Unsupported payment gateway');
}

// Complete payment
function complete_subscription_payment($subscription_id, $payment_id, $order_id, $signature) {
    $payment_gateway = get_option('subscription_management_payment_gateway', 'razorpay');
    
    if ($payment_gateway === 'razorpay') {
        $verification = verify_razorpay_payment($payment_id, $order_id, $signature);
        
        if (isset($verification['error'])) {
            // Log a failed transaction attempt with no subscription_id
            $owner_user_id = 0;
            $pending = get_transient('smp_pending_sub_' . $order_id);
            if ($pending) {
                $owner_user_id = intval($pending['user_id']);
            }
            // Include plan_id when available so failed transactions still show plan context
            $pending_plan_id = ($pending && isset($pending['plan_id'])) ? intval($pending['plan_id']) : null;
            store_transaction(null, $owner_user_id, 0, 'razorpay', $payment_id, 'failed', json_encode($verification), $pending_plan_id);
            return $verification;
        }
        
        // Create subscription row NOW using pending data (if provided), else fallback
        global $wpdb;
        $subscriptions_table = $wpdb->prefix . 'subscription_management_subscriptions';
        $pending = get_transient('smp_pending_sub_' . $order_id);
        if ($pending) {
            delete_transient('smp_pending_sub_' . $order_id);
            // Prepare GST data from pending notes
            $gst_data = null;
            if (!empty($pending['notes']) && is_array($pending['notes'])) {
                $gst_data = array(
                    'is_gst_applied' => isset($pending['notes']['gst_rate']) && floatval($pending['notes']['gst_rate']) > 0 ? 1 : 0,
                    'gst_percentage' => isset($pending['notes']['gst_rate']) ? intval($pending['notes']['gst_rate']) : null,
                    'gst_amount' => isset($pending['notes']['gst_amount']) ? floatval($pending['notes']['gst_amount']) : null,
                    'base_amount' => isset($pending['notes']['base_amount']) ? floatval($pending['notes']['base_amount']) : floatval($pending['plan_amount']),
                    'amount_paid' => floatval($verification['amount']) // Use the actual amount paid from Razorpay
                );
            }
            // Create subscription row with GST data
            $subscription_id = store_user_subscription($pending['user_id'], $pending['plan_id'], $pending['plan_amount'], $pending['plan_expiry_in_months'], $gst_data);
            if (!$subscription_id) {
                return array('error' => 'Failed to create subscription after payment');
            }
            /**
             * Action fired after a subscription row is created locally.
             */
            // Store notes into remark column if present for traceability
            if (!empty($pending['notes']) && is_array($pending['notes'])) {
                global $wpdb;
                $subscriptions_table = $wpdb->prefix . 'subscription_management_subscriptions';
                $remark = 'Notes: ' . wp_json_encode($pending['notes']);
                $wpdb->update($subscriptions_table, array('remark' => $remark), array('id' => $subscription_id), array('%s'), array('%d'));
            }

            do_action('smp_subscription_created', $subscription_id, array(
                'user_id' => $pending['user_id'],
                'plan_id' => $pending['plan_id'],
                'plan_amount' => $pending['plan_amount'],
                'plan_expiry_in_months' => $pending['plan_expiry_in_months'],
            ));
        } else {
            // Backward compatibility: try to load an existing row if provided
            $subscription = $wpdb->get_row($wpdb->prepare("SELECT * FROM $subscriptions_table WHERE id = %d", $subscription_id));
            if (!$subscription) {
                return array('error' => 'Subscription not found');
            }
        }
        
        $owner_user_id = isset($pending['user_id']) ? intval($pending['user_id']) : (isset($subscription->user_id) ? intval($subscription->user_id) : 0);

        $transaction_id = store_transaction(
            $subscription_id,
            $owner_user_id,
            $verification['amount'],
            'razorpay',
            $verification['payment_id'],
            $verification['status'],
            $verification['gateway_response'],
            // include plan id when present
            (isset($pending['plan_id']) ? intval($pending['plan_id']) : (isset($subscription->plan_id) ? intval($subscription->plan_id) : null))
        );
        
        if (!$transaction_id) {
            return array('error' => 'Failed to store transaction');
        }
        
        /**
         * Update subscription with payment details & Save gateway order id to the subscription
        */
        $wpdb->update(
            $subscriptions_table,
            array(
                'payment_id' => $verification['payment_id'],
                'payment_status' => $verification['status'],
                'order_id' => $order_id,
                'remark' => sprintf('Razorpay order %s', $order_id),
                'currency_code' => isset($verification['currency']) ? $verification['currency'] : 'INR',
                'subscribed_on' => current_time('mysql')
            ),
            array('id' => $subscription_id),
            array('%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        $result = array(
            'success' => true,
            'transaction_id' => $transaction_id,
            'payment_status' => $verification['gateway_response']
        );
        /**
         * Action fired when a payment is completed and stored.
         * Themes can sync to a central site or update usermeta remotely.
         */
        do_action('smp_payment_completed', $subscription_id, $transaction_id, $result);
        return $result;
    }
    
    return array('error' => 'Unsupported payment gateway');
}

/**
 * Complete a trial subscription locally (no external gateway).
 * Creates a zero-amount transaction row and fires smp_payment_completed hook
 * so themes can react uniformly.
 */
function complete_trial_subscription($subscription_id, $notes = null) {
    global $wpdb;
    $subscriptions_table = $wpdb->prefix . 'subscription_management_subscriptions';
    $subscription = $wpdb->get_row($wpdb->prepare("SELECT * FROM $subscriptions_table WHERE id = %d", $subscription_id));
    if (!$subscription) {
        return array('error' => 'Subscription not found');
    }

    $owner_user_id = intval($subscription->user_id);
    // Store a zero-amount transaction with method 'trial'
    $transaction_id = store_transaction(
        $subscription_id, // subscription_id
        $owner_user_id, // user_id
        0, // amount
        'trial', // payment_gateway
        'trial-' . $subscription_id, // payment_id
        'success', // payment_status
        is_array($notes) ? wp_json_encode($notes) : (is_string($notes) ? $notes : ''), // gateway_response
        isset($subscription->plan_id) ? intval($subscription->plan_id) : null // plan_id
    );

    if (!$transaction_id) {
        return array('error' => 'Failed to store trial transaction');
    }

    $result = array(
        'success' => true,
        'transaction_id' => $transaction_id,
        'payment_status' => 'success',
        'trial' => true
    );

    do_action('smp_payment_completed', $subscription_id, $transaction_id, $result);
    return $result;
}

// Get payment gateway info
function get_payment_gateway_info($gateway) {
    $info = array();
    
    switch ($gateway) {
        case 'razorpay':
            $info = array(
                'name' => 'Razorpay',
                'description' => 'Accept payments via Razorpay',
                'supported_currencies' => array('INR'),
                'supported_methods' => array('card', 'netbanking', 'wallet', 'upi'),
                'webhook_support' => true,
                'refund_support' => true
            );
            break;
        case 'stripe':
            $info = array(
                'name' => 'Stripe',
                'description' => 'Accept payments via Stripe (Coming Soon)',
                'supported_currencies' => array('USD', 'EUR', 'GBP', 'INR'),
                'supported_methods' => array('card', 'bank_transfer', 'wallet'),
                'webhook_support' => true,
                'refund_support' => true,
                'coming_soon' => true
            );
            break;
        case 'paypal':
            $info = array(
                'name' => 'PayPal',
                'description' => 'Accept payments via PayPal (Coming Soon)',
                'supported_currencies' => array('USD', 'EUR', 'GBP', 'INR'),
                'supported_methods' => array('paypal', 'card'),
                'webhook_support' => true,
                'refund_support' => true,
                'coming_soon' => true
            );
            break;
    }
    
    return $info;
}

// Handle webhook
function handle_payment_webhook($gateway, $payload) {
    switch ($gateway) {
        case 'razorpay':
            return handle_razorpay_webhook($payload);
        case 'stripe':
            return handle_stripe_webhook($payload);
        case 'paypal':
            return handle_paypal_webhook($payload);
        default:
            return array('error' => 'Unsupported payment gateway');
    }
}

// Handle Razorpay webhook
function handle_razorpay_webhook($payload) {
    $settings = get_payment_gateway_settings('razorpay');
    
    // Verify webhook signature
    $webhook_secret = $settings['webhook_secret'];
    $received_signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'];
    
    $expected_signature = hash_hmac('sha256', $payload, $webhook_secret);
    
    if (!hash_equals($expected_signature, $received_signature)) {
        return array('error' => 'Invalid webhook signature');
    }
    
    $data = json_decode($payload, true);
    
    if ($data['event'] === 'payment.captured') {
        $payment_id = $data['payload']['payment']['entity']['id'];
        
        // Update transaction status
        global $wpdb;
        $transactions_table = $wpdb->prefix . 'subscription_management_transactions';
        
        $wpdb->update(
            $transactions_table,
            array('payment_status' => 'success'),
            array('payment_id' => $payment_id),
            array('%s'),
            array('%s')
        );
        
        log_subscription_activity('Payment captured via webhook: ' . $payment_id);
    }
    
    // Also handle failed payments so they are recorded in plugin transactions
    if ($data['event'] === 'payment.failed') {
        $payment = isset($data['payload']['payment']['entity']) ? $data['payload']['payment']['entity'] : null;
        if ($payment) {
            $payment_id = isset($payment['id']) ? $payment['id'] : '';
            $order_id = isset($payment['order_id']) ? $payment['order_id'] : '';
            $amount = isset($payment['amount']) ? floatval($payment['amount']) / 100 : 0;

            global $wpdb;
            $transactions_table = $wpdb->prefix . 'subscription_management_transactions';

            // Try update an existing transaction row
            $updated = $wpdb->update(
                $transactions_table,
                array(
                    'payment_status' => 'failed',
                    'gateway_response' => wp_json_encode($data)
                ),
                array('payment_id' => $payment_id),
                array('%s', '%s'),
                array('%s')
            );

            if ($updated === false || $updated === 0) {
                // No existing transaction found/updated; attempt to create one using pending transient info
                $owner_user_id = 0;
                $subscription_id = null;
                if (!empty($order_id)) {
                    $pending = get_transient('smp_pending_sub_' . $order_id);
                    if ($pending) {
                        $owner_user_id = isset($pending['user_id']) ? intval($pending['user_id']) : 0;
                        // subscription not created yet on failed payment
                    }
                }

                $pending_plan_id = (isset($pending['plan_id']) ? intval($pending['plan_id']) : null);
                store_transaction($subscription_id, $owner_user_id, $amount, 'razorpay', $payment_id, 'failed', wp_json_encode($data), $pending_plan_id);
            }

            log_subscription_activity('Payment failed via webhook: ' . $payment_id . ' (order: ' . $order_id . ')');
        }
    }
    
    return array('success' => true);
}

// Handle Stripe webhook (placeholder)
function handle_stripe_webhook($payload) {
    return array('error' => 'Stripe webhook handling not implemented yet');
}

// Handle PayPal webhook (placeholder)
function handle_paypal_webhook($payload) {
    return array('error' => 'PayPal webhook handling not implemented yet');
}
