<?php

/**
 * WooCommerce Settings Tab: Edition Contacts
 *
 * Adds an "Edition Contacts" tab under WooCommerce > Settings where admins can
 * add, edit, remove, and reorder courses without editing plugin code.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FCEF_WC_Settings
{
    const TAB_ID = 'fcef_courses';

    private static $row_index = -1;

    public static function init()
    {
        add_filter('woocommerce_settings_tabs_array', [__CLASS__, 'add_settings_tab'], 50);
        add_action('woocommerce_settings_tabs_' . self::TAB_ID, [__CLASS__, 'render_settings_page']);
        add_action('woocommerce_update_options_' . self::TAB_ID, [__CLASS__, 'save_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function add_settings_tab($tabs)
    {
        $tabs[self::TAB_ID] = __('Edition Contacts', 'fluentcrm-edition-contacts');
        return $tabs;
    }

    public static function enqueue_assets($hook)
    {
        if ($hook !== 'woocommerce_page_wc-settings') {
            return;
        }

        if (!isset($_GET['tab']) || $_GET['tab'] !== self::TAB_ID) {
            return;
        }

        wp_register_style('fcef-wc-settings', false);
        wp_enqueue_style('fcef-wc-settings');
        wp_add_inline_style('fcef-wc-settings', self::get_inline_css());

        wp_register_script('fcef-wc-settings', '', ['jquery'], FCEF_VERSION, true);
        wp_enqueue_script('fcef-wc-settings');
        wp_add_inline_script('fcef-wc-settings', self::get_inline_js());
    }

    public static function render_settings_page()
    {
        self::$row_index = -1;
        $courses = get_option(FCEF_OPTION_KEY, []);

        if (!is_array($courses)) {
            $courses = [];
        }

        if (isset($_GET['fcef_saved']) && $_GET['fcef_saved'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>' .
                esc_html__('Course configuration saved.', 'fluentcrm-edition-contacts') .
                '</strong></p></div>';
        }

        if (isset($_GET['fcef_error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' .
                esc_html(wp_unslash($_GET['fcef_error'])) .
                '</p></div>';
        }
        ?>
        <div class="fcef-settings-wrap">
            <h2><?php _e('Edition Contacts - Course Configuration', 'fluentcrm-edition-contacts'); ?></h2>
            <p class="description">
                <?php _e('Configure the courses that appear in the FluentCRM "Contacts by Edition" page. Each row maps a FluentCRM subscriber meta key to a WooCommerce product category.', 'fluentcrm-edition-contacts'); ?>
            </p>

            <div class="fcef-settings-help">
                <strong><?php _e('How the fields map:', 'fluentcrm-edition-contacts'); ?></strong>
                <ul>
                    <li><code>Course Field Key</code> - <?php echo wp_kses_post(__('The FluentCRM subscriber meta <code>key</code> that stores the edition value, for example <code>frcophth_p1_edition</code>. Lowercase, no spaces.', 'fluentcrm-edition-contacts')); ?></li>
                    <li><code>Display Label</code> - <?php echo wp_kses_post(__('Human-friendly name shown in dropdowns and cards, for example <code>FRCOphth Part 1</code>.', 'fluentcrm-edition-contacts')); ?></li>
                    <li><code>Product Category</code> - <?php echo wp_kses_post(__('The WooCommerce category slug used in order meta keys like <code>_edition_name_&lt;category&gt;</code>, for example <code>frcophth-part-1</code>.', 'fluentcrm-edition-contacts')); ?></li>
                </ul>
            </div>

            <form method="post" id="fcef-settings-form">
                <?php wp_nonce_field('fcef_save_courses', 'fcef_nonce'); ?>

                <table class="wp-list-table widefat fixed striped fcef-courses-table" id="fcef-courses-table">
                    <thead>
                        <tr>
                            <th class="fcef-col-handle"></th>
                            <th><?php _e('Course Field Key', 'fluentcrm-edition-contacts'); ?> <span class="required">*</span></th>
                            <th><?php _e('Display Label', 'fluentcrm-edition-contacts'); ?> <span class="required">*</span></th>
                            <th><?php _e('Internal Name', 'fluentcrm-edition-contacts'); ?></th>
                            <th><?php _e('Product Category', 'fluentcrm-edition-contacts'); ?> <span class="required">*</span></th>
                            <th class="fcef-col-actions"><?php _e('Actions', 'fluentcrm-edition-contacts'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="fcef-courses-body">
                        <?php
                        if (empty($courses)) {
                            self::render_course_row('', []);
                        } else {
                            foreach ($courses as $field => $course) {
                                self::render_course_row($field, $course);
                            }
                        }
                        ?>
                    </tbody>
                </table>

                <p class="fcef-add-row-wrap">
                    <button type="button" class="button button-secondary" id="fcef-add-course">
                        <span class="dashicons dashicons-plus-alt2" style="vertical-align: middle; margin-top: 3px;"></span>
                        <?php _e('Add Course', 'fluentcrm-edition-contacts'); ?>
                    </button>
                </p>

                <p class="submit">
                    <button type="submit" name="fcef_save" value="1" class="button button-primary button-large">
                        <?php _e('Save Changes', 'fluentcrm-edition-contacts'); ?>
                    </button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=fluentcrm-edition-contacts')); ?>" class="button button-secondary">
                        <?php _e('Go to Contacts by Edition', 'fluentcrm-edition-contacts'); ?>
                    </a>
                </p>
            </form>

            <script type="text/template" id="fcef-row-template">
                <?php self::render_course_row('', [], true); ?>
            </script>
        </div>
        <?php
    }

    private static function render_course_row($field, $course, $is_template = false)
    {
        $index = $is_template ? '__INDEX__' : self::next_index();
        $label = $course['label'] ?? '';
        $name = $course['name'] ?? '';
        $category = $course['category'] ?? '';
        ?>
        <tr class="fcef-course-row<?php echo $is_template ? ' fcef-template-row' : ''; ?>">
            <td class="fcef-col-handle">
                <span class="dashicons dashicons-menu" title="<?php esc_attr_e('Drag to reorder', 'fluentcrm-edition-contacts'); ?>"></span>
            </td>
            <td>
                <input type="text" name="fcef_courses[<?php echo esc_attr($index); ?>][field]" value="<?php echo esc_attr($field); ?>" class="regular-text fcef-input-field" placeholder="frcophth_p1_edition" <?php disabled($is_template); ?> />
            </td>
            <td>
                <input type="text" name="fcef_courses[<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($label); ?>" class="regular-text" placeholder="FRCOphth Part 1" <?php disabled($is_template); ?> />
            </td>
            <td>
                <input type="text" name="fcef_courses[<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($name); ?>" class="regular-text" placeholder="<?php esc_attr_e('Optional, defaults to label', 'fluentcrm-edition-contacts'); ?>" <?php disabled($is_template); ?> />
            </td>
            <td>
                <input type="text" name="fcef_courses[<?php echo esc_attr($index); ?>][category]" value="<?php echo esc_attr($category); ?>" class="regular-text fcef-input-category" placeholder="frcophth-part-1" <?php disabled($is_template); ?> />
            </td>
            <td class="fcef-col-actions">
                <button type="button" class="button button-link-delete fcef-remove-row">
                    <?php _e('Remove', 'fluentcrm-edition-contacts'); ?>
                </button>
            </td>
        </tr>
        <?php
    }

    private static function next_index()
    {
        self::$row_index++;
        return self::$row_index;
    }

    public static function save_settings()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        if (!isset($_POST['fcef_nonce']) || !wp_verify_nonce($_POST['fcef_nonce'], 'fcef_save_courses')) {
            self::redirect_with_error(__('Security check failed. Please try again.', 'fluentcrm-edition-contacts'));
            return;
        }

        $raw = $_POST['fcef_courses'] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }

        $clean = [];
        $seen_fields = [];
        $errors = [];

        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $field = isset($row['field']) ? sanitize_key(wp_unslash($row['field'])) : '';
            $label = isset($row['label']) ? sanitize_text_field(wp_unslash($row['label'])) : '';
            $name = isset($row['name']) ? sanitize_text_field(wp_unslash($row['name'])) : '';
            $category = isset($row['category']) ? sanitize_title(wp_unslash($row['category'])) : '';

            if ($field === '' && $label === '' && $category === '') {
                continue;
            }

            if ($field === '') {
                $errors[] = sprintf(__('Row "%s" is missing a Course Field Key.', 'fluentcrm-edition-contacts'), $label ?: $category);
                continue;
            }

            if ($label === '') {
                $errors[] = sprintf(__('Row "%s" is missing a Display Label.', 'fluentcrm-edition-contacts'), $field);
                continue;
            }

            if ($category === '') {
                $errors[] = sprintf(__('Row "%s" is missing a Product Category.', 'fluentcrm-edition-contacts'), $field);
                continue;
            }

            if (isset($seen_fields[$field])) {
                $errors[] = sprintf(__('Duplicate Course Field Key: %s', 'fluentcrm-edition-contacts'), $field);
                continue;
            }

            $seen_fields[$field] = true;
            $clean[$field] = [
                'label' => $label,
                'name' => $name !== '' ? $name : $label,
                'category' => $category,
            ];
        }

        if (!empty($errors)) {
            self::redirect_with_error(implode(' | ', $errors));
            return;
        }

        update_option(FCEF_OPTION_KEY, $clean);

        if (class_exists('FluentCRM_Edition_Contacts')) {
            FluentCRM_Edition_Contacts::refresh_courses_cache();
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'wc-settings',
            'tab' => self::TAB_ID,
            'fcef_saved' => '1',
        ], admin_url('admin.php')));
        exit;
    }

    private static function redirect_with_error($message)
    {
        wp_safe_redirect(add_query_arg([
            'page' => 'wc-settings',
            'tab' => self::TAB_ID,
            'fcef_error' => rawurlencode($message),
        ], admin_url('admin.php')));
        exit;
    }

    private static function get_inline_css()
    {
        return '
        .fcef-settings-wrap { max-width: 1200px; }
        .fcef-settings-wrap h2 { margin-top: 0; }
        .fcef-settings-help {
            background: #f6f7f7;
            border-left: 4px solid #2271b1;
            padding: 12px 16px;
            margin: 16px 0 24px;
            border-radius: 2px;
        }
        .fcef-settings-help ul { margin: 8px 0 0 18px; }
        .fcef-settings-help code { background: #fff; padding: 1px 5px; border-radius: 3px; font-size: 12px; }
        .fcef-courses-table th { font-weight: 600; }
        .fcef-courses-table .required { color: #d63638; }
        .fcef-courses-table .fcef-col-handle { width: 30px; text-align: center; cursor: move; color: #8c8f94; }
        .fcef-courses-table .fcef-col-actions { width: 90px; text-align: right; }
        .fcef-courses-table input[type="text"] { width: 100%; }
        .fcef-courses-table .fcef-template-row { display: none; }
        .fcef-add-row-wrap { margin: 16px 0; }
        .fcef-add-row-wrap .button .dashicons { margin-right: 4px; }
        .fcef-course-row.fcef-row-removing { opacity: 0.4; }
        ';
    }

    private static function get_inline_js()
    {
        return <<<'JS'
        jQuery(function($) {
            var $body = $('#fcef-courses-body');
            var $template = $('#fcef-row-template');

            $('#fcef-add-course').on('click', function(e) {
                e.preventDefault();
                if (!$template.length) return;

                var html = $template.html();
                var $newRow = $('<tbody>').html(html).find('tr.fcef-course-row');
                $newRow.removeClass('fcef-template-row');
                $newRow.find('input').prop('disabled', false).val('');
                $body.append($newRow);
                $newRow.find('input').first().focus();
            });

            $body.on('click', '.fcef-remove-row', function(e) {
                e.preventDefault();
                var $row = $(this).closest('tr.fcef-course-row');
                var visibleRows = $body.find('tr.fcef-course-row:not(.fcef-template-row)').length;

                if (visibleRows <= 1) {
                    $row.find('input').val('');
                    return;
                }

                $row.addClass('fcef-row-removing');
                setTimeout(function() { $row.remove(); }, 150);
            });

            $('#fcef-settings-form').on('submit', function() {
                $body.find('tr.fcef-course-row:not(.fcef-template-row):not(.fcef-row-removing)').each(function(i) {
                    $(this).find('input').each(function() {
                        var name = $(this).attr('name') || '';
                        name = name.replace(/fcef_courses\[[^\]]*\]/, 'fcef_courses[' + i + ']');
                        $(this).attr('name', name);
                    });
                });
            });
        });
JS;
    }
}
