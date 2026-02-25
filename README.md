# Pakistani Payment Gateway for WooCommerce

A comprehensive payment gateway solution for Pakistani businesses using WooCommerce. This plugin adds support for 14 popular Pakistani payment methods including mobile wallets and major banks.

## Features

### Supported Payment Methods

**Mobile Wallets:**
- Easypaisa
- JazzCash
- SadaPay
- NayaPay
- Zindigi

**Banks:**
- HBL (Habib Bank Limited)
- UBL (United Bank Limited)
- Meezan Bank
- MCB (Muslim Commercial Bank)
- Allied Bank
- Bank Alfalah
- Faysal Bank
- Standard Chartered Bank
- Generic Bank Transfer

### Key Features

- **Transaction ID Tracking** - Customers can enter transaction IDs for payment verification
- **Sender Verification** - Collect sender account number and name for verification
- **Customizable Checkout Fields** - Control which fields are shown and required
- **WhatsApp Integration** - Send payment notifications via WhatsApp to admin and customers
- **Custom Email Templates** - Customize payment pending and approved email templates
- **Payment Fees** - Add fixed or percentage-based fees for payment methods
- **Conditional Logic** - Set minimum/maximum cart amounts for each gateway
- **Custom Fields** - Add up to 3 custom fields per payment gateway
- **Gateway Icons** - Upload custom logos for each payment method
- **Auto-Complete Orders** - Automatically mark orders as completed after verification
- **Payment Reminders** - Send automatic email reminders for pending payments
- **Multi-language Support** - Translation ready with text domain

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.2 or higher

## Installation

1. Download the plugin file `pakistani-payment-gateways.php`
2. Upload to your WordPress site's `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to WooCommerce > Settings > Payments to configure each payment gateway

## Configuration

### Basic Setup

1. Navigate to **WooCommerce > Settings > Payments**
2. Enable the payment gateways you want to use
3. Click on each gateway to configure:
   - **Title** - Name shown to customers at checkout
   - **Description** - Payment method description
   - **Account Details** - Your mobile wallet number or bank account details
   - **Account Holder Name** - Name registered on the account

### Advanced Settings

#### Checkout Field Settings
- Show/hide transaction ID field
- Make transaction ID required or optional
- Show/hide sender details fields
- Make sender details required or optional

#### Email Templates
- Customize payment pending email (sent after order placement)
- Customize payment approved email (sent after admin verification)
- Use variables like `{order_number}`, `{customer_name}`, `{order_total}`, etc.

#### WhatsApp Integration
- Enable WhatsApp notifications
- Set admin WhatsApp number
- Customize message templates for admin and customer
- Send payment details via WhatsApp

#### Payment Fees
- Add fixed amount or percentage-based fees
- Customize fee label shown at checkout

#### Conditional Logic
- Set minimum cart amount for gateway availability
- Set maximum cart amount for gateway availability

#### Custom Fields
- Add up to 3 custom fields per gateway
- Make custom fields required or optional

## Usage

### For Customers

1. Add products to cart and proceed to checkout
2. Select your preferred Pakistani payment method
3. View the payment account details displayed
4. Complete payment using your mobile wallet or bank
5. Enter transaction ID and sender details at checkout
6. Place order and receive confirmation email
7. Optionally send payment screenshot via WhatsApp

### For Store Owners

1. Receive WhatsApp notification when payment is submitted
2. Verify payment details in order admin panel
3. Update order status to "Processing" or "Completed" after verification
4. Customer receives payment approved email automatically

## Payment Verification Workflow

1. **Customer places order** → Order status: "On Hold" → Customer receives "Payment Pending" email
2. **Admin verifies payment** → Admin updates order status → Customer receives "Payment Approved" email
3. **Order processing** → Order is fulfilled and shipped

## WhatsApp Integration

The plugin supports WhatsApp notifications with customizable templates:

- **Admin Alert** - Notifies admin when new payment is submitted
- **Customer Message** - Pre-filled message customers can send to admin
- **Payment Verified** - Message admin can send after verifying payment
- **Payment Request** - Reminder message for pending payments

## Customization

### Available Email Variables

Use these variables in email templates:
- `{order_number}` - Order number
- `{customer_name}` - Customer name
- `{order_total}` - Order total amount
- `{payment_method}` - Payment method name
- `{transaction_id}` - Transaction ID
- `{sender_name}` - Sender account name
- `{sender_number}` - Sender account number
- `{site_name}` - Website name
- `{order_date}` - Order date
- `{billing_address}` - Billing address
- `{billing_city}` - Billing city
- `{billing_phone}` - Billing phone
- `{billing_email}` - Billing email
- `{order_items}` - List of ordered items

## Support

For support, feature requests, or bug reports, please visit:
- **Plugin URI:** https://www.mubashirhassan.com/pakistani-payment-gateway-wordpress-plugin.html
- **Author:** Mubashir Hassan
- **Author URI:** https://www.mubashirhassan.com

## License

This plugin is licensed under GPL v2 or later.
License URI: https://www.gnu.org/licenses/gpl-2.0.html

## Changelog

### Version 1.0.0
- Initial release
- Support for 14 Pakistani payment methods
- Transaction ID tracking
- Sender verification
- WhatsApp integration
- Custom email templates
- Payment fees
- Conditional logic
- Custom fields
- Gateway icons
- Auto-complete orders
- Payment reminders

## Credits

Developed by **Mubashir Hassan**

## Screenshots

(Add screenshots of your plugin in action)

1. Payment gateway selection at checkout
2. Payment details form
3. Admin settings panel
4. Order details with payment information
5. WhatsApp notification example

## Frequently Asked Questions

**Q: Can customers upload payment screenshots?**
A: Screenshot upload at checkout is disabled. Customers can send screenshots via WhatsApp after placing the order for better reliability.

**Q: How do I verify payments?**
A: Check the order details in WooCommerce admin panel. You'll see the transaction ID, sender details, and can verify against your payment account.

**Q: Can I add custom payment methods?**
A: Yes, you can use the generic "Bank Transfer" option and customize it with your bank details.

**Q: Does this plugin process payments automatically?**
A: No, this is a manual payment gateway. Customers make payments directly to your accounts, and you verify them manually.

**Q: Can I customize the email templates?**
A: Yes, the plugin includes full email template customization with support for multiple variables.

## Contributing

Contributions are welcome! Please feel free to submit pull requests or open issues on the GitHub repository.

