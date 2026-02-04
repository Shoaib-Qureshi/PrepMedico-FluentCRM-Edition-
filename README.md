# FluentCRM Edition Contacts

A WordPress plugin that adds a "Contacts by Edition" page to FluentCRM, allowing you to browse and filter contacts by their enrolled course edition with integrated WooCommerce order data.

## Features

- **Edition-Based Filtering**: Browse contacts organized by course editions
- **WooCommerce Integration**: Displays product names, order totals, and order counts
- **ASiT Member Detection**: Automatically detects and badges ASiT members
- **Library Subscription Support**: Special handling for library subscriptions via FluentCRM tags
- **Multiple Order Support**: Shows all orders for contacts with multiple purchases
- **Revenue Statistics**: View total revenue, order count, and average order value per edition
- **Export to CSV**: Export filtered contacts to CSV file with all data fields
- **Copy Email Button**: Quick copy-to-clipboard for contact emails
- **Phone Number Display**: Shows phone from FluentCRM or WooCommerce billing
- **Custom Order Meta Fields**: Displays Specialities, Exam Date, and Date of Birth from orders
- **Responsive Design**: Works on desktop and mobile devices
- **HPOS Compatible**: Supports WooCommerce High-Performance Order Storage

## Requirements

- WordPress 5.8+
- PHP 7.4+
- FluentCRM (required)
- WooCommerce (optional, for order data)

## Installation

1. Upload the `fluentcrm-edition-contacts` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Access via FluentCRM menu: **FluentCRM > Contacts by Edition**

## Course Configuration

The plugin comes pre-configured with the following courses:

| Custom Field | Label | WooCommerce Category |
|--------------|-------|---------------------|
| `frcophth_p1_edition` | FRCOphth Part 1 | `frcophth-part-1` |
| `frcophth_p2_edition` | FRCOphth Part 2 | `frcophth-part-2` |
| `frcs_edition` | FRCS | `frcs` |
| `frcs_vasc_edition` | FRCS-VASC | `frcs-vasc` |
| `scfhs_edition` | SCFHS | `scfhs` |
| `library_sub_edition` | Lib Sub | `library-subscription` |

### Adding New Courses

Edit the `$courses` array in `fluentcrm-edition-contacts.php`:

```php
private static $courses = [
    'your_custom_field' => [
        'label' => 'Display Name',
        'name' => 'Full Course Name',
        'category' => 'woocommerce-category-slug'
    ],
    // ... other courses
];
```

## How It Works

### Contact Detection

1. **Standard Courses**: Contacts are detected via FluentCRM custom fields (e.g., `frcophth_p1_edition = "2025-26"`)
2. **Library Subscription**: Contacts are detected via FluentCRM tag "Library-Subscription" OR WooCommerce orders with library-subscription products

### Order Data Retrieval

The plugin searches for WooCommerce orders using:
- Billing email matching the contact's email
- Order meta key: `_edition_name_[category]` (e.g., `_edition_name_frcs`)
- Order meta value matching the edition (e.g., "2025-26")

If no exact match is found, a fallback search looks for any orders with the category meta key.

## Table Columns

| Column | Description |
|--------|-------------|
| Contact | Name, email (with copy button), and ASiT member badge |
| Phone | Phone number from FluentCRM or WooCommerce billing |
| Course | Product name (truncated to 30 chars) + item count |
| Price | Total amount from all orders |
| Edition | The edition value from FluentCRM |
| Specialities | From WooCommerce order meta `billing_specialities` |
| Exam Date | From WooCommerce order meta `billing_exam_date` |
| DOB | Date of birth from WooCommerce order meta `billing_dob` |
| Status | FluentCRM subscription status |
| Added | Date contact was added to FluentCRM |

## Statistics Display

For each edition filter, the plugin shows:
- **Total Enrolled**: Number of contacts in the edition
- **Subscribed**: Contacts with "subscribed" status
- **Total Revenue**: Sum of all order totals
- **Total Orders**: Number of WooCommerce orders
- **Avg. Order Value**: Average order amount

## WooCommerce Order Meta

For the plugin to work correctly, WooCommerce orders must have:

```
Meta Key: _edition_name_[category]
Meta Value: [edition-value]
```

Example:
```
Meta Key: _edition_name_frcs
Meta Value: 2025-26
```

### ASiT Member Detection

The plugin checks for ASiT membership via order meta:
- `_asit_membership_number`
- `_wcem_asit_number`

If either exists and has a value, the contact is marked as an ASiT Member.

## Customization

### Hiding the Course Column

To hide the Course/Product column:

1. In `fluentcrm-edition-contacts.php`, comment out line ~1023:
```php
// <th class="fcef-col-course"><?php _e('Course', 'fluentcrm-edition-contacts'); ?></th>
```

2. In `assets/js/admin.js`, comment out lines ~209-214:
```javascript
// row += '<td><div class="fcef-product-wrapper">';
// ...
// row += '</div></td>';
```

### Styling

Custom CSS can be added via WordPress Customizer or by editing `assets/css/admin.css`.

Key CSS variables:
```css
:root {
    --fc-primary: #86205e;      /* Primary brand color */
    --fc-secondary: #442e8c;    /* Secondary brand color */
    --fc-success: #00b27f;      /* Success/subscribed color */
    --fc-warning: #f5a623;      /* Warning/pending color */
    --fc-danger: #ff4757;       /* Danger/unsubscribed color */
}
```

## Troubleshooting

### Contacts Not Showing

1. Verify the FluentCRM custom field exists and has values
2. Check that the custom field key matches the `$courses` configuration

### Order Data Not Appearing

1. Verify WooCommerce orders have the correct `_edition_name_[category]` meta
2. Check that the billing email matches the FluentCRM contact email
3. Ensure orders have status "completed" or "processing"

### Library Subscription Not Working

1. Verify the FluentCRM tag "Library-Subscription" exists (case-insensitive)
2. Check that orders have `_edition_name_library-subscription` meta key

## File Structure

```
fluentcrm-edition-contacts/
├── fluentcrm-edition-contacts.php  # Main plugin file
├── assets/
│   ├── css/
│   │   └── admin.css               # Admin styles
│   └── js/
│       └── admin.js                # Admin JavaScript
└── README.md                       # This file
```

## Changelog

### Version 1.4.0
- Added Export to CSV functionality
- Added Phone number column (from FluentCRM or WooCommerce billing)
- Added Copy email button with visual feedback
- Added Specialities column (`billing_specialities` order meta)
- Added Exam Date column (`billing_exam_date` order meta)
- Added Date of Birth column (`billing_dob` order meta)
- Improved table layout with new columns

### Version 1.3.0
- Added ASiT Member badge detection
- Fixed FRCOphth Part 1/2 category slugs
- Improved order data retrieval with fallback search
- Added Library Subscription support via tags
- Multiple order display with count badge
- Product name truncation (30 characters)
- HPOS compatibility

### Version 1.2.0
- Initial release with edition filtering
- WooCommerce order integration
- Revenue statistics

## Author

**Shoaib Qureshi**

## License

This plugin is licensed under the GPL v2 or later.
