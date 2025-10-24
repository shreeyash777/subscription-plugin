# Subscription Management Plugin

A comprehensive WordPress plugin for managing subscriptions with payment gateway integration, admin dashboard, and user-friendly interfaces.

## Features

### Admin Dashboard
- **Summary Statistics**: Total subscriptions, active subscriptions, revenue, and recent subscriptions
- **Subscription Plans Management**: Create, edit, and manage subscription plans
- **All Subscriptions**: View and manage all user subscriptions with pagination
- **Transactions**: Track all payment transactions with detailed information
- **Settings**: Configure payment gateways and their settings

### Payment Gateways
- **Razorpay**: Full integration with Razorpay payment gateway
- **Stripe**: Coming soon (placeholder implementation)
- **PayPal**: Coming soon (placeholder implementation)

### User Features
- **Subscription Plans Page**: User-friendly subscription selection interface
- **Payment Processing**: Secure payment handling with Razorpay
- **Subscription Status**: Users can view their subscription status

### Shortcodes
- `[subscription_plans]`: Display subscription plans on any page
- `[user_subscription_status]`: Show user's current subscription status
- `[subscription_form]`: Display subscription form for users

## Installation

1. Upload the plugin folder to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Subscriptions' menu in WordPress admin to configure settings
4. Set up CRON jobs for production sites (see CRON Setup section below)

## Configuration

### Payment Gateway Setup
1. Go to **Subscriptions > Settings**
2. Select Razorpay as payment gateway
3. Enter your Razorpay Key ID and Key Secret
4. Configure webhook secret for payment verification
5. Enable/disable test mode as needed

## CRON Setup for Production Sites

**Important:** You have two options for handling subscription expiry and renewal reminders:

### Option 1: WordPress Cron (Recommended)
The plugin now includes built-in WordPress cron functionality. To enable it:

1. Go to **Subscriptions > Settings**
2. Scroll down to **Cron Settings**
3. Check **"Enable WordPress Cron"**
4. Configure the schedule for:
   - **Subscription Expiry Check**: How often to check for expired subscriptions (hourly, twice daily, or daily)
   - **Renewal Reminders**: How often to send renewal reminder emails (daily, twice daily, or weekly)
5. Optionally enable **"Admin-Only Cron Testing"** to restrict cron testing URLs to admin users only

**Note:** WordPress cron requires site traffic to trigger. For sites with low traffic, consider using Option 2 below.

### Option 2: System CRON (External)
For sites with low traffic or when you need more control, you can use external system CRON jobs:

```bash
# Run every 5 minutes to check for expired subscriptions and send renewal reminders
*/5 * * * * curl -s https://yoursite.com/wp-content/plugins/subscription-management-plugin/cron-jobs/subscription-cron.php?action=run_all >/dev/null 2>&1
```

#### Individual CRON Jobs
You can also run specific jobs separately:

```bash
# Check for expired subscriptions (run daily)
0 0 * * * curl -s https://yoursite.com/wp-content/plugins/subscription-management-plugin/cron-jobs/subscription-cron.php?action=expire_subscriptions >/dev/null 2>&1

# Send renewal reminders (run daily)
0 1 * * * curl -s https://yoursite.com/wp-content/plugins/subscription-management-plugin/cron-jobs/subscription-cron.php?action=send_renewal_reminders >/dev/null 2>&1
```

#### Using EasyCron or Similar Services
You can also use external CRON services like EasyCron to call the CRON endpoint:
- URL: `https://yoursite.com/wp-content/plugins/subscription-management-plugin/cron-jobs/subscription-cron.php?action=run_all`
- Frequency: Every 5 minutes or daily

### Testing CRON Jobs

#### WordPress Cron Testing
- Use the **"Test Cron Jobs"** button in **Subscriptions > Settings > Cron Settings**
- This will run both expiry check and renewal reminders and show results

#### External CRON Testing
You can test the external CRON jobs manually by visiting:
- `https://yoursite.com/wp-content/plugins/subscription-management-plugin/cron-jobs/subscription-cron.php?action=expire_subscriptions`
- `https://yoursite.com/wp-content/plugins/subscription-management-plugin/cron-jobs/subscription-cron.php?action=send_renewal_reminders`
- `https://yoursite.com/wp-content/plugins/subscription-management-plugin/cron-jobs/subscription-cron.php?action=run_all`

**Security Note:** If you have enabled **"Admin-Only Cron Testing"** in the settings, these URLs will require admin authentication and return a 403 error for non-admin users.

### Creating Subscription Plans
Plans are now stored in a custom database table `wp_subscription_management_plans` instead of a custom post type. You can insert/update plans via code, a small admin UI you build, or direct DB access.

Table columns: `id, name, slug, description, amount, expiry_in_months, sequence, status`.

## Database Tables

The plugin creates two custom tables:

### `wp_subscription_management_subscriptions`
- Stores user subscription data
- Links users to plans
- Tracks subscription status and payment information

### `wp_subscription_management_transactions`
- Stores payment transaction details
- Links to subscriptions
- Tracks payment status and gateway responses

## API Endpoints

### REST API Routes
- `POST /wp-json/subscription-management/v1/subscription/store-user-subscription`
- `POST /wp-json/subscription-management/v1/subscription/complete-payment`
- `GET /wp-json/subscription-management/v1/subscription/user-subscription`
- `POST /wp-json/subscription-management/v1/webhook/{gateway}`

### AJAX Actions
- `process_subscription_payment`
- `complete_subscription_payment`

## File Structure

```
subscription-management-plugin/
├── inc/                    # Helper functions and includes
│   ├── admin-menu.php      # Admin menu and pages
│   ├── database.php        # Database functions
│   ├── functions.php       # Helper functions
│   ├── payment-gateways.php # Payment gateway functions
│   └── shortcodes.php      # Shortcode implementations
├── js/                     # JavaScript files
│   ├── admin.js           # Admin dashboard scripts
│   └── subscription.js    # Frontend subscription scripts
├── css/                    # Stylesheets
│   ├── admin.css          # Admin dashboard styles
│   └── frontend.css       # Frontend styles
├── rest_api/              # REST API endpoints
│   └── subscription-api.php
├── pages/                 # Page templates
│   └── subscription-plans.php  # Frontend template used by the "Subscription" page
├── logs/                  # Log files (not public)
└── subscription-management-plugin.php # Main plugin file
```

## Usage Examples

### Display Subscription Plans
```php
// Using shortcode
[subscription_plans columns="3" show_pricing="true"]

// Using PHP function
$plans = get_subscription_plans();
foreach($plans as $plan) {
    echo $plan->name;
}
```

### Check User Subscription
```php
// Check if user has active subscription
if (user_has_active_subscription($user_id)) {
    echo "User has active subscription";
}

// Get user's active subscription
$subscription = get_user_active_subscription($user_id);
if ($subscription) {
    echo "Plan: " . $subscription->plan_name;
}
```

### Get Statistics
```php
$stats = get_subscription_statistics();
echo "Total subscriptions: " . $stats['total_subscriptions'];
echo "Active subscriptions: " . $stats['active_subscriptions'];
echo "Total revenue: " . format_currency($stats['total_revenue']);
```

## Hooks and Filters

### Actions
- `subscription_management_plugin_activate`: Plugin activation
- `subscription_management_plugin_deactivate`: Plugin deactivation
- `subscription_management_admin_menu`: Admin menu creation

### Filters
- Custom filters can be added for extending functionality

## Security Features

- Nonce verification for AJAX requests
- Input sanitization and validation
- SQL injection prevention with prepared statements
- Capability checks for admin functions
- Secure payment processing with Razorpay

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- Razorpay account (for payment processing)

## Support

For support and feature requests, please contact the development team.

## UI Customization Guidance

If you want to change the UI of the subscription plan listing page or make the "Make payment" button common across projects, use theme overrides first. Copy this file from the plugin into your theme and edit there:

`wp-content/themes/your-theme/subscription-management-plugin/subscription-plans.php`

If a theme override exists, it takes precedence. Otherwise the plugin's default template at `wp-content/plugins/subscription-management-plugin/pages/subscription-plans.php` is used.

You can also filter the located template path via:

```php
add_filter('smp_locate_template', function($path, $slug){
    // return a custom absolute path if you want to move templates elsewhere
    return $path;
}, 10, 2);
```

This keeps the plugin generic and not site-bound.

## Extensibility Hooks

- `smp_subscription_created` (action): Fires after a subscription row is created locally. Use this to push user meta or subscription info to a central site.
- `smp_payment_completed` (action): Fires after a payment is verified and stored. Use this to sync transactions or update remote meta.
- `smp_after_store_user_subscription_api` (action): Fires at the end of Store User Subscription API handler with the payment init result and original request payload.
- `smp_enable_plugin_rest_routes` (filter): Return `false` in your theme to disable the plugin's REST routes and fully own the API surface.

## Theme-side Implementation Examples

```php
// Disable plugin REST so the theme owns APIs
add_filter('smp_enable_plugin_rest_routes', '__return_false');

// Push subscription meta to accounts.abc.net after creation
add_action('smp_subscription_created', function($subscriptionId, $payload){
    my_site_sync_subscription_create($payload);
}, 10, 2);

// Sync payment completion
add_action('smp_payment_completed', function($subscriptionId, $transactionId, $result){
    // Handle successful payment here
    error_log("Theme :: Subscription payment completed.");
    error_log("Subscription ID: " . $subscriptionId);
    error_log("Transaction ID: " . $transactionId);
    error_log(print_r($result, true));
    // Example log output:
    // Theme :: Subscription payment completed.
    // Subscription ID: 1
    // Transaction ID: 1
    // Array(
    //     [success] => 1
    //     [transaction_id] => 1
    //     [payment_status] => {"id":"pay_RQxKSuLg73gi2G","entity":"payment","amount":30000,"currency":"INR","status":"captured","order_id":"order_RQxJIuXWKbSXBl","invoice_id":null,"international":false,"method":"netbanking","amount_refunded":0,"refund_status":null,"captured":true,"description":"Subscription Payment","card_id":null,"bank":"BARB_R","wallet":null,"vpa":null,"email":"void@razorpay.com","contact":"+917977874423","notes":{"source":"subscription-management-plugin","central_portal_user_id":"154","district":"Mumbai","ma_referral_code":"I88R5EYX1Z"},"fee":708,"tax":108,"error_code":null,"error_description":null,"error_source":null,"error_step":null,"error_reason":null,"acquirer_data":{"bank_transaction_id":"5861325"},"created_at":1759921517}
    // )
}, 10, 3);

// Build a user subscription API in the theme (example)
add_action('rest_api_init', function(){
    register_rest_route('mytheme/v1', '/user/subscription', array(
        'methods' => 'GET',
        'callback' => function($req){
            $user_id = intval($req->get_param('user_id'));
            $sub = get_user_active_subscription($user_id);
            return rest_ensure_response(['status' => $sub ? 1 : 0, 'subscription' => smp_build_subscription_payload($sub)]);
        },
        'permission_callback' => '__return_true'
    ));
});
```

## Changelog

### Version 1.1.0
- Added WordPress cron functionality with configurable schedules
- Added cron settings section in admin panel
- Added test cron functionality similar to email testing
- Added admin-only authentication for cron testing URLs
- Improved cron management with proper WordPress scheduling
- Updated documentation with new cron options

### Version 1.0.0
- Initial release
- Razorpay payment gateway integration
- Admin dashboard with statistics
- Subscription plans management
- User subscription interface
- Shortcode support
- REST API endpoints
- Subscription plan template edit access via theme
