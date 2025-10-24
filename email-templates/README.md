# Email Templates

This folder contains HTML email templates for the Subscription Management Plugin.

## Available Templates

### subscription-success.html
Sent when a user successfully subscribes to a plan.

**Available Variables:**
- `{site_name}` - Site name
- `{site_url}` - Site URL
- `{user_name}` - User's display name
- `{user_email}` - User's email address
- `{plan_name}` - Subscription plan name
- `{plan_amount}` - Plan amount (formatted with currency)
- `{plan_duration}` - Plan duration (e.g., "1 month", "3 months")
- `{start_date}` - Subscription start date
- `{end_date}` - Subscription end date
- `{current_year}` - Current year
- `{admin_email}` - Admin email address

### subscription-renewal.html
Sent as a reminder when a subscription is about to expire.

**Available Variables:**
- `{site_name}` - Site name
- `{site_url}` - Site URL
- `{user_name}` - User's display name
- `{user_email}` - User's email address
- `{plan_name}` - Subscription plan name
- `{plan_amount}` - Plan amount (formatted with currency)
- `{plan_duration}` - Plan duration (e.g., "1 month", "3 months")
- `{end_date}` - Subscription end date
- `{days_remaining}` - Days remaining until expiration
- `{renewal_url}` - URL to renewal page
- `{current_year}` - Current year
- `{admin_email}` - Admin email address

## Customizing Templates

### Theme Override
You can override these templates by placing custom versions in your theme:

```
wp-content/themes/your-theme/subscription-management-plugin/email-templates/
```

### Template Structure
- Templates are HTML files with inline CSS
- Use Bootstrap classes for responsive design
- All variables are replaced with actual values when emails are sent
- Templates support HTML formatting and styling

### Best Practices
- Keep templates mobile-responsive
- Use inline CSS for better email client compatibility
- Test templates across different email clients
- Keep file sizes reasonable for faster loading
- Use web-safe fonts and colors

## Testing
Use the "Send Test Email" feature in the Email Settings page to test your email configuration and templates.
