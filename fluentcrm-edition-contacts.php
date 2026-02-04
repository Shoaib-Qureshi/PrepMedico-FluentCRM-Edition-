<?php

/**
 * Plugin Name: FluentCRM Edition Contacts
 * Description: Browse and filter FluentCRM contacts by course edition. Adds "Contacts by Edition" to FluentCRM navigation.
 * Version: 1.3.0
 * Author: Shoaib Qureshi
 * Text Domain: fluentcrm-edition-contacts
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

define('FCEF_VERSION', '1.4.0');
define('FCEF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FCEF_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class
 */
class FluentCRM_Edition_Contacts
{

    private static $instance = null;

    /**
     * Course configuration
     */
    private static $courses = [
        'frcophth_p1_edition' => [
            'label' => 'FRCOphth Part 1',
            'name' => 'FRCOphth Part 1',
            'category' => 'frcophth-part-1'
        ],
        'frcophth_p2_edition' => [
            'label' => 'FRCOphth Part 2',
            'name' => 'FRCOphth Part 2',
            'category' => 'frcophth-part-2'
        ],
        'frcs_edition' => [
            'label' => 'FRCS',
            'name' => 'FRCS',
            'category' => 'frcs'
        ],
        'frcs_vasc_edition' => [
            'label' => 'FRCS-VASC',
            'name' => 'FRCS-VASC',
            'category' => 'frcs-vasc'
        ],
        'scfhs_edition' => [
            'label' => 'SCFHS',
            'name' => 'SCFHS',
            'category' => 'scfhs'
        ],
        'library_sub_edition' => [
            'label' => 'Lib Sub',
            'name' => 'Library Subscription',
            'category' => 'library-subscription'
        ]
    ];

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('plugins_loaded', [$this, 'init'], 30);
    }

    public function init()
    {
        // Check if FluentCRM is active
        if (!defined('FLUENTCRM')) {
            add_action('admin_notices', [$this, 'fluentcrm_missing_notice']);
            return;
        }

        // Add to WordPress admin menu
        add_action('admin_menu', [$this, 'add_admin_menu'], 100);

        // Add to FluentCRM's navigation (with high priority position)
        add_filter('fluent_crm/core_menu_items', [$this, 'add_fluentcrm_nav_item'], 5);
        add_filter('fluentcrm_menu_items', [$this, 'add_fluentcrm_nav_item'], 5);

        // Register as FluentCRM page
        add_filter('fluentcrm_is_admin_page', [$this, 'register_as_fluentcrm_page'], 10, 1);

        // Enqueue scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

        // AJAX handlers
        add_action('wp_ajax_fcef_get_editions', [$this, 'ajax_get_editions']);
        add_action('wp_ajax_fcef_filter_contacts', [$this, 'ajax_filter_contacts']);
        add_action('wp_ajax_fcef_get_all_editions', [$this, 'ajax_get_all_editions']);
        add_action('wp_ajax_fcef_get_stats', [$this, 'ajax_get_stats']);
        add_action('wp_ajax_fcef_export_contacts', [$this, 'ajax_export_contacts']);
    }

    public function fluentcrm_missing_notice()
    {
        echo '<div class="notice notice-error"><p><strong>FluentCRM Edition Contacts</strong> requires FluentCRM to be installed and activated.</p></div>';
    }

    /**
     * Register as FluentCRM page
     */
    public function register_as_fluentcrm_page($isAdmin)
    {
        if (isset($_GET['page']) && $_GET['page'] === 'fluentcrm-edition-contacts') {
            return true;
        }
        return $isAdmin;
    }

    /**
     * Add to FluentCRM navigation - position 3 (after Dashboard at 1, before Contacts)
     */
    public function add_fluentcrm_nav_item($items)
    {
        // Create new array with our item inserted at position
        $new_items = [];
        $inserted = false;

        foreach ($items as $key => $item) {
            // Insert our item after 'dashboard' or at start if no dashboard
            if ($key === 'dashboard' || $key === 'Dashboard') {
                $new_items[$key] = $item;
                $new_items['edition_contacts'] = [
                    'key'       => 'edition_contacts',
                    'label'     => __('Contacts by Edition', 'fluentcrm-edition-contacts'),
                    'permalink' => admin_url('admin.php?page=fluentcrm-edition-contacts'),
                    'position'  => 3,
                    'icon'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'
                ];
                $inserted = true;
            } else {
                $new_items[$key] = $item;
            }
        }

        // If dashboard wasn't found, just add to start
        if (!$inserted) {
            $edition_item = [
                'edition_contacts' => [
                    'key'       => 'edition_contacts',
                    'label'     => __('Contacts by Edition', 'fluentcrm-edition-contacts'),
                    'permalink' => admin_url('admin.php?page=fluentcrm-edition-contacts'),
                    'position'  => 3,
                    'icon'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'
                ]
            ];
            $new_items = array_merge($edition_item, $items);
        }

        return $new_items;
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'fluentcrm-admin',
            __('Contacts by Edition', 'fluentcrm-edition-contacts'),
            __('Contacts by Edition', 'fluentcrm-edition-contacts'),
            'manage_options',
            'fluentcrm-edition-contacts',
            [$this, 'render_page'],
            2  // Position - after dashboard
        );
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts($hook)
    {
        if (
            strpos($hook, 'fluentcrm-edition-contacts') === false &&
            !(isset($_GET['page']) && $_GET['page'] === 'fluentcrm-edition-contacts')
        ) {
            return;
        }

        wp_enqueue_style('fcef-admin', FCEF_PLUGIN_URL . 'assets/css/admin.css', [], FCEF_VERSION);
        wp_enqueue_script('fcef-admin', FCEF_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], FCEF_VERSION, true);
        wp_localize_script('fcef-admin', 'fcefData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fcef_nonce'),
            'courses' => self::$courses,
            'fluentcrmUrl' => admin_url('admin.php?page=fluentcrm-admin#/subscribers/'),
            'adminUrl' => admin_url(),
            'currency' => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$'
        ]);
    }

    /**
     * Get editions for course
     */
    private function get_editions_for_course($course_field)
    {
        global $wpdb;

        // Special handling for Lib Sub - it has no editions, only tags
        if ($course_field === 'library_sub_edition') {
            return ['Library-Subscription'];
        }

        $table = $wpdb->prefix . 'fc_subscriber_meta';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return [];
        }

        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT value FROM $table WHERE `key` = %s AND value != '' ORDER BY value DESC",
            $course_field
        ));

        return $results ? $results : [];
    }

    /**
     * Get contact phone number from FluentCRM or WooCommerce
     */
    private function get_contact_phone($subscriber_id)
    {
        global $wpdb;

        // First try FluentCRM subscriber phone
        $subscribers_table = $wpdb->prefix . 'fc_subscribers';
        $phone = $wpdb->get_var($wpdb->prepare(
            "SELECT phone FROM $subscribers_table WHERE id = %d",
            $subscriber_id
        ));

        if (!empty($phone)) {
            return $phone;
        }

        // If no phone in FluentCRM, try to get from WooCommerce billing
        $email = $wpdb->get_var($wpdb->prepare(
            "SELECT email FROM $subscribers_table WHERE id = %d",
            $subscriber_id
        ));

        if (!empty($email) && class_exists('WooCommerce')) {
            // Check for HPOS
            if (
                class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') &&
                \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
            ) {
                $orders_table = $wpdb->prefix . 'wc_orders';
                $phone = $wpdb->get_var($wpdb->prepare(
                    "SELECT billing_phone FROM $orders_table WHERE billing_email = %s AND billing_phone != '' ORDER BY date_created_gmt DESC LIMIT 1",
                    $email
                ));
            } else {
                $phone = $wpdb->get_var($wpdb->prepare(
                    "SELECT pm.meta_value FROM {$wpdb->postmeta} pm
                     INNER JOIN {$wpdb->postmeta} pm_email ON pm.post_id = pm_email.post_id
                     INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                     WHERE pm_email.meta_key = '_billing_email' AND pm_email.meta_value = %s
                     AND pm.meta_key = '_billing_phone' AND pm.meta_value != ''
                     AND p.post_type = 'shop_order'
                     ORDER BY p.post_date DESC LIMIT 1",
                    $email
                ));
            }
        }

        return $phone ?: '';
    }

    /**
     * Get contacts by edition
     */
    private function get_contacts_by_edition($course_field, $edition_value, $page = 1, $per_page = 20)
    {
        global $wpdb;

        $meta_table = $wpdb->prefix . 'fc_subscriber_meta';
        $subscribers_table = $wpdb->prefix . 'fc_subscribers';
        $tags_table = $wpdb->prefix . 'fc_subscriber_pivot';
        $terms_table = $wpdb->prefix . 'fc_tags';

        $offset = ($page - 1) * $per_page;

        // Special handling for Lib Sub - filter by tags instead of edition meta
        if ($course_field === 'library_sub_edition' && $edition_value === 'Library-Subscription') {
            // Use case-insensitive matching for tag title OR check for library-subscription orders
            if (class_exists('WooCommerce')) {
                // Get contacts with library-subscription tag OR library-subscription orders
                if (
                    class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') &&
                    \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
                ) {
                    // HPOS enabled
                    $orders_table = $wpdb->prefix . 'wc_orders';
                    $orders_meta_table = $wpdb->prefix . 'wc_orders_meta';

                    $total = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(DISTINCT s.id)
                         FROM $subscribers_table s
                         WHERE EXISTS (
                             SELECT 1 FROM $tags_table sp
                             INNER JOIN $terms_table t ON sp.object_id = t.id
                             WHERE sp.subscriber_id = s.id
                             AND sp.object_type = 'FluentCrm\\\\App\\\\Models\\\\Tag'
                             AND LOWER(t.title) = LOWER(%s)
                         ) OR EXISTS (
                             SELECT 1 FROM $orders_table o
                             INNER JOIN $orders_meta_table om ON o.id = om.order_id
                             WHERE o.billing_email = s.email
                             AND om.meta_key LIKE %s
                             AND o.status IN ('wc-completed', 'wc-processing')
                         )",
                        $edition_value,
                        '_edition_name_library-subscription%'
                    ));

                    $contacts = $wpdb->get_results($wpdb->prepare(
                        "SELECT DISTINCT s.id, s.email, s.first_name, s.last_name, s.status, s.created_at, s.avatar, %s as edition
                         FROM $subscribers_table s
                         WHERE EXISTS (
                             SELECT 1 FROM $tags_table sp
                             INNER JOIN $terms_table t ON sp.object_id = t.id
                             WHERE sp.subscriber_id = s.id
                             AND sp.object_type = 'FluentCrm\\\\App\\\\Models\\\\Tag'
                             AND LOWER(t.title) = LOWER(%s)
                         ) OR EXISTS (
                             SELECT 1 FROM $orders_table o
                             INNER JOIN $orders_meta_table om ON o.id = om.order_id
                             WHERE o.billing_email = s.email
                             AND om.meta_key LIKE %s
                             AND o.status IN ('wc-completed', 'wc-processing')
                         )
                         ORDER BY s.created_at DESC
                         LIMIT %d OFFSET %d",
                        $edition_value,
                        $edition_value,
                        '_edition_name_library-subscription%',
                        $per_page,
                        $offset
                    ));
                } else {
                    // Legacy post-based orders
                    $total = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(DISTINCT s.id)
                         FROM $subscribers_table s
                         WHERE EXISTS (
                             SELECT 1 FROM $tags_table sp
                             INNER JOIN $terms_table t ON sp.object_id = t.id
                             WHERE sp.subscriber_id = s.id
                             AND sp.object_type = 'FluentCrm\\\\App\\\\Models\\\\Tag'
                             AND LOWER(t.title) = LOWER(%s)
                         ) OR EXISTS (
                             SELECT 1 FROM {$wpdb->posts} p
                             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                             INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
                             WHERE pm_email.meta_value = s.email
                             AND p.post_type = 'shop_order'
                             AND p.post_status IN ('wc-completed', 'wc-processing')
                             AND pm.meta_key LIKE %s
                         )",
                        $edition_value,
                        '_edition_name_library-subscription%'
                    ));

                    $contacts = $wpdb->get_results($wpdb->prepare(
                        "SELECT DISTINCT s.id, s.email, s.first_name, s.last_name, s.status, s.created_at, s.avatar, %s as edition
                         FROM $subscribers_table s
                         WHERE EXISTS (
                             SELECT 1 FROM $tags_table sp
                             INNER JOIN $terms_table t ON sp.object_id = t.id
                             WHERE sp.subscriber_id = s.id
                             AND sp.object_type = 'FluentCrm\\\\App\\\\Models\\\\Tag'
                             AND LOWER(t.title) = LOWER(%s)
                         ) OR EXISTS (
                             SELECT 1 FROM {$wpdb->posts} p
                             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                             INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
                             WHERE pm_email.meta_value = s.email
                             AND p.post_type = 'shop_order'
                             AND p.post_status IN ('wc-completed', 'wc-processing')
                             AND pm.meta_key LIKE %s
                         )
                         ORDER BY s.created_at DESC
                         LIMIT %d OFFSET %d",
                        $edition_value,
                        $edition_value,
                        '_edition_name_library-subscription%',
                        $per_page,
                        $offset
                    ));
                }
            } else {
                // Fallback to tag-only filtering if WooCommerce is not active
                $total = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT s.id)
                     FROM $subscribers_table s
                     INNER JOIN $tags_table sp ON s.id = sp.subscriber_id
                     INNER JOIN $terms_table t ON sp.object_id = t.id
                     WHERE sp.object_type = 'FluentCrm\\\\App\\\\Models\\\\Tag'
                     AND LOWER(t.title) = LOWER(%s)",
                    $edition_value
                ));

                $contacts = $wpdb->get_results($wpdb->prepare(
                    "SELECT s.id, s.email, s.first_name, s.last_name, s.status, s.created_at, s.avatar, %s as edition
                     FROM $subscribers_table s
                     INNER JOIN $tags_table sp ON s.id = sp.subscriber_id
                     INNER JOIN $terms_table t ON sp.object_id = t.id
                     WHERE sp.object_type = 'FluentCrm\\\\App\\\\Models\\\\Tag'
                     AND LOWER(t.title) = LOWER(%s)
                     ORDER BY s.created_at DESC
                     LIMIT %d OFFSET %d",
                    $edition_value,
                    $edition_value,
                    $per_page,
                    $offset
                ));
            }
        } else {
            // Standard edition meta filtering for other courses
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT s.id)
                 FROM $subscribers_table s
                 INNER JOIN $meta_table m ON s.id = m.subscriber_id
                 WHERE m.`key` = %s AND m.value = %s",
                $course_field,
                $edition_value
            ));

            $contacts = $wpdb->get_results($wpdb->prepare(
                "SELECT s.id, s.email, s.first_name, s.last_name, s.status, s.created_at, s.avatar, m.value as edition
                 FROM $subscribers_table s
                 INNER JOIN $meta_table m ON s.id = m.subscriber_id
                 WHERE m.`key` = %s AND m.value = %s
                 ORDER BY s.created_at DESC
                 LIMIT %d OFFSET %d",
                $course_field,
                $edition_value,
                $per_page,
                $offset
            ));
        }

        // Get course category for order lookup
        $category = self::$courses[$course_field]['category'] ?? '';

        // Enrich contacts with order data and phone
        foreach ($contacts as &$contact) {
            // Get phone number from FluentCRM subscriber
            $contact->phone = $this->get_contact_phone($contact->id);

            $order_data = $this->get_contact_order_data($contact->email, $category, $edition_value);
            $contact->product_name = $order_data['product_name'];
            $contact->order_count_text = $order_data['order_count_text'] ?? '';
            $contact->order_total = $order_data['order_total'];
            $contact->order_date = $order_data['order_date'];
            $contact->order_count = $order_data['order_count'] ?? 0;
            $contact->is_asit_member = $order_data['is_asit_member'] ?? false;
            $contact->asit_number = $order_data['asit_number'] ?? '';
            $contact->all_product_names = $order_data['all_product_names'] ?? '';

            // WooCommerce order meta fields
            $contact->specialities = $order_data['specialities'] ?? '';
            $contact->exam_date = $order_data['exam_date'] ?? '';
        }

        return [
            'contacts' => $contacts,
            'total' => (int) $total,
            'pages' => ceil($total / $per_page),
            'current_page' => $page,
            'per_page' => $per_page
        ];
    }

    /**
     * Get order data for a contact
     */
    private function get_contact_order_data($email, $category, $edition_value)
    {
        global $wpdb;

        $default = [
            'product_name' => '-',
            'order_count_text' => '',
            'order_total' => 0,
            'order_date' => null,
            'order_count' => 0,
            'is_asit_member' => false,
            'asit_number' => '',
            'all_product_names' => '',
            'specialities' => '',
            'exam_date' => '',
            'dob' => ''
        ];

        if (!class_exists('WooCommerce') || empty($category)) {
            return $default;
        }

        // Special handling for library-subscription - no edition meta to check
        $is_lib_sub = ($category === 'library-subscription');

        // Check for HPOS
        if (
            class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') &&
            \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
        ) {

            // HPOS enabled - fetch ALL orders
            $orders_table = $wpdb->prefix . 'wc_orders';
            $meta_table = $wpdb->prefix . 'wc_orders_meta';

            if ($is_lib_sub) {
                // For library-subscription, don't filter by edition meta
                $orders = $wpdb->get_results($wpdb->prepare(
                    "SELECT DISTINCT o.id, o.total_amount, o.date_created_gmt
                     FROM $orders_table o
                     WHERE o.billing_email = %s
                     AND o.status IN ('wc-completed', 'wc-processing')
                     AND EXISTS (
                         SELECT 1 FROM $meta_table m
                         WHERE m.order_id = o.id
                         AND m.meta_key LIKE %s
                     )
                     ORDER BY o.date_created_gmt DESC",
                    $email,
                    '_edition_name_' . $category . '%'
                ));
            } else {
                $orders = $wpdb->get_results($wpdb->prepare(
                    "SELECT o.id, o.total_amount, o.date_created_gmt
                     FROM $orders_table o
                     INNER JOIN $meta_table m ON o.id = m.order_id
                     WHERE o.billing_email = %s
                     AND m.meta_key = %s AND m.meta_value = %s
                     AND o.status IN ('wc-completed', 'wc-processing')
                     ORDER BY o.date_created_gmt DESC",
                    $email,
                    '_edition_name_' . $category,
                    $edition_value
                ));
            }
        } else {
            // Legacy post-based orders - fetch ALL orders
            if ($is_lib_sub) {
                // For library-subscription, don't filter by edition meta value
                $orders = $wpdb->get_results($wpdb->prepare(
                    "SELECT DISTINCT p.ID, pm_total.meta_value as total_amount, p.post_date as date_created_gmt
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
                     INNER JOIN {$wpdb->postmeta} pm_edition ON p.ID = pm_edition.post_id
                     LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
                     WHERE p.post_type = 'shop_order'
                     AND p.post_status IN ('wc-completed', 'wc-processing')
                     AND pm_email.meta_value = %s
                     AND pm_edition.meta_key LIKE %s
                     ORDER BY p.post_date DESC",
                    $email,
                    '_edition_name_' . $category . '%'
                ));
            } else {
                $orders = $wpdb->get_results($wpdb->prepare(
                    "SELECT p.ID, pm_total.meta_value as total_amount, p.post_date as date_created_gmt
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
                     INNER JOIN {$wpdb->postmeta} pm_edition ON p.ID = pm_edition.post_id
                     LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
                     WHERE p.post_type = 'shop_order'
                     AND p.post_status IN ('wc-completed', 'wc-processing')
                     AND pm_email.meta_value = %s
                     AND pm_edition.meta_key = %s AND pm_edition.meta_value = %s
                     ORDER BY p.post_date DESC",
                    $email,
                    '_edition_name_' . $category,
                    $edition_value
                ));
            }
        }

        // If no orders found with specific edition meta, try a broader search
        if (empty($orders) && !$is_lib_sub) {
            // Try searching by product category or term
            if (
                class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') &&
                \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
            ) {

                // HPOS - get any orders for this email with the category meta key (regardless of value)
                $orders = $wpdb->get_results($wpdb->prepare(
                    "SELECT DISTINCT o.id, o.total_amount, o.date_created_gmt
                     FROM $orders_table o
                     INNER JOIN $meta_table m ON o.id = m.order_id
                     WHERE o.billing_email = %s
                     AND m.meta_key = %s
                     AND o.status IN ('wc-completed', 'wc-processing')
                     ORDER BY o.date_created_gmt DESC",
                    $email,
                    '_edition_name_' . $category
                ));
            } else {
                // Legacy - get any orders for this email with the category meta key
                $orders = $wpdb->get_results($wpdb->prepare(
                    "SELECT DISTINCT p.ID, pm_total.meta_value as total_amount, p.post_date as date_created_gmt
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
                     INNER JOIN {$wpdb->postmeta} pm_edition ON p.ID = pm_edition.post_id
                     LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
                     WHERE p.post_type = 'shop_order'
                     AND p.post_status IN ('wc-completed', 'wc-processing')
                     AND pm_email.meta_value = %s
                     AND pm_edition.meta_key = %s
                     ORDER BY p.post_date DESC",
                    $email,
                    '_edition_name_' . $category
                ));
            }
        }

        if (empty($orders)) {
            return $default;
        }

        // Collect all product names and calculate total
        $product_names = [];
        $total_amount = 0;
        $most_recent_date = null;
        $order_count = count($orders);
        $is_asit_member = false;
        $asit_number = '';

        // WooCommerce order meta fields (get from most recent order)
        $specialities = '';
        $exam_date = '';
        $dob = '';

        foreach ($orders as $order) {
            $order_id = isset($order->id) ? $order->id : $order->ID;
            $order_total = floatval($order->total_amount);
            $order_date = $order->date_created_gmt;

            // Track the most recent date
            if ($most_recent_date === null) {
                $most_recent_date = $order_date;
            }

            // Add to total
            $total_amount += $order_total;

            // Get product names from this order and check for ASiT membership
            $wc_order = wc_get_order($order_id);
            if ($wc_order) {
                $items = $wc_order->get_items();
                foreach ($items as $item) {
                    $product_names[] = $item->get_name();
                }

                // Check if ASiT membership number exists on this order
                if (!$is_asit_member) {
                    $found_asit_number = $wc_order->get_meta('_asit_membership_number');
                    if (empty($found_asit_number)) {
                        $found_asit_number = $wc_order->get_meta('_wcem_asit_number');
                    }
                    if (!empty($found_asit_number)) {
                        $is_asit_member = true;
                        $asit_number = $found_asit_number;
                    }
                }

                // Get additional order meta fields (from most recent order with data)
                if (empty($specialities)) {
                    $specialities = $wc_order->get_meta('billing_specialities');
                    if (empty($specialities)) {
                        $specialities = $wc_order->get_meta('_billing_specialities');
                    }
                }
                if (empty($exam_date)) {
                    $exam_date = $wc_order->get_meta('billing_exam_date');
                    if (empty($exam_date)) {
                        $exam_date = $wc_order->get_meta('_billing_exam_date');
                    }
                }
                if (empty($dob)) {
                    $dob = $wc_order->get_meta('billing_dob');
                    if (empty($dob)) {
                        $dob = $wc_order->get_meta('_billing_dob');
                    }
                }
            }
        }

        // Format product name display
        $product_display = '-';
        $order_count_text = '';
        $total_items = count($product_names);

        if (!empty($product_names)) {
            // Truncate product name to 30 characters for better display
            $first_product = $product_names[0];
            if (strlen($first_product) > 30) {
                $first_product = substr($first_product, 0, 30) . '...';
            }

            $product_display = $first_product;

            // Format item count text for multiple items (across all orders)
            if ($total_items > 1) {
                $additional_items = $total_items - 1;
                $order_count_text = '+' . $additional_items . ' more item' . ($additional_items > 1 ? 's' : '');
            }
        }

        return [
            'product_name' => $product_display,
            'order_count_text' => $order_count_text,
            'order_total' => $total_amount,
            'order_date' => $most_recent_date,
            'order_count' => $order_count,
            'total_items' => $total_items,
            'is_asit_member' => $is_asit_member,
            'asit_number' => $asit_number,
            'all_product_names' => implode(', ', $product_names),
            'specialities' => $specialities,
            'exam_date' => $exam_date,
            'dob' => $dob
        ];
    }

    /**
     * Get edition count
     */
    private function get_edition_count($course_field, $edition_value)
    {
        global $wpdb;

        $meta_table = $wpdb->prefix . 'fc_subscriber_meta';
        $subscribers_table = $wpdb->prefix . 'fc_subscribers';
        $tags_table = $wpdb->prefix . 'fc_subscriber_pivot';
        $terms_table = $wpdb->prefix . 'fc_tags';

        // Special handling for Lib Sub - count by tags instead of edition meta
        if ($course_field === 'library_sub_edition' && $edition_value === 'Library-Subscription') {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT s.id)
                 FROM $subscribers_table s
                 INNER JOIN $tags_table sp ON s.id = sp.subscriber_id
                 INNER JOIN $terms_table t ON sp.object_id = t.id
                 WHERE sp.object_type = 'FluentCrm\\\\App\\\\Models\\\\Tag'
                 AND LOWER(t.title) = LOWER(%s)",
                $edition_value
            ));
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT s.id)
             FROM $subscribers_table s
             INNER JOIN $meta_table m ON s.id = m.subscriber_id
             WHERE m.`key` = %s AND m.value = %s",
            $course_field,
            $edition_value
        ));
    }

    /**
     * Get revenue stats for an edition from WooCommerce orders
     */
    private function get_edition_revenue_stats($course_field, $edition_value)
    {
        global $wpdb;

        if (!class_exists('WooCommerce')) {
            return [
                'total_revenue' => 0,
                'order_count' => 0,
                'avg_order_value' => 0
            ];
        }

        // Get the course category from our config
        $category = '';
        foreach (self::$courses as $field => $course) {
            if ($field === $course_field) {
                $category = $course['category'];
                break;
            }
        }

        if (empty($category)) {
            return [
                'total_revenue' => 0,
                'order_count' => 0,
                'avg_order_value' => 0
            ];
        }

        // Special handling for library-subscription - no edition value to filter by
        $is_lib_sub = ($category === 'library-subscription');

        // Query WooCommerce orders with this edition
        // Check for HPOS (High-Performance Order Storage) first
        if (
            class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') &&
            \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
        ) {
            // HPOS enabled - use orders table
            $orders_table = $wpdb->prefix . 'wc_orders';
            $meta_table = $wpdb->prefix . 'wc_orders_meta';

            if ($is_lib_sub) {
                // For library-subscription, get all orders with library-subscription category
                $results = $wpdb->get_row($wpdb->prepare(
                    "SELECT COUNT(DISTINCT o.id) as order_count, COALESCE(SUM(o.total_amount), 0) as total_revenue
                     FROM $orders_table o
                     INNER JOIN $meta_table m ON o.id = m.order_id
                     WHERE m.meta_key LIKE %s
                     AND o.status IN ('wc-completed', 'wc-processing')",
                    '_edition_name_' . $category . '%'
                ));
            } else {
                $results = $wpdb->get_row($wpdb->prepare(
                    "SELECT COUNT(DISTINCT o.id) as order_count, COALESCE(SUM(o.total_amount), 0) as total_revenue
                     FROM $orders_table o
                     INNER JOIN $meta_table m ON o.id = m.order_id
                     WHERE m.meta_key = %s AND m.meta_value = %s
                     AND o.status IN ('wc-completed', 'wc-processing')",
                    '_edition_name_' . $category,
                    $edition_value
                ));
            }
        } else {
            // Legacy post-based orders
            if ($is_lib_sub) {
                // For library-subscription, get all orders with library-subscription category
                $results = $wpdb->get_row($wpdb->prepare(
                    "SELECT COUNT(DISTINCT p.ID) as order_count, COALESCE(SUM(pm2.meta_value), 0) as total_revenue
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                     LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_order_total'
                     WHERE p.post_type = 'shop_order'
                     AND p.post_status IN ('wc-completed', 'wc-processing')
                     AND pm.meta_key LIKE %s",
                    '_edition_name_' . $category . '%'
                ));
            } else {
                $results = $wpdb->get_row($wpdb->prepare(
                    "SELECT COUNT(DISTINCT p.ID) as order_count, COALESCE(SUM(pm2.meta_value), 0) as total_revenue
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                     LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_order_total'
                     WHERE p.post_type = 'shop_order'
                     AND p.post_status IN ('wc-completed', 'wc-processing')
                     AND pm.meta_key = %s AND pm.meta_value = %s",
                    '_edition_name_' . $category,
                    $edition_value
                ));
            }
        }

        $total_revenue = floatval($results->total_revenue ?? 0);
        $order_count = intval($results->order_count ?? 0);
        $avg_order_value = $order_count > 0 ? $total_revenue / $order_count : 0;

        return [
            'total_revenue' => $total_revenue,
            'order_count' => $order_count,
            'avg_order_value' => $avg_order_value
        ];
    }

    /**
     * AJAX: Get editions
     */
    public function ajax_get_editions()
    {
        check_ajax_referer('fcef_nonce', 'nonce');

        $course_field = sanitize_text_field($_POST['course'] ?? '');

        if (empty($course_field) || !isset(self::$courses[$course_field])) {
            wp_send_json_error(['message' => 'Invalid course']);
        }

        $editions = $this->get_editions_for_course($course_field);

        wp_send_json_success([
            'editions' => $editions,
            'course' => $course_field,
            'course_name' => self::$courses[$course_field]['label']
        ]);
    }

    /**
     * AJAX: Get all editions
     */
    public function ajax_get_all_editions()
    {
        check_ajax_referer('fcef_nonce', 'nonce');

        $all_editions = [];

        foreach (self::$courses as $field => $course) {
            $editions = $this->get_editions_for_course($field);
            $edition_counts = [];

            foreach ($editions as $edition) {
                $count = $this->get_edition_count($field, $edition);
                $edition_counts[] = [
                    'value' => $edition,
                    'count' => $count
                ];
            }

            $all_editions[$field] = [
                'label' => $course['label'],
                'editions' => $edition_counts
            ];
        }

        wp_send_json_success(['courses' => $all_editions]);
    }

    /**
     * AJAX: Get stats for filtered results
     */
    public function ajax_get_stats()
    {
        check_ajax_referer('fcef_nonce', 'nonce');

        $course_field = sanitize_text_field($_POST['course'] ?? '');
        $edition_value = sanitize_text_field($_POST['edition'] ?? '');

        if (empty($course_field) || !isset(self::$courses[$course_field])) {
            wp_send_json_error(['message' => 'Invalid course']);
        }

        // Get contact count
        $contact_count = $this->get_edition_count($course_field, $edition_value);

        // Get status breakdown
        global $wpdb;
        $meta_table = $wpdb->prefix . 'fc_subscriber_meta';
        $subscribers_table = $wpdb->prefix . 'fc_subscribers';
        $tags_table = $wpdb->prefix . 'fc_subscriber_pivot';
        $terms_table = $wpdb->prefix . 'fc_tags';

        // Special handling for Lib Sub - filter by tags
        if ($course_field === 'library_sub_edition' && $edition_value === 'Library-Subscription') {
            $status_breakdown = $wpdb->get_results($wpdb->prepare(
                "SELECT s.status, COUNT(DISTINCT s.id) as count
                 FROM $subscribers_table s
                 INNER JOIN $tags_table sp ON s.id = sp.subscriber_id
                 INNER JOIN $terms_table t ON sp.object_id = t.id
                 WHERE sp.object_type = 'FluentCrm\\\\App\\\\Models\\\\Tag'
                 AND LOWER(t.title) = LOWER(%s)
                 GROUP BY s.status",
                $edition_value
            ), OBJECT_K);
        } else {
            $status_breakdown = $wpdb->get_results($wpdb->prepare(
                "SELECT s.status, COUNT(DISTINCT s.id) as count
                 FROM $subscribers_table s
                 INNER JOIN $meta_table m ON s.id = m.subscriber_id
                 WHERE m.`key` = %s AND m.value = %s
                 GROUP BY s.status",
                $course_field,
                $edition_value
            ), OBJECT_K);
        }

        // Get revenue stats from WooCommerce
        $revenue_stats = $this->get_edition_revenue_stats($course_field, $edition_value);

        wp_send_json_success([
            'total_contacts' => $contact_count,
            'subscribed' => intval($status_breakdown['subscribed']->count ?? 0),
            'pending' => intval($status_breakdown['pending']->count ?? 0),
            'unsubscribed' => intval($status_breakdown['unsubscribed']->count ?? 0),
            'total_revenue' => $revenue_stats['total_revenue'],
            'order_count' => $revenue_stats['order_count'],
            'avg_order_value' => $revenue_stats['avg_order_value']
        ]);
    }

    /**
     * AJAX: Filter contacts
     */
    public function ajax_filter_contacts()
    {
        check_ajax_referer('fcef_nonce', 'nonce');

        $course_field = sanitize_text_field($_POST['course'] ?? '');
        $edition_value = sanitize_text_field($_POST['edition'] ?? '');
        $page = max(1, intval($_POST['page'] ?? 1));
        $per_page = max(10, min(100, intval($_POST['per_page'] ?? 20)));

        if (empty($course_field) || !isset(self::$courses[$course_field])) {
            wp_send_json_error(['message' => 'Invalid course']);
        }

        if (empty($edition_value)) {
            wp_send_json_error(['message' => 'Please select an edition']);
        }

        $result = $this->get_contacts_by_edition($course_field, $edition_value, $page, $per_page);

        wp_send_json_success($result);
    }

    /**
     * AJAX: Export contacts to CSV
     */
    public function ajax_export_contacts()
    {
        check_ajax_referer('fcef_nonce', 'nonce');

        $course_field = sanitize_text_field($_POST['course'] ?? '');
        $edition_value = sanitize_text_field($_POST['edition'] ?? '');

        if (empty($course_field) || !isset(self::$courses[$course_field])) {
            wp_send_json_error(['message' => 'Invalid course']);
        }

        if (empty($edition_value)) {
            wp_send_json_error(['message' => 'Please select an edition']);
        }

        // Get all contacts without pagination
        $result = $this->get_contacts_by_edition($course_field, $edition_value, 1, 10000);
        $contacts = $result['contacts'];

        // Build CSV data
        $csv_data = [];

        // Header row
        $csv_data[] = [
            'Name',
            'Email',
            'Phone',
            'Course',
            'Price',
            'Edition',
            'Status',
            'Specialities',
            'Exam Date',
            'ASiT Member No.',
            'Added Date'
        ];

        // Data rows
        foreach ($contacts as $contact) {
            $full_name = trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? ''));
            if (empty($full_name)) {
                $full_name = 'No Name';
            }

            $csv_data[] = [
                $full_name,
                $contact->email ?? '',
                $contact->phone ?? '',
                $contact->all_product_names ?? ($contact->product_name ?? '-'),
                $contact->order_total ? number_format($contact->order_total, 2) : '0.00',
                $contact->edition ?? '',
                $contact->status ?? '',
                $contact->specialities ?? '',
                $contact->exam_date ?? '',
                $contact->asit_number ?? '',
                $contact->created_at ? date('Y-m-d', strtotime($contact->created_at)) : ''
            ];
        }

        wp_send_json_success([
            'csv_data' => $csv_data,
            'filename' => sanitize_file_name(self::$courses[$course_field]['label'] . '-' . $edition_value . '-contacts.csv')
        ]);
    }

    /**
     * Render page
     */
    public function render_page()
    {
?>
        <div class="fcef-app">
            <!-- Header -->
            <div class="fcef-header">
                <div class="fcef-header-content">
                    <div class="fcef-header-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                    </div>
                    <div class="fcef-header-text">
                        <h1><?php _e('Contacts by Edition', 'fluentcrm-edition-contacts'); ?></h1>
                        <p><?php _e('Filter and browse contacts by their enrolled course edition', 'fluentcrm-edition-contacts'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="fcef-card fcef-filter-card">
                <div class="fcef-filter-bar">
                    <div class="fcef-filter-group">
                        <label for="fcef-course"><?php _e('Course', 'fluentcrm-edition-contacts'); ?></label>
                        <div class="fcef-select-wrap">
                            <select id="fcef-course" class="fcef-select">
                                <option value=""><?php _e('Select Course...', 'fluentcrm-edition-contacts'); ?></option>
                                <?php foreach (self::$courses as $field => $course): ?>
                                    <option value="<?php echo esc_attr($field); ?>"><?php echo esc_html($course['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="fcef-filter-group">
                        <label for="fcef-edition"><?php _e('Edition', 'fluentcrm-edition-contacts'); ?></label>
                        <div class="fcef-select-wrap">
                            <select id="fcef-edition" class="fcef-select" disabled>
                                <option value=""><?php _e('Select Edition...', 'fluentcrm-edition-contacts'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="fcef-filter-buttons">
                        <button type="button" id="fcef-filter-btn" class="fcef-btn fcef-btn-primary" disabled>
                            <svg viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                            </svg>
                            <?php _e('Search', 'fluentcrm-edition-contacts'); ?>
                        </button>
                        <button type="button" id="fcef-reset-btn" class="fcef-btn fcef-btn-ghost">
                            <svg viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                            </svg>
                            <?php _e('Reset', 'fluentcrm-edition-contacts'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="fcef-section">
                <div class="fcef-section-header">
                    <h2><?php _e('Edition Overview', 'fluentcrm-edition-contacts'); ?></h2>
                    <span class="fcef-section-hint"><?php _e('Click any edition to filter contacts', 'fluentcrm-edition-contacts'); ?></span>
                </div>
                <div id="fcef-stats-container" class="fcef-stats-grid">
                    <div class="fcef-loading-state">
                        <div class="fcef-spinner"></div>
                        <span><?php _e('Loading editions...', 'fluentcrm-edition-contacts'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Results Section -->
            <div class="fcef-section fcef-results-section" id="fcef-results" style="display: none;">
                <div class="fcef-section-header">
                    <div class="fcef-results-title">
                        <h2 id="fcef-results-title"><?php _e('Contacts', 'fluentcrm-edition-contacts'); ?></h2>
                        <span class="fcef-badge fcef-badge-primary" id="fcef-results-count">0 contacts</span>
                    </div>
                    <button type="button" id="fcef-export-btn" class="fcef-btn fcef-btn-outline">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                        <?php _e('Export CSV', 'fluentcrm-edition-contacts'); ?>
                    </button>
                </div>

                <div class="fcef-table-container">
                    <table class="fcef-table">
                        <thead>
                            <tr>
                                <th class="fcef-col-checkbox"><input type="checkbox" id="fcef-select-all"></th>
                                <th class="fcef-col-contact"><?php _e('Contact', 'fluentcrm-edition-contacts'); ?></th>
                                <th class="fcef-col-phone"><?php _e('Phone', 'fluentcrm-edition-contacts'); ?></th>
                                <?php
                                // ========== COURSE/PRODUCT NAME COLUMN ==========
                                // To hide this column, comment out the next line and also comment out
                                // the corresponding row in assets/js/admin.js (around line 203)
                                ?>
                                <th class="fcef-col-course"><?php _e('Course', 'fluentcrm-edition-contacts'); ?></th>
                                <?php // ================================================
                                ?>
                                <th class="fcef-col-price"><?php _e('Price', 'fluentcrm-edition-contacts'); ?></th>
                                <th class="fcef-col-edition"><?php _e('Edition', 'fluentcrm-edition-contacts'); ?></th>
                                <th class="fcef-col-specialities"><?php _e('Specialities', 'fluentcrm-edition-contacts'); ?></th>
                                <th class="fcef-col-exam-date"><?php _e('Exam Date', 'fluentcrm-edition-contacts'); ?></th>
                                <th class="fcef-col-status"><?php _e('Status', 'fluentcrm-edition-contacts'); ?></th>
                                <th class="fcef-col-date"><?php _e('Added', 'fluentcrm-edition-contacts'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="fcef-contacts-body"></tbody>
                    </table>
                </div>

                <div class="fcef-pagination" id="fcef-pagination"></div>

                <!-- Edition Stats Summary -->
                <div class="fcef-edition-stats" id="fcef-edition-stats">
                    <div class="fcef-stats-summary">
                        <div class="fcef-stat-item">
                            <div class="fcef-stat-icon fcef-stat-icon-contacts">
                                <svg viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                                </svg>
                            </div>
                            <div class="fcef-stat-content">
                                <span class="fcef-stat-value" id="stat-total-contacts">0</span>
                                <span class="fcef-stat-label"><?php _e('Total Enrolled', 'fluentcrm-edition-contacts'); ?></span>
                            </div>
                        </div>

                        <div class="fcef-stat-item">
                            <div class="fcef-stat-icon fcef-stat-icon-subscribed">
                                <svg viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="fcef-stat-content">
                                <span class="fcef-stat-value" id="stat-subscribed">0</span>
                                <span class="fcef-stat-label"><?php _e('Subscribed', 'fluentcrm-edition-contacts'); ?></span>
                            </div>
                        </div>

                        <div class="fcef-stat-item">
                            <div class="fcef-stat-icon fcef-stat-icon-revenue">
                                <svg viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="fcef-stat-content">
                                <span class="fcef-stat-value" id="stat-revenue"><?php echo function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$'; ?>0</span>
                                <span class="fcef-stat-label"><?php _e('Total Revenue', 'fluentcrm-edition-contacts'); ?></span>
                            </div>
                        </div>

                        <div class="fcef-stat-item">
                            <div class="fcef-stat-icon fcef-stat-icon-orders">
                                <svg viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3zM16 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM6.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z" />
                                </svg>
                            </div>
                            <div class="fcef-stat-content">
                                <span class="fcef-stat-value" id="stat-orders">0</span>
                                <span class="fcef-stat-label"><?php _e('Total Orders', 'fluentcrm-edition-contacts'); ?></span>
                            </div>
                        </div>

                        <div class="fcef-stat-item">
                            <div class="fcef-stat-icon fcef-stat-icon-avg">
                                <svg viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5 2a2 2 0 00-2 2v14l3.5-2 3.5 2 3.5-2 3.5 2V4a2 2 0 00-2-2H5zm2.5 3a1.5 1.5 0 100 3 1.5 1.5 0 000-3zm6.207.293a1 1 0 00-1.414 0l-6 6a1 1 0 101.414 1.414l6-6a1 1 0 000-1.414zM12.5 10a1.5 1.5 0 100 3 1.5 1.5 0 000-3z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="fcef-stat-content">
                                <span class="fcef-stat-value" id="stat-avg"><?php echo function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$'; ?>0</span>
                                <span class="fcef-stat-label"><?php _e('Avg. Order Value', 'fluentcrm-edition-contacts'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading Overlay -->
            <div class="fcef-overlay" id="fcef-loading" style="display: none;">
                <div class="fcef-overlay-content">
                    <div class="fcef-spinner-lg"></div>
                </div>
            </div>
        </div>
<?php
    }
}

// Initialize
FluentCRM_Edition_Contacts::get_instance();
