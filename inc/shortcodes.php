<?php
/**
 * Shortcodes for Subscription Management Plugin
 */

// Register shortcodes
add_action('init', 'register_subscription_shortcodes');
function register_subscription_shortcodes() {
    add_shortcode('subscription_plans', 'subscription_plans_shortcode');
    add_shortcode('user_subscription_status', 'user_subscription_status_shortcode');
    add_shortcode('subscription_form', 'subscription_form_shortcode');
}

// Subscription Plans Shortcode
function subscription_plans_shortcode($atts) {
    $atts = shortcode_atts(array(
        'show_pricing' => 'true',
        'show_features' => 'true',
        'columns' => '3',
        'style' => 'cards'
    ), $atts);
    
    $plans = get_subscription_plans();
    
    if (empty($plans)) {
        return '<p>No subscription plans available.</p>';
    }
    
    ob_start();
    ?>
    <div class="subscription-plans-section">
        <div class="subscription-plans-container">
            <div class="subscription-plans-header">
                <h1>Choose Your Plan</h1>
                <p>Select the perfect subscription plan for your needs</p>
            </div>
            
            <div class="subscription-plans-grid" style="grid-template-columns: repeat(<?php echo intval($atts['columns']); ?>, 1fr);">
                <?php foreach ($plans as $index => $plan): 
                    $amount = get_post_meta($plan->ID, 'plan_amount', true);
                    $expiry = get_post_meta($plan->ID, 'expiry_in_months', true);
                    $is_featured = ($index === 1); // Make second plan featured
                ?>
                    <div class="subscription-plan-card <?php echo $is_featured ? 'featured' : ''; ?>">
                        <div class="plan-name"><?php echo esc_html($plan->post_title); ?></div>
                        
                        <?php if ($atts['show_pricing'] === 'true'): ?>
                            <div class="plan-price">
                                <span class="currency">₹</span><?php echo intval($amount); ?>
                                <span class="period">/<?php echo $expiry; ?> month<?php echo $expiry > 1 ? 's' : ''; ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($atts['show_features'] === 'true'): ?>
                            <div class="plan-content">
                                <?php echo wp_kses_post($plan->post_content); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (is_user_logged_in()): ?>
                            <a href="<?php echo site_url('subscription-plans'); ?>" class="plan-button">
                                Choose Plan
                            </a>
                        <?php else: ?>
                            <a href="<?php echo wp_login_url(site_url('subscription-plans')); ?>" class="plan-button secondary">
                                Login to Subscribe
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
    
    return ob_get_clean();
}

// User Subscription Status Shortcode
function user_subscription_status_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<p>Please <a href="' . wp_login_url() . '">login</a> to view your subscription status.</p>';
    }
    
    $user_id = get_current_user_id();
    $subscription = get_user_active_subscription($user_id);
    
    ob_start();
    ?>
    <div class="subscription-status-section">
        <?php if ($subscription): ?>
            <div class="subscription-status active">
                <h3>Active Subscription</h3>
                <p>You have an active subscription to <strong><?php echo esc_html($subscription->plan_name); ?></strong></p>
            </div>
            
            <div class="subscription-details">
                <h3>Subscription Details</h3>
                <div class="detail-row">
                    <span class="detail-label">Plan:</span>
                    <span class="detail-value"><?php echo esc_html($subscription->plan_name); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Amount:</span>
                    <span class="detail-value"><?php echo format_currency($subscription->plan_amount); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Duration:</span>
                    <span class="detail-value"><?php echo $subscription->plan_expiry_in_months; ?> month<?php echo $subscription->plan_expiry_in_months > 1 ? 's' : ''; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value"><?php echo get_subscription_status_badge($subscription->status); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Started:</span>
                    <span class="detail-value"><?php echo format_date($subscription->created_at); ?></span>
                </div>
            </div>
        <?php else: ?>
            <div class="subscription-status inactive">
                <h3>No Active Subscription</h3>
                <p>You don't have an active subscription. <a href="<?php echo site_url('subscription-plans'); ?>">Choose a plan</a> to get started.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
    
    return ob_get_clean();
}

// Subscription Form Shortcode
function subscription_form_shortcode($atts) {
    $atts = shortcode_atts(array(
        'plan_id' => '',
        'redirect_url' => ''
    ), $atts);
    
    if (!is_user_logged_in()) {
        return '<p>Please <a href="' . wp_login_url() . '">login</a> to subscribe to a plan.</p>';
    }
    
    $user_id = get_current_user_id();
    
    // Check if user already has active subscription
    if (user_has_active_subscription($user_id)) {
        return '<p>You already have an active subscription. <a href="' . site_url('my-account') . '">View your account</a></p>';
    }
    
    $plans = get_subscription_plans();
    
    if (empty($plans)) {
        return '<p>No subscription plans available.</p>';
    }
    
    ob_start();
    ?>
    <div class="subscription-form-section">
        <div class="subscription-form">
            <h3>Subscribe to a Plan</h3>
            
            <form id="subscription-form" method="post">
                <?php wp_nonce_field('subscription_form_nonce', 'subscription_nonce'); ?>
                
                <div class="form-group">
                    <label for="subscription_plan">Select Plan:</label>
                    <select name="subscription_plan" id="subscription_plan" required>
                        <option value="">Choose a plan...</option>
                        <?php foreach ($plans as $plan): 
                            $amount = get_post_meta($plan->ID, 'plan_amount', true);
                            $expiry = get_post_meta($plan->ID, 'expiry_in_months', true);
                        ?>
                            <option value="<?php echo $plan->ID; ?>" data-amount="<?php echo $amount; ?>" data-expiry="<?php echo $expiry; ?>">
                                <?php echo esc_html($plan->post_title); ?> - ₹<?php echo intval($amount); ?>/<?php echo $expiry; ?> month<?php echo $expiry > 1 ? 's' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Project-specific fields like referral code should be handled by theme templates -->
                
                <div class="form-group">
                    <label>Payment Method:</label>
                    <div class="payment-methods">
                        <div class="payment-method">
                            <input type="radio" name="payment_method" value="razorpay" id="razorpay" checked>
                            <label for="razorpay">Razorpay</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="plan-button">Subscribe Now</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#subscription-form').on('submit', function(e) {
            e.preventDefault();
            
            var planId = $('#subscription_plan').val();
            var planAmount = $('#subscription_plan option:selected').data('amount');
            var planExpiry = $('#subscription_plan option:selected').data('expiry');
            var maReferralCode = null; // handled by theme if needed
            
            if (!planId) {
                alert('Please select a plan');
                return;
            }
            
            // Process subscription
            $.ajax({
                url: subscription_ajax.rest_url + '/subscription/store-user-subscription',
                type: 'POST',
                data: {
                    user_id: <?php echo $user_id; ?>,
                    
                    plan_id: planId,
                    plan_amount: planAmount,
                    plan_expiry_in_months: planExpiry
                },
                success: function(response) {
                    if (response.status == 1) {
                        // Initialize Razorpay payment
                        var options = {
                            "key": response.payment_data.key_id,
                            "amount": response.payment_data.amount,
                            "currency": response.payment_data.currency,
                            "name": "TownSol Sports",
                            "description": "Subscription Payment",
                            "order_id": response.payment_data.order_id,
                            "handler": function (paymentResponse){
                                // Complete payment
                                completePayment(response.subscription_id, paymentResponse);
                            },
                            "theme": {
                                "color": "#3399cc"
                            }
                        };
                        var rzp = new Razorpay(options);
                        rzp.open();
                    } else {
                        alert('Error: ' + response.message);
                    }
                }
            });
        });
        
        function completePayment(subscriptionId, paymentResponse) {
            $.ajax({
                url: subscription_ajax.rest_url + '/subscription/complete-payment',
                type: 'POST',
                data: {
                    subscription_id: subscriptionId,
                    payment_id: paymentResponse.razorpay_payment_id,
                    order_id: paymentResponse.razorpay_order_id,
                    signature: paymentResponse.razorpay_signature
                },
                success: function(response) {
                    if (response.status == 1) {
                        alert('Payment successful! Your subscription is now active.');
                        location.reload();
                    } else {
                        alert('Payment verification failed: ' + response.message);
                    }
                }
            });
        }
    });
    </script>
    <?php
    
    return ob_get_clean();
}
