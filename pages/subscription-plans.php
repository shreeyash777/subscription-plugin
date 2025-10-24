<?php
/**
 * Template Name: Subscription Plans Page Template
 * Plugin's subscription plans page
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if user is logged in
if (!is_user_logged_in()) {
    wp_redirect(get_option('accounts_portal_url_option') . 'login?rt=' . base64_encode(site_url('subscription')));
    exit;
}

get_header();

$redirectTo = site_url();
if (isset($_GET['rt']) && strlen(trim($_GET['rt'])) > 0) {
    $redirectTo = base64_decode($_GET['rt']);
}

/** User data */
$currentUser = wp_get_current_user();
$currentUserId = $currentUser->ID;

// Check if user has active subscription
$userHasSubscription = get_user_active_subscription($currentUserId);
$userCanPurchase = smp_user_can_purchase_subscription($currentUserId);

if (!$userHasSubscription || $userCanPurchase): 
    /** Fetch subscription plans */
    $subscriptionPlans = get_subscription_plans();
    if (!empty($subscriptionPlans)): ?>
        <section class="py-lg-5 py-3">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-12 d-flex flex-column justify-content-center position-relative" id="subscription_plans_section">
                        <div class="d-flex flex-column gap-4">
                            <div class="">
                                <h2 class="ts-h2 mb-3 fw-semibold">
                                    <?php if ($userHasSubscription && $userCanPurchase): ?>
                                        <?php _e('Renew Your Subscription', 'subscription-management-plugin'); ?>
                                    <?php else: ?>
                                        <?php _e('Pricing plan', 'subscription-management-plugin'); ?>
                                    <?php endif; ?>
                                </h2>
                                <span class="ts-p-medium m-0">
                                    <?php if ($userHasSubscription && $userCanPurchase): ?>
                                        <?php 
                                        $activeSubscription = $userHasSubscription;
                                        $daysRemaining = 0;
                                        if ($activeSubscription) {
                                            $start = strtotime($activeSubscription->subscription_starts_on);
                                            $months = intval($activeSubscription->plan_expiry_in_months);
                                            $end_ts = strtotime($activeSubscription->subscription_ends_on);
                                            $now = time();
                                            $daysRemaining = ceil(max(0, ($end_ts - $now)) / DAY_IN_SECONDS);
                                        }
                                        ?>
                                        <?php printf(__('Your current subscription expires in %d days. You can renew now to continue uninterrupted access.', 'subscription-management-plugin'), $daysRemaining); ?>
                                    <?php else: ?>
                                        <?php _e('Discover the perfect plan to unlock exclusive features and community benefits! Choose a subscription that fits your needs and enjoy seamless access to premium content, updates, and special offers. Whether you\'re here to explore, connect, or grow — we\'ve got a plan designed just for you. Subscribe today and take your experience to the next level.', 'subscription-management-plugin'); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="">
                                <!-- Plans slider (Swiper) with pricing cards -->
                                <div class="swiper" id="smpPlansSwiper">
                                    <div class="swiper-wrapper">
                                        <?php foreach($subscriptionPlans as $plan): 
                                            $planAmount = $plan->amount; 
                                            $gstEnabled = (bool) get_option('smp_gst_enabled', false);
                                            $gstPercent = floatval(get_option('smp_gst_percentage', 0));
                                            $gstLabel = get_option('smp_gst_label', __('Inclusive of GST', 'subscription-management-plugin'));
                                            $displayAmount = $planAmount;
                                            if ($gstEnabled && floatval($planAmount) > 0 && empty($plan->is_trial)) {
                                                $gst_amount = ($planAmount * $gstPercent) / 100;
                                                $displayAmount = $planAmount + $gst_amount;
                                            }
                                            if($userHasSubscription && $userCanPurchase && $plan->is_trial){
                                                // Skip trial plans if user is renewing
                                                continue;
                                            }
                                        ?>
                                            <div class="swiper-slide">
                                                <div class="card h-100 shadow-sm plan-card" data-plan-id="<?= intval($plan->id); ?>">
                                                    <div class="card-body d-flex flex-column position-relative">
                                                        <!-- Selected indicator -->
                                                        <div class="selected-indicator d-none position-absolute top-0 end-0 m-2">
                                                            <span class="badge bg-success">✓ Selected</span>
                                                        </div>
                                                        <h5 class="card-title mb-1">
                                                            <?= esc_html($plan->name); ?>
                                                            <?php if (!empty($plan->is_trial)): ?>
                                                                <span class="badge bg-info ms-2"><?php _e('Trial', 'subscription-management-plugin'); ?></span>
                                                            <?php endif; ?>
                                                        </h5>
                                                        <div class="display-6 fw-bold mb-2"><?= (intval($displayAmount) > 0) ? '₹ '.intval($displayAmount).'/-' : __('Free', 'subscription-management-plugin'); ?></div>
                                                        <p class="text-muted small mb-3"><?php _e('/ Month', 'subscription-management-plugin'); ?></p>
                                                        <div class="flex-grow-1 mb-3">
                                                            <?= wp_kses_post($plan->description); ?>
                                                        </div>
                                                        <?php if ($gstEnabled && floatval($planAmount) > 0 && empty($plan->is_trial)): ?>
                                                            <div class="text-muted small mb-2"><?= esc_html($gstLabel); ?></div>
                                                        <?php endif; ?>
                                                        <a href="javascript:void(0);" class="btn bg-ts-primary text-white w-100 selectPlanButton"
                                                        data-plan-id="<?= intval($plan->id); ?>"
                                                        data-plan-amount="<?= base64_encode(esc_attr($planAmount)); ?>"
                                                        data-plan-expiry-in-months="<?= base64_encode(intval($plan->expiry_in_months)); ?>"
                                                        <?php if ($gstEnabled && floatval($planAmount) > 0 && empty($plan->is_trial)): ?>
                                                            <?php
                                                                $notes = array(
                                                                    'base_amount' => floatval($planAmount),
                                                                    'gst_percent' => floatval($gstPercent),
                                                                    'net_amount' => floatval($displayAmount)
                                                                );
                                                                $notes_json = wp_json_encode($notes);
                                                            ?>
                                                            data-notes="<?= esc_attr($notes_json); ?>"
                                                        <?php endif; ?>>
                                                            <?php if ($userHasSubscription && $userCanPurchase): ?>
                                                                <?php _e('Renew Plan', 'subscription-management-plugin'); ?>
                                                            <?php else: ?>
                                                                <?php _e('Choose Plan', 'subscription-management-plugin'); ?>
                                                            <?php endif; ?>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="swiper-pagination"></div>
                                    <div class="swiper-button-prev"></div>
                                    <div class="swiper-button-next"></div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex flex-row justify-content-center gap-4">
                            <!-- All hidden inputs here -->
                            <input type="hidden" id="site_url" value="<?= site_url(); ?>">
                            <input type="hidden" id="rest_url" value="<?= rest_url('subscription-management/v1'); ?>">
                            <input type="hidden" id="current_user_id" value="<?= get_current_user_id(); ?>">
                            <a href="<?= site_url(); ?>" class="btn icon-btn border-ts-primary rounded-1 text-ts-secondary w-25 justify-content-center"><i class="ph ph-arrow-left"></i>Back</a>
                            <a href="javascript:void(0);" class="btn bg-ts-secondary rounded-1 text-white w-25 proceedSubscriptionPayment">
                                <?php if ($userHasSubscription && $userCanPurchase): ?>
                                    <?php _e('Continue to Renew', 'subscription-management-plugin'); ?>
                                <?php else: ?>
                                    <?php _e('Continue to Pay', 'subscription-management-plugin'); ?>
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>
                    <div class="d-none d-flex flex-column gap-4 px-5" id="subscription_successful_section">
                        <div class="text-center">
                            <h2 class="ts-h2 mb-2 fw-semibold">Payment Successful.</h2>
                        </div>
                        <div class="d-flex flex-column justify-content-center align-items-center">
                            <img src="<?= get_template_directory_uri(); ?>/images/success.gif" alt="successfull" class="img-fluid game-center-logo">
                        </div>
                        <div class="">
                            <a href="<?= $redirectTo; ?>" class="btn bg-ts-secondary rounded-0 text-white w-100">Begin Your Journey</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- Include Razorpay SDK -->
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <?php else: ?>
        <div class="row">
            <div class="col-12 px-4 p-5">
                <h6 class="ts-h6 text-center">No subscription plans available.</h6>
                <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                    <a href="<?= site_url(); ?>" class=" btn bg-primary-500 text-white">Home</a>
                    <!-- <a href="<?= get_option('accounts_portal_url_option').'my-account'; ?>" class=" btn border-ts-secondary text-ts-primary">My account</a> -->
                </div>
            </div>
        </div>
    <?php endif; 
else: ?>
    <div class="row">
        <div class="col-12 px-4 p-5">
            <h6 class="ts-h6 text-center">
                <?php if (!$userCanPurchase): ?>
                    <?php _e('You have already purchased a subscription plan.', 'subscription-management-plugin'); ?>
                <?php else: ?>
                    <?php _e('You have an active subscription. Please wait for the renewal window to open.', 'subscription-management-plugin'); ?>
                <?php endif; ?>
            </h6>
            <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                <a href="<?= site_url(); ?>" class=" btn bg-primary-500 text-white"><?php _e('Home', 'subscription-management-plugin'); ?></a>
                <!-- <a href="<?= get_option('accounts_portal_url_option').'my-account'; ?>" class=" btn border-ts-secondary text-ts-primary">My account</a> -->
            </div>
        </div>
    </div>
<?php endif;

get_footer();

