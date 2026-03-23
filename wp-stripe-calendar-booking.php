<?php
/**
 * Plugin Name: Stripe Calendar Booking Cards
 * Description: Admin defined booking schedules shown in a monthly calendar with Stripe checkout and booking notifications.
 * Version: 1.7.4
 * Author: Mik Neri
 * Author URI: https://mikneri.dev
 * License: GPL2+
 */

if (!defined('ABSPATH')) {
    exit;
}

class Stripe_Calendar_Booking_Cards
{
    const OPTION_KEY = 'scbc_settings';
    const NONCE_ACTION = 'scbc_checkout_nonce';
    const PROGRAM_SESSIONS = 6;
    const FRONTEND_PAGE_SIZE = 12;
    const DB_VERSION = '1.2.0';
    const DOC_URL = 'https://github.com/mneri1/WP-Stripe-Calendar-Booking/blob/main/HOW_TO_USE.md';

    public function __construct()
    {
        $this->maybe_upgrade_schema();
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_slot_metabox'));
        add_action('save_post_scbc_slot', array($this, 'save_slot_meta'));
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_export_request'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_shortcode('stripe_booking_calendar', array($this, 'render_shortcode'));
        add_shortcode('scbc_client_portal', array($this, 'render_client_portal_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('wp_ajax_scbc_create_checkout_session', array($this, 'ajax_create_checkout_session'));
        add_action('wp_ajax_nopriv_scbc_create_checkout_session', array($this, 'ajax_create_checkout_session'));
        add_action('wp_ajax_scbc_fetch_slots', array($this, 'ajax_fetch_slots'));
        add_action('wp_ajax_nopriv_scbc_fetch_slots', array($this, 'ajax_fetch_slots'));
        add_action('template_redirect', array($this, 'handle_checkout_return'));
        add_action('template_redirect', array($this, 'handle_ics_download'));
        add_action('rest_api_init', array($this, 'register_webhook_route'));
        add_action('init', array($this, 'ensure_reminder_cron'));
        add_action('scbc_hourly_reminder_event', array($this, 'process_scheduled_reminders'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
    }

    public function register_post_type()
    {
        register_post_type('scbc_slot', array(
            'labels' => array(
                'name' => 'Booking Slots',
                'singular_name' => 'Booking Slot',
                'add_new_item' => 'Add New Booking Slot',
                'edit_item' => 'Edit Booking Slot',
                'menu_name' => 'Booking Slots',
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-calendar-alt',
            'supports' => array('title'),
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ));
    }

    public function add_slot_metabox()
    {
        add_meta_box('scbc_slot_details', 'Slot Details', array($this, 'render_slot_metabox'), 'scbc_slot', 'normal', 'high');
    }

    public function render_slot_metabox($post)
    {
        wp_nonce_field('scbc_slot_meta_nonce', 'scbc_slot_meta_nonce');
        $start = get_post_meta($post->ID, '_scbc_start_datetime', true);
        $start_date = '';
        $start_time = '09:00';
        if (!empty($start) && strpos((string) $start, 'T') !== false) {
            $parts = explode('T', (string) $start, 2);
            $start_date = isset($parts[0]) ? $parts[0] : '';
            $start_time = isset($parts[1]) ? substr($parts[1], 0, 5) : '09:00';
        }
        $price = get_post_meta($post->ID, '_scbc_price', true);
        $timezone = $this->get_slot_timezone($post->ID);
        $capacity = $this->get_slot_capacity($post->ID);
        $duration_minutes = $this->get_slot_duration_minutes($post->ID);
        $booked_count = $this->get_slot_booked_count($post->ID);
        $spots_left = max(0, $capacity - $booked_count);

        echo '<p><label for="scbc_start_date"><strong>Start Date</strong></label><br>';
        echo '<input type="date" id="scbc_start_date" name="scbc_start_date" value="' . esc_attr($start_date) . '" style="width:100%;max-width:260px" required></p>';
        echo '<p><label for="scbc_start_time"><strong>Start Time</strong></label><br>';
        echo '<select id="scbc_start_time" name="scbc_start_time" size="8" style="width:100%;max-width:260px;overflow-y:auto;" required>';
        for ($h = 0; $h < 24; $h++) {
            foreach (array('00', '30') as $m) {
                $time_val = str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':' . $m;
                echo '<option value="' . esc_attr($time_val) . '"' . selected($start_time, $time_val, false) . '>' . esc_html($time_val) . '</option>';
            }
        }
        echo '</select><br><small>Scroll up or down to pick time slots.</small></p>';
        echo '<p><label for="scbc_price"><strong>Price</strong></label><br>';
        echo '<input type="number" step="0.01" min="0" id="scbc_price" name="scbc_price" value="' . esc_attr($price) . '" style="width:100%;max-width:220px" required></p>';
        $timezone_options = function_exists('wp_timezone_choice')
            ? wp_timezone_choice($timezone)
            : '<option value="' . esc_attr($timezone) . '">' . esc_html($timezone) . '</option>';
        echo '<p><label for="scbc_timezone"><strong>Timezone</strong></label><br>';
        echo '<select id="scbc_timezone" name="scbc_timezone" style="width:100%;max-width:420px;">' . $timezone_options . '</select></p>';
        echo '<p><label for="scbc_capacity"><strong>Capacity</strong></label><br>';
        echo '<input type="number" step="1" min="1" id="scbc_capacity" name="scbc_capacity" value="' . esc_attr((string) $capacity) . '" style="width:100%;max-width:220px" required></p>';
        echo '<p><label for="scbc_duration_minutes"><strong>Duration Minutes</strong></label><br>';
        echo '<input type="number" step="1" min="5" id="scbc_duration_minutes" name="scbc_duration_minutes" value="' . esc_attr((string) $duration_minutes) . '" style="width:100%;max-width:220px" required></p>';
        echo '<p><strong>Status:</strong> ' . ($spots_left > 0 ? 'Open' : 'Full') . '</p>';
        echo '<p><strong>Booked Count:</strong> ' . esc_html((string) $booked_count) . ' of ' . esc_html((string) $capacity) . '</p>';
    }

    public function save_slot_meta($post_id)
    {
        if (!isset($_POST['scbc_slot_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['scbc_slot_meta_nonce'])), 'scbc_slot_meta_nonce')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        if (!isset($_POST['scbc_start_date'], $_POST['scbc_start_time'], $_POST['scbc_price'])) {
            return;
        }

        $start_date = sanitize_text_field(wp_unslash($_POST['scbc_start_date']));
        $start_time = sanitize_text_field(wp_unslash($_POST['scbc_start_time']));
        $start_datetime = '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) && preg_match('/^\d{2}:\d{2}$/', $start_time)) {
            $start_datetime = $start_date . 'T' . $start_time;
        } else {
            return;
        }

        $timezone = isset($_POST['scbc_timezone']) ? $this->sanitize_timezone(wp_unslash($_POST['scbc_timezone'])) : wp_timezone_string();
        $capacity = isset($_POST['scbc_capacity']) ? max(1, absint(wp_unslash($_POST['scbc_capacity']))) : 1;
        $duration_minutes = isset($_POST['scbc_duration_minutes']) ? max(5, absint(wp_unslash($_POST['scbc_duration_minutes']))) : $this->get_default_duration_minutes();
        $booked_count = $this->get_slot_booked_count($post_id);

        update_post_meta($post_id, '_scbc_start_datetime', $start_datetime);
        update_post_meta($post_id, '_scbc_price', (float) wp_unslash($_POST['scbc_price']));
        update_post_meta($post_id, '_scbc_timezone', $timezone);
        update_post_meta($post_id, '_scbc_capacity', $capacity);
        update_post_meta($post_id, '_scbc_duration_minutes', $duration_minutes);
        update_post_meta($post_id, '_scbc_booked', $booked_count >= $capacity ? 1 : 0);
        $this->log_event('slot_saved', 'Booking slot details were saved.', array(
            'slot_id' => (int) $post_id,
            'start_datetime' => $start_datetime,
            'timezone' => $timezone,
            'price' => (float) wp_unslash($_POST['scbc_price']),
            'capacity' => $capacity,
            'duration_minutes' => $duration_minutes,
            'user_id' => get_current_user_id(),
            'user_ip' => $this->get_request_ip(),
        ));
    }

    public function add_settings_page()
    {
        add_options_page('Stripe Booking Settings', 'Stripe Booking', 'manage_options', 'scbc-settings', array($this, 'render_settings_page'));
        add_submenu_page('edit.php?post_type=scbc_slot', 'Settings', 'Settings', 'manage_options', 'scbc-settings', array($this, 'render_settings_page'));
        add_submenu_page('edit.php?post_type=scbc_slot', 'Calendar View', 'Calendar View', 'edit_posts', 'scbc-calendar-view', array($this, 'render_admin_calendar_page'));
        add_submenu_page('edit.php?post_type=scbc_slot', 'Booking Entries', 'Booking Entries', 'edit_posts', 'scbc-booking-entries', array($this, 'render_entries_page'));
        add_submenu_page('edit.php?post_type=scbc_slot', 'Export Bookings', 'Export Bookings', 'edit_posts', 'scbc-export-bookings', array($this, 'render_export_page'));
        add_submenu_page('edit.php?post_type=scbc_slot', 'Activity Logs', 'Activity Logs', 'manage_options', 'scbc-activity-logs', array($this, 'render_logs_page'));
    }

    public function plugin_action_links($links)
    {
        $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=scbc-settings')) . '">Settings</a>';
        $docs_link = '<a href="' . esc_url(self::DOC_URL) . '" target="_blank" rel="noopener noreferrer">Documentation</a>';
        array_unshift($links, $docs_link);
        array_unshift($links, $settings_link);
        return $links;
    }

    public function plugin_row_meta($links, $file)
    {
        if ($file !== plugin_basename(__FILE__)) {
            return $links;
        }
        $links[] = '<a href="' . esc_url(self::DOC_URL) . '" target="_blank" rel="noopener noreferrer">Documentation</a>';
        return $links;
    }

    public function register_settings()
    {
        register_setting(self::OPTION_KEY, self::OPTION_KEY, array($this, 'sanitize_settings'));
        add_settings_section('scbc_main', 'Stripe Configuration', '__return_false', 'scbc-settings');
        add_settings_field('publishable_key', 'Stripe Publishable Key', array($this, 'render_text_field'), 'scbc-settings', 'scbc_main', array('key' => 'publishable_key', 'placeholder' => 'pk_live_or_test'));
        add_settings_field('secret_key', 'Stripe Secret Key', array($this, 'render_text_field'), 'scbc-settings', 'scbc_main', array('key' => 'secret_key', 'placeholder' => 'sk_live_or_test'));
        add_settings_field('webhook_secret', 'Stripe Webhook Secret', array($this, 'render_text_field'), 'scbc-settings', 'scbc_main', array('key' => 'webhook_secret', 'placeholder' => 'whsec_xxx'));
        add_settings_field('currency', 'Currency', array($this, 'render_text_field'), 'scbc-settings', 'scbc_main', array('key' => 'currency', 'placeholder' => 'usd'));
        add_settings_field('admin_email', 'Admin Notification Email', array($this, 'render_text_field'), 'scbc-settings', 'scbc_main', array('key' => 'admin_email', 'placeholder' => get_option('admin_email')));
        add_settings_field('default_duration_minutes', 'Default Event Duration Minutes', array($this, 'render_text_field'), 'scbc-settings', 'scbc_main', array('key' => 'default_duration_minutes', 'placeholder' => '60'));
        add_settings_field('admin_desktop_columns', 'Admin Desktop Card Columns', array($this, 'render_select_field'), 'scbc-settings', 'scbc_main', array(
            'key' => 'admin_desktop_columns',
            'options' => array(
                '2' => '2 Columns',
                '4' => '4 Columns',
            ),
        ));
        add_settings_field('tier_standard_max', 'Price Tier Standard Max', array($this, 'render_text_field'), 'scbc-settings', 'scbc_main', array('key' => 'tier_standard_max', 'placeholder' => '300'));
        add_settings_field('tier_premium_max', 'Price Tier Premium Max', array($this, 'render_text_field'), 'scbc-settings', 'scbc_main', array('key' => 'tier_premium_max', 'placeholder' => '700'));
        add_settings_section('scbc_branding', 'Email Branding', '__return_false', 'scbc-settings');
        add_settings_field('brand_name', 'Brand Name', array($this, 'render_text_field'), 'scbc-settings', 'scbc_branding', array('key' => 'brand_name', 'placeholder' => get_bloginfo('name')));
        add_settings_field('brand_color', 'Brand Color', array($this, 'render_text_field'), 'scbc-settings', 'scbc_branding', array('key' => 'brand_color', 'placeholder' => '#0ea5e9'));
        add_settings_section('scbc_notifications', 'Reminder Templates', '__return_false', 'scbc-settings');
        add_settings_field('reminder_subject', 'Reminder Email Subject', array($this, 'render_text_field'), 'scbc-settings', 'scbc_notifications', array('key' => 'reminder_subject', 'placeholder' => 'Reminder 6 Week Mentorship session in 24 hours'));
        add_settings_field('reminder_body', 'Reminder Email Body', array($this, 'render_textarea_field'), 'scbc-settings', 'scbc_notifications', array('key' => 'reminder_body', 'placeholder' => 'Template with tokens', 'description' => 'Tokens: {session_title} {schedule} {timezone} {gmt_offset} {ics_url} {site_name}'));
        add_settings_section('scbc_frontend_copy', 'Frontend Modal Copy', '__return_false', 'scbc-settings');
        add_settings_field('session_expectations_copy', 'Session Expectations Copy', array($this, 'render_textarea_field'), 'scbc-settings', 'scbc_frontend_copy', array('key' => 'session_expectations_copy', 'placeholder' => 'Shown before payment confirmation'));
        add_settings_field('cancellation_policy_copy', 'Cancellation Policy Copy', array($this, 'render_textarea_field'), 'scbc-settings', 'scbc_frontend_copy', array('key' => 'cancellation_policy_copy', 'placeholder' => 'Shown before payment confirmation'));
    }

    public function sanitize_settings($input)
    {
        $output = array();
        $output['publishable_key'] = isset($input['publishable_key']) ? sanitize_text_field($input['publishable_key']) : '';
        $output['secret_key'] = isset($input['secret_key']) ? sanitize_text_field($input['secret_key']) : '';
        $output['webhook_secret'] = isset($input['webhook_secret']) ? sanitize_text_field($input['webhook_secret']) : '';
        $output['currency'] = isset($input['currency']) ? strtolower(sanitize_text_field($input['currency'])) : 'usd';
        $output['admin_email'] = isset($input['admin_email']) ? sanitize_email($input['admin_email']) : '';
        $output['default_duration_minutes'] = isset($input['default_duration_minutes']) ? max(5, absint($input['default_duration_minutes'])) : 60;
        $admin_cols = isset($input['admin_desktop_columns']) ? absint($input['admin_desktop_columns']) : 4;
        $output['admin_desktop_columns'] = in_array($admin_cols, array(2, 4), true) ? $admin_cols : 4;
        $standard_max = isset($input['tier_standard_max']) ? max(0, (float) $input['tier_standard_max']) : 300;
        $premium_max = isset($input['tier_premium_max']) ? max($standard_max, (float) $input['tier_premium_max']) : 700;
        $output['tier_standard_max'] = $standard_max;
        $output['tier_premium_max'] = $premium_max;
        $output['brand_name'] = isset($input['brand_name']) ? sanitize_text_field($input['brand_name']) : get_bloginfo('name');
        $output['brand_color'] = isset($input['brand_color']) ? sanitize_hex_color($input['brand_color']) : '#0ea5e9';
        $output['reminder_subject'] = isset($input['reminder_subject']) ? sanitize_text_field($input['reminder_subject']) : 'Reminder 6 Week Mentorship session in 24 hours';
        $output['reminder_body'] = isset($input['reminder_body']) ? sanitize_textarea_field($input['reminder_body']) : '';
        $output['session_expectations_copy'] = isset($input['session_expectations_copy']) ? sanitize_textarea_field($input['session_expectations_copy']) : "This reserves one mentorship session inside your 6 week program.\nPlease join five minutes early and be ready with your questions.";
        $output['cancellation_policy_copy'] = isset($input['cancellation_policy_copy']) ? sanitize_textarea_field($input['cancellation_policy_copy']) : 'Reschedule or cancel at least 24 hours before start time. Late cancel or no show may count as a used session.';
        if (empty($output['brand_color'])) {
            $output['brand_color'] = '#0ea5e9';
        }
        $this->log_event('settings_saved', 'Plugin settings were updated.', array(
            'user_id' => get_current_user_id(),
            'user_ip' => $this->get_request_ip(),
            'timezone' => wp_timezone_string(),
        ));
        return $output;
    }

    public function render_text_field($args)
    {
        $options = $this->get_settings();
        $key = $args['key'];
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';
        printf(
            '<input type="text" name="%1$s[%2$s]" value="%3$s" placeholder="%4$s" class="regular-text"/>',
            esc_attr(self::OPTION_KEY),
            esc_attr($key),
            esc_attr(isset($options[$key]) ? $options[$key] : ''),
            esc_attr($placeholder)
        );
    }

    public function render_textarea_field($args)
    {
        $options = $this->get_settings();
        $key = $args['key'];
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';
        $description = isset($args['description']) ? (string) $args['description'] : '';
        $description_html = $description !== '' ? '<p class="description">' . esc_html($description) . '</p>' : '';
        printf(
            '<textarea name="%1$s[%2$s]" rows="7" class="large-text" placeholder="%4$s">%3$s</textarea>%5$s',
            esc_attr(self::OPTION_KEY),
            esc_attr($key),
            esc_textarea(isset($options[$key]) ? $options[$key] : ''),
            esc_attr($placeholder),
            $description_html
        );
    }

    public function render_select_field($args)
    {
        $options = $this->get_settings();
        $key = $args['key'];
        $choices = isset($args['options']) && is_array($args['options']) ? $args['options'] : array();
        $current = isset($options[$key]) ? (string) $options[$key] : '';
        echo '<select name="' . esc_attr(self::OPTION_KEY . '[' . $key . ']') . '">';
        foreach ($choices as $value => $label) {
            echo '<option value="' . esc_attr((string) $value) . '"' . selected($current, (string) $value, false) . '>' . esc_html((string) $label) . '</option>';
        }
        echo '</select>';
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $options = $this->get_settings();
        $currency = strtoupper((string) $options['currency']);
        $stats = $this->get_program_dashboard_stats();
        $saved = isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true';
        $save_time = current_time('Y-m-d H:i:s');
        $site_timezone = wp_timezone_string();
        if (empty($site_timezone)) {
            $site_timezone = 'UTC';
        }
        $preview_slot = $this->get_next_preview_slot();
        echo '<div class="wrap"><h1>Stripe Booking Settings</h1>';
        if ($saved) {
            echo '<div id="scbc-settings-toast" style="position:fixed;right:22px;bottom:22px;background:#0f172a;color:#fff;padding:11px 14px 11px 12px;border-radius:10px;box-shadow:0 10px 24px rgba(15,23,42,0.24);z-index:9999;font-weight:600;display:flex;align-items:center;gap:10px;">';
            echo '<span>Settings saved at ' . esc_html($save_time . ' ' . $site_timezone) . '.</span>';
            echo '<button type="button" id="scbc-settings-toast-close" aria-label="Dismiss" style="border:1px solid rgba(255,255,255,.35);background:transparent;color:#fff;border-radius:6px;padding:3px 7px;line-height:1;cursor:pointer;">x</button>';
            echo '</div>';
            echo '<script>(function(){var t=document.getElementById("scbc-settings-toast");var b=document.getElementById("scbc-settings-toast-close");if(b&&t){b.addEventListener("click",function(){t.style.transition="opacity .24s ease";t.style.opacity="0";setTimeout(function(){if(t&&t.parentNode){t.parentNode.removeChild(t);}},260);});}setTimeout(function(){if(t){t.style.transition="opacity .24s ease";t.style.opacity="0";setTimeout(function(){if(t&&t.parentNode){t.parentNode.removeChild(t);}},260);}},3200);}());</script>';
        }
        echo '<div style="display:flex;gap:10px;flex-wrap:wrap;margin:10px 0 16px;">';
        echo '<div style="background:#f8fafc;border:1px solid #dbe3ee;padding:10px 14px;border-radius:8px;min-width:190px;"><strong>Active Clients</strong><br>' . esc_html((string) $stats['active_clients']) . '</div>';
        echo '<div style="background:#f8fafc;border:1px solid #dbe3ee;padding:10px 14px;border-radius:8px;min-width:190px;"><strong>Total Sessions Booked</strong><br>' . esc_html((string) $stats['total_sessions']) . '</div>';
        echo '<div style="background:#f8fafc;border:1px solid #dbe3ee;padding:10px 14px;border-radius:8px;min-width:190px;"><strong>Total Remaining Sessions</strong><br>' . esc_html((string) $stats['remaining_sessions']) . '</div>';
        echo '<div style="background:#f8fafc;border:1px solid #dbe3ee;padding:10px 14px;border-radius:8px;min-width:190px;"><strong>Completed Clients</strong><br>' . esc_html((string) $stats['completed_clients']) . '</div>';
        echo '</div>';
        echo '<p>Use shortcode <code>[stripe_booking_calendar]</code> on any page to show booking schedules.</p>';
        echo '<p>Client portal shortcode: <code>[scbc_client_portal]</code></p>';
        echo '<p><strong>Webhook URL:</strong> <code>' . esc_html(rest_url('scbc/v1/stripe-webhook')) . '</code></p>';
        echo '<form method="post" action="options.php">';
        settings_fields(self::OPTION_KEY);
        do_settings_sections('scbc-settings');
        submit_button();
        echo '</form>';
        echo '<div style="max-width:760px;margin-top:20px;background:#fff;border:1px solid #dbe3ee;border-radius:12px;padding:18px;">';
        echo '<h2 style="margin-top:0;">Modal Policy Preview</h2>';
        echo '<p style="margin-top:0;color:#475569;">Mobile modal width preview with frontend style spacing and action button.</p>';
        echo '<div style="max-width:388px;padding:12px;background:#eef2f7;border:1px solid #dbe3ee;border-radius:16px;">';
        echo '<div style="width:100%;max-width:360px;margin:0 auto;background:#fff;border:1px solid #dbe3ee;border-radius:16px;box-shadow:0 18px 48px rgba(15,23,42,.2);padding:16px;position:relative;">';
        echo '<button type="button" aria-label="Close" style="position:absolute;top:10px;right:10px;border:0;background:transparent;color:#334155;font-size:18px;width:28px;height:28px;border-radius:999px;line-height:1;">x</button>';
        echo '<h3 style="margin:0 0 10px;">Booking Details</h3>';
        echo '<div style="margin-bottom:10px;">';
        echo '<p style="margin:0 0 6px;"><strong>' . esc_html($preview_slot['title']) . '</strong></p>';
        echo '<p style="margin:0 0 6px;color:#1e293b;"><strong>Date:</strong> ' . esc_html($preview_slot['date']) . '</p>';
        echo '<p style="margin:0 0 6px;color:#1e293b;"><strong>Time:</strong> ' . esc_html($preview_slot['time']) . '</p>';
        echo '<p style="margin:0 0 6px;color:#1e293b;"><strong>Duration:</strong> ' . esc_html((string) $preview_slot['duration']) . '</p>';
        echo '<p style="margin:0;color:#1e293b;"><strong>Price:</strong> ' . esc_html($currency . ' ' . number_format_i18n((float) $preview_slot['price'], 2)) . '</p>';
        echo '</div>';
        echo '<div style="border-top:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;margin:10px 0 12px;padding:10px 0;">';
        echo '<p style="margin:0 0 8px;color:#334155;font-size:13px;line-height:1.4;"><strong>Session Expectations</strong></p>';
        echo '<p style="margin:0 0 10px;color:#334155;font-size:13px;line-height:1.4;">' . nl2br(esc_html((string) $options['session_expectations_copy'])) . '</p>';
        echo '<p style="margin:0 0 8px;color:#334155;font-size:13px;line-height:1.4;"><strong>Cancellation Policy</strong></p>';
        echo '<p style="margin:0;color:#334155;font-size:13px;line-height:1.4;">' . nl2br(esc_html((string) $options['cancellation_policy_copy'])) . '</p>';
        echo '</div>';
        echo '<button type="button" style="border:0;border-radius:8px;padding:12px 14px;min-height:48px;font-size:14px;font-weight:600;background:#0ea5e9;color:#fff;cursor:pointer;width:100%;">Continue to Payment</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    public function render_admin_calendar_page()
    {
        if (!current_user_can('edit_posts')) {
            return;
        }
        $month = $this->get_requested_month('scbc_admin_month');
        $month_ts = strtotime($month . '-01 00:00:00');
        $base_url = admin_url('edit.php?post_type=scbc_slot&page=scbc-calendar-view');
        $prev_url = add_query_arg('scbc_admin_month', gmdate('Y-m', strtotime('-1 month', $month_ts)), $base_url);
        $next_url = add_query_arg('scbc_admin_month', gmdate('Y-m', strtotime('+1 month', $month_ts)), $base_url);
        $density = $this->get_admin_calendar_density();
        $settings = $this->get_settings();
        $currency = strtoupper((string) $settings['currency']);
        $admin_cols = isset($settings['admin_desktop_columns']) && (int) $settings['admin_desktop_columns'] === 2 ? 2 : 4;
        $cols_class = $admin_cols === 2 ? 'scbc-admin-cols-2' : 'scbc-admin-cols-4';
        $standard_max = (float) $settings['tier_standard_max'];
        $premium_max = (float) $settings['tier_premium_max'];
        $slots = $this->get_slots_for_month($month, true);
        $slots_by_day = $this->group_slots_by_day($slots);

        $total_slots = count($slots);
        $full_slots = 0;
        $open_slots = 0;
        $total_spots = 0;
        $booked_spots = 0;
        foreach ($slots as $slot) {
            $total_spots += (int) $slot['capacity'];
            $booked_spots += (int) $slot['booked_count'];
            if (!empty($slot['booked'])) {
                $full_slots++;
            } else {
                $open_slots++;
            }
        }

        echo '<div class="wrap scbc-admin-calendar-wrap ' . esc_attr($cols_class) . '"><h1>Booking Calendar</h1>';
        echo '<p><a class="button" href="' . esc_url($prev_url) . '">Previous Month</a> ';
        echo '<a class="button" href="' . esc_url($next_url) . '">Next Month</a> ';
        echo '<a class="button" href="' . esc_url(admin_url('edit.php?post_type=scbc_slot&page=scbc-booking-entries')) . '">Booking Entries</a> ';
        echo '<a class="button" href="' . esc_url(admin_url('edit.php?post_type=scbc_slot&page=scbc-export-bookings')) . '">Export Bookings</a> ';
        echo '<a class="button' . ($density === 'compact' ? ' button-primary' : '') . '" href="' . esc_url(add_query_arg(array('scbc_density' => 'compact'), $base_url)) . '">Compact</a> ';
        echo '<a class="button' . ($density === 'detailed' ? ' button-primary' : '') . '" href="' . esc_url(add_query_arg(array('scbc_density' => 'detailed'), $base_url)) . '">Detailed</a></p>';
        echo '<h2>' . esc_html(wp_date('F Y', $month_ts)) . '</h2>';
        echo '<div class="scbc-admin-metrics">';
        echo '<div class="scbc-admin-metric"><strong>Total Slots</strong><span>' . esc_html((string) $total_slots) . '</span></div>';
        echo '<div class="scbc-admin-metric"><strong>Open Slots</strong><span>' . esc_html((string) $open_slots) . '</span></div>';
        echo '<div class="scbc-admin-metric"><strong>Full Slots</strong><span>' . esc_html((string) $full_slots) . '</span></div>';
        echo '<div class="scbc-admin-metric"><strong>Booked Spots</strong><span>' . esc_html((string) $booked_spots . '/' . (string) $total_spots) . '</span></div>';
        echo '</div>';
        echo '<div class="scbc-tier-legend">';
        echo '<span class="scbc-tier-chip scbc-tier-chip-standard">Standard: up to ' . esc_html($currency . ' ' . number_format_i18n($standard_max, 2)) . '</span>';
        echo '<span class="scbc-tier-chip scbc-tier-chip-premium">Premium: up to ' . esc_html($currency . ' ' . number_format_i18n($premium_max, 2)) . '</span>';
        echo '<span class="scbc-tier-chip scbc-tier-chip-elite">Elite: above ' . esc_html($currency . ' ' . number_format_i18n($premium_max, 2)) . '</span>';
        echo '</div>';
        $this->render_calendar_table($month, $slots_by_day, true, $density);
        echo '</div>';
    }

    public function render_entries_page()
    {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $search = isset($_GET['scbc_q']) ? sanitize_text_field(wp_unslash($_GET['scbc_q'])) : '';
        $date_from = isset($_GET['scbc_from']) ? sanitize_text_field(wp_unslash($_GET['scbc_from'])) : '';
        $date_to = isset($_GET['scbc_to']) ? sanitize_text_field(wp_unslash($_GET['scbc_to'])) : '';
        $preset = isset($_GET['scbc_preset']) ? sanitize_text_field(wp_unslash($_GET['scbc_preset'])) : '';
        $preset_dates = $this->get_preset_date_range($preset);
        if (!empty($preset_dates['from']) && empty($date_from)) {
            $date_from = $preset_dates['from'];
        }
        if (!empty($preset_dates['to']) && empty($date_to)) {
            $date_to = $preset_dates['to'];
        }
        $paged = isset($_GET['paged']) ? max(1, absint(wp_unslash($_GET['paged']))) : 1;
        $per_page = 25;
        $entries_page = $this->get_booking_entries_page($paged, $per_page, $search, $date_from, $date_to);
        $entries = $entries_page['rows'];
        echo '<div class="wrap">';
        echo '<h1>Booking Entries</h1>';
        echo '<p>Every paid Stripe session is listed here.</p>';
        $export_entries_url = wp_nonce_url(
            add_query_arg(
                array(
                    'post_type' => 'scbc_slot',
                    'page' => 'scbc-booking-entries',
                    'scbc_export' => 'entries',
                    'scbc_q' => $search,
                    'scbc_from' => $date_from,
                    'scbc_to' => $date_to,
                ),
                admin_url('edit.php')
            ),
            'scbc_export_entries'
        );
        echo '<p><a class="button button-secondary" href="' . esc_url($export_entries_url) . '">Export Entries CSV</a></p>';
        echo '<form method="get" style="margin:12px 0;">';
        echo '<input type="hidden" name="post_type" value="scbc_slot">';
        echo '<input type="hidden" name="page" value="scbc-booking-entries">';
        echo '<input type="search" name="scbc_q" value="' . esc_attr($search) . '" placeholder="Search email or session id" style="min-width:280px;"> ';
        echo '<label style="margin-left:8px;">From <input type="date" name="scbc_from" value="' . esc_attr($date_from) . '"></label> ';
        echo '<label>To <input type="date" name="scbc_to" value="' . esc_attr($date_to) . '"></label> ';
        echo '<button class="button">Search</button>';
        echo '</form>';
        echo '<p>';
        echo '<a class="button button-small" href="' . esc_url(add_query_arg(array('post_type' => 'scbc_slot', 'page' => 'scbc-booking-entries', 'scbc_preset' => 'today'), admin_url('edit.php'))) . '">Today</a> ';
        echo '<a class="button button-small" href="' . esc_url(add_query_arg(array('post_type' => 'scbc_slot', 'page' => 'scbc-booking-entries', 'scbc_preset' => 'this_week'), admin_url('edit.php'))) . '">This Week</a> ';
        echo '<a class="button button-small" href="' . esc_url(add_query_arg(array('post_type' => 'scbc_slot', 'page' => 'scbc-booking-entries', 'scbc_preset' => 'this_month'), admin_url('edit.php'))) . '">This Month</a> ';
        echo '<a class="button button-small" href="' . esc_url(add_query_arg(array('post_type' => 'scbc_slot', 'page' => 'scbc-booking-entries'), admin_url('edit.php'))) . '">Clear</a>';
        echo '</p>';

        if (empty($entries)) {
            echo '<p>No booking entries found yet.</p>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>Booked At</th><th>Slot</th><th>Schedule</th><th>Customer Email</th><th>Amount</th><th>Session</th><th>Source</th><th>iCal</th>';
        echo '</tr></thead><tbody>';

        foreach ($entries as $entry) {
            $slot_id = (int) $entry['slot_id'];
            $slot_title = get_the_title($slot_id);
            $timezone = $this->sanitize_timezone($entry['slot_timezone']);
            $entry_ts = $this->get_slot_timestamp($entry['slot_start'], $timezone);
            $schedule = $this->format_slot_datetime($entry['slot_start'], $timezone, get_option('date_format') . ' ' . get_option('time_format')) . ' ' . $timezone . ' ' . $this->get_gmt_offset_label($timezone, $entry_ts);
            $ics_url = add_query_arg(
                array(
                    'scbc_download_ics' => '1',
                    'slot_id' => $slot_id,
                    'session_id' => $entry['session_id'],
                ),
                home_url('/')
            );

            echo '<tr>';
            echo '<td>' . esc_html((string) $entry['booked_at']) . '</td>';
            echo '<td>' . esc_html($slot_title . ' (#' . $slot_id . ')') . '</td>';
            echo '<td>' . esc_html($schedule) . '</td>';
            echo '<td>' . esc_html((string) $entry['customer_email']) . '</td>';
            echo '<td>' . esc_html(strtoupper((string) $entry['currency']) . ' ' . number_format_i18n(((float) $entry['amount_total']) / 100, 2)) . '</td>';
            echo '<td><code>' . esc_html((string) $entry['session_id']) . '</code></td>';
            echo '<td>' . esc_html((string) $entry['booking_source']) . '</td>';
            echo '<td><a class="button button-small" href="' . esc_url($ics_url) . '" target="_blank" rel="noopener noreferrer">Download</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        if ($entries_page['total_pages'] > 1) {
            echo '<p style="margin-top:14px;">';
            for ($i = 1; $i <= $entries_page['total_pages']; $i++) {
                if ($i === $paged) {
                    echo '<strong style="margin-right:8px;">' . esc_html((string) $i) . '</strong>';
                    continue;
                }
                $page_url = add_query_arg(
                    array(
                        'post_type' => 'scbc_slot',
                        'page' => 'scbc-booking-entries',
                        'paged' => $i,
                        'scbc_q' => $search,
                        'scbc_from' => $date_from,
                        'scbc_to' => $date_to,
                        'scbc_preset' => $preset,
                    ),
                    admin_url('edit.php')
                );
                echo '<a style="margin-right:8px;" href="' . esc_url($page_url) . '">' . esc_html((string) $i) . '</a>';
            }
            echo '</p>';
        }

        echo '</div>';
    }

    public function render_export_page()
    {
        if (!current_user_can('edit_posts')) {
            return;
        }
        $url = wp_nonce_url(
            add_query_arg(
                array(
                    'post_type' => 'scbc_slot',
                    'page' => 'scbc-export-bookings',
                    'scbc_export' => 'bookings',
                ),
                admin_url('edit.php')
            ),
            'scbc_export_bookings'
        );
        $entries_url = wp_nonce_url(
            add_query_arg(
                array(
                    'post_type' => 'scbc_slot',
                    'page' => 'scbc-export-bookings',
                    'scbc_export' => 'entries',
                ),
                admin_url('edit.php')
            ),
            'scbc_export_entries'
        );

        echo '<div class="wrap">';
        echo '<h1>Export Bookings</h1>';
        echo '<p>Download aggregate slot bookings or paid session entries as CSV.</p>';
        echo '<p><a class="button button-primary" href="' . esc_url($url) . '">Download CSV</a></p>';
        echo '<p><a class="button button-secondary" href="' . esc_url($entries_url) . '">Download Entries CSV</a></p>';
        echo '</div>';
    }

    public function render_logs_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (
            isset($_POST['scbc_clear_logs_nonce'])
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['scbc_clear_logs_nonce'])), 'scbc_clear_logs')
            && isset($_POST['scbc_clear_logs'])
            && $_POST['scbc_clear_logs'] === '1'
        ) {
            global $wpdb;
            $table = $this->get_logs_table_name();
            $wpdb->query("TRUNCATE TABLE {$table}");
            $this->log_event('logs_cleared', 'Activity logs were cleared from admin page.', array('user_id' => get_current_user_id(), 'user_ip' => $this->get_request_ip()));
            echo '<div class="notice notice-success"><p>Activity logs cleared.</p></div>';
        }

        $page = isset($_GET['paged']) ? max(1, absint(wp_unslash($_GET['paged']))) : 1;
        $level = isset($_GET['scbc_level']) ? sanitize_text_field(wp_unslash($_GET['scbc_level'])) : '';
        $search = isset($_GET['scbc_q']) ? sanitize_text_field(wp_unslash($_GET['scbc_q'])) : '';
        $result = $this->get_logs_page($page, 100, $level, $search);
        $today_counts = $this->get_log_counts_today();

        echo '<div class="wrap">';
        echo '<h1>Activity Logs</h1>';
        echo '<p>System events, admin actions, Stripe flow events, reminders, and emails are recorded here.</p>';
        echo '<div style="display:flex;gap:10px;flex-wrap:wrap;margin:10px 0 14px;">';
        echo '<div style="background:#ecfeff;border:1px solid #a5f3fc;padding:10px 14px;border-radius:8px;min-width:150px;"><strong>Total Logs Today</strong><br>' . esc_html((string) $today_counts['total']) . '</div>';
        echo '<div style="background:#f8fafc;border:1px solid #dbe3ee;padding:10px 14px;border-radius:8px;min-width:150px;"><strong>Today Info</strong><br>' . esc_html((string) $today_counts['info']) . '</div>';
        echo '<div style="background:#fff7ed;border:1px solid #fed7aa;padding:10px 14px;border-radius:8px;min-width:150px;"><strong>Today Warning</strong><br>' . esc_html((string) $today_counts['warning']) . '</div>';
        echo '<div style="background:#fef2f2;border:1px solid #fecaca;padding:10px 14px;border-radius:8px;min-width:150px;"><strong>Today Error</strong><br>' . esc_html((string) $today_counts['error']) . '</div>';
        echo '</div>';
        echo '<form method="get" style="margin:12px 0;">';
        echo '<input type="hidden" name="post_type" value="scbc_slot">';
        echo '<input type="hidden" name="page" value="scbc-activity-logs">';
        echo '<label style="margin-right:8px;">Level ';
        echo '<select name="scbc_level">';
        echo '<option value="">All</option>';
        foreach (array('info' => 'Info', 'warning' => 'Warning', 'error' => 'Error') as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($level, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label> ';
        echo '<input type="search" name="scbc_q" value="' . esc_attr($search) . '" placeholder="Search event or message" style="min-width:260px;"> ';
        echo '<button class="button">Filter</button>';
        echo '</form>';

        echo '<form method="post" onsubmit="return confirm(\'Clear all activity logs?\');" style="margin:0 0 12px;">';
        wp_nonce_field('scbc_clear_logs', 'scbc_clear_logs_nonce');
        echo '<input type="hidden" name="scbc_clear_logs" value="1">';
        echo '<button type="submit" class="button button-secondary">Clear Logs</button>';
        echo '</form>';

        if (empty($result['rows'])) {
            echo '<p>No logs found.</p></div>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>When</th><th>Level</th><th>Event</th><th>Message</th><th>Context</th>';
        echo '</tr></thead><tbody>';
        foreach ($result['rows'] as $row) {
            $context = isset($row['context_json']) ? (string) $row['context_json'] : '';
            $context_display = $context !== '' ? $context : '{}';
            echo '<tr>';
            echo '<td>' . esc_html((string) $row['created_at']) . '</td>';
            echo '<td><code>' . esc_html((string) $row['level']) . '</code></td>';
            echo '<td>' . esc_html((string) $row['event_key']) . '</td>';
            echo '<td>' . esc_html((string) $row['message']) . '</td>';
            echo '<td><pre style="margin:0;white-space:pre-wrap;word-break:break-word;max-width:520px;">' . esc_html($context_display) . '</pre></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        if ($result['total_pages'] > 1) {
            echo '<p style="margin-top:14px;">';
            for ($i = 1; $i <= $result['total_pages']; $i++) {
                if ($i === $page) {
                    echo '<strong style="margin-right:8px;">' . esc_html((string) $i) . '</strong>';
                    continue;
                }
                $page_url = add_query_arg(
                    array(
                        'post_type' => 'scbc_slot',
                        'page' => 'scbc-activity-logs',
                        'paged' => $i,
                        'scbc_level' => $level,
                        'scbc_q' => $search,
                    ),
                    admin_url('edit.php')
                );
                echo '<a style="margin-right:8px;" href="' . esc_url($page_url) . '">' . esc_html((string) $i) . '</a>';
            }
            echo '</p>';
        }

        echo '</div>';
    }

    private function get_logs_page($page = 1, $per_page = 100, $level = '', $search = '')
    {
        global $wpdb;
        $table = $this->get_logs_table_name();
        $safe_page = max(1, (int) $page);
        $safe_limit = max(1, min(500, (int) $per_page));
        $offset = ($safe_page - 1) * $safe_limit;
        $level = sanitize_text_field((string) $level);
        $search = sanitize_text_field((string) $search);

        $where_parts = array();
        $params = array();
        if (in_array($level, array('info', 'warning', 'error'), true)) {
            $where_parts[] = "level = %s";
            $params[] = $level;
        }
        if ($search !== '') {
            $where_parts[] = "(event_key LIKE %s OR message LIKE %s OR context_json LIKE %s)";
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = '';
        if (!empty($where_parts)) {
            $where_sql = ' WHERE ' . implode(' AND ', $where_parts) . ' ';
        }

        $count_sql = "SELECT COUNT(*) FROM {$table}{$where_sql}";
        $total = (int) $wpdb->get_var(!empty($params) ? $wpdb->prepare($count_sql, $params) : $count_sql);

        $data_sql = "SELECT * FROM {$table}{$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
        $data_params = $params;
        $data_params[] = $safe_limit;
        $data_params[] = $offset;
        $rows = $wpdb->get_results($wpdb->prepare($data_sql, $data_params), ARRAY_A);

        return array(
            'rows' => is_array($rows) ? $rows : array(),
            'total' => $total,
            'total_pages' => max(1, (int) ceil($total / $safe_limit)),
        );
    }

    private function get_log_counts_today()
    {
        global $wpdb;
        $table = $this->get_logs_table_name();
        $today = current_time('Y-m-d');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT level, COUNT(*) AS total FROM {$table} WHERE DATE(created_at) = %s GROUP BY level",
                $today
            ),
            ARRAY_A
        );
        $counts = array('total' => 0, 'info' => 0, 'warning' => 0, 'error' => 0);
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $level = isset($row['level']) ? (string) $row['level'] : '';
                $counts['total'] += (int) $row['total'];
                if (isset($counts[$level])) {
                    $counts[$level] = (int) $row['total'];
                }
            }
        }
        return $counts;
    }

    public function handle_export_request()
    {
        if (!is_admin() || !current_user_can('edit_posts')) {
            return;
        }
        if (!isset($_GET['scbc_export'])) {
            return;
        }
        $export_type = sanitize_text_field(wp_unslash($_GET['scbc_export']));
        if ($export_type !== 'bookings' && $export_type !== 'entries') {
            return;
        }

        if ($export_type === 'entries') {
            check_admin_referer('scbc_export_entries');
            $search = isset($_GET['scbc_q']) ? sanitize_text_field(wp_unslash($_GET['scbc_q'])) : '';
            $date_from = isset($_GET['scbc_from']) ? sanitize_text_field(wp_unslash($_GET['scbc_from'])) : '';
            $date_to = isset($_GET['scbc_to']) ? sanitize_text_field(wp_unslash($_GET['scbc_to'])) : '';
            $preset = isset($_GET['scbc_preset']) ? sanitize_text_field(wp_unslash($_GET['scbc_preset'])) : '';
            $preset_dates = $this->get_preset_date_range($preset);
            if (!empty($preset_dates['from']) && empty($date_from)) {
                $date_from = $preset_dates['from'];
            }
            if (!empty($preset_dates['to']) && empty($date_to)) {
                $date_to = $preset_dates['to'];
            }

            $entries_result = $this->get_booking_entries_page(1, 1000, $search, $date_from, $date_to);
            $entries = $entries_result['rows'];
            nocache_headers();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=scbc-booking-entries-' . gmdate('Ymd-His') . '.csv');
            $out = fopen('php://output', 'w');
            fputcsv($out, array('Booked At', 'Slot ID', 'Slot Title', 'Schedule', 'Timezone', 'Duration Minutes', 'Customer Email', 'Amount', 'Currency', 'Session ID', 'Source'));
            foreach ($entries as $entry) {
                $slot_id = (int) $entry['slot_id'];
                $timezone = $this->sanitize_timezone($entry['slot_timezone']);
                fputcsv($out, array(
                    (string) $entry['booked_at'],
                    $slot_id,
                    get_the_title($slot_id),
                    $this->format_slot_datetime($entry['slot_start'], $timezone, get_option('date_format') . ' ' . get_option('time_format')),
                    $timezone,
                    $this->get_slot_duration_minutes($slot_id),
                    (string) $entry['customer_email'],
                    number_format(((float) $entry['amount_total']) / 100, 2, '.', ''),
                    strtoupper((string) $entry['currency']),
                    (string) $entry['session_id'],
                    (string) $entry['booking_source'],
                ));
            }
            fclose($out);
            $this->log_event('export_entries_csv', 'Booking entries CSV was exported.', array(
                'rows' => count($entries),
                'search' => $search,
                'date_from' => $date_from,
                'date_to' => $date_to,
                'user_id' => get_current_user_id(),
                'user_ip' => $this->get_request_ip(),
            ));
            exit;
        }

        check_admin_referer('scbc_export_bookings');

        $query = new WP_Query(array(
            'post_type' => 'scbc_slot',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_key' => '_scbc_start_datetime',
            'orderby' => 'meta_value',
            'order' => 'ASC',
        ));

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=scbc-slot-bookings-' . gmdate('Ymd-His') . '.csv');

        $out = fopen('php://output', 'w');
        fputcsv($out, array('Slot ID', 'Title', 'Schedule', 'Timezone', 'Capacity', 'Booked Count', 'Spots Left', 'Amount', 'Currency', 'Last Customer Email', 'Last Session ID', 'Paid At'));

        while ($query->have_posts()) {
            $query->the_post();
            $slot_id = get_the_ID();
            $booked_count = $this->get_slot_booked_count($slot_id);
            if ($booked_count < 1) {
                continue;
            }

            $start_raw = (string) get_post_meta($slot_id, '_scbc_start_datetime', true);
            $timezone = $this->get_slot_timezone($slot_id);
            $schedule = $this->format_slot_datetime($start_raw, $timezone, get_option('date_format') . ' ' . get_option('time_format'));
            $capacity = $this->get_slot_capacity($slot_id);
            $price = (float) get_post_meta($slot_id, '_scbc_price', true);
            $currency = strtoupper($this->get_settings()['currency']);

            fputcsv($out, array(
                $slot_id,
                get_the_title($slot_id),
                $schedule,
                $timezone,
                $capacity,
                $booked_count,
                max(0, $capacity - $booked_count),
                number_format((float) $price, 2, '.', ''),
                $currency,
                (string) get_post_meta($slot_id, '_scbc_customer_email', true),
                (string) get_post_meta($slot_id, '_scbc_booking_session', true),
                (string) get_post_meta($slot_id, '_scbc_paid_at', true),
            ));
        }
        wp_reset_postdata();

        fclose($out);
        $this->log_event('export_slots_csv', 'Aggregate slot bookings CSV was exported.', array(
            'user_id' => get_current_user_id(),
            'user_ip' => $this->get_request_ip(),
        ));
        exit;
    }

    public function register_assets()
    {
        wp_register_style('scbc-style', plugin_dir_url(__FILE__) . 'assets/css/scbc.css', array(), '1.7.4');
        wp_register_script('scbc-stripe-js', 'https://js.stripe.com/v3/', array(), null, true);
        wp_register_script('scbc-booking', plugin_dir_url(__FILE__) . 'assets/js/scbc.js', array('scbc-stripe-js'), '1.7.4', true);
    }

    public function enqueue_admin_assets($hook)
    {
        $allowed = array(
            'scbc_slot_page_scbc-calendar-view',
            'scbc_slot_page_scbc-booking-entries',
            'scbc_slot_page_scbc-export-bookings',
            'settings_page_scbc-settings',
            'post.php',
            'post-new.php',
        );
        if (!in_array($hook, $allowed, true)) {
            return;
        }
        wp_enqueue_style('scbc-admin-style', plugin_dir_url(__FILE__) . 'assets/css/scbc.css', array(), '1.7.4');
    }

    public function render_shortcode()
    {
        $options = $this->get_settings();
        if (empty($options['publishable_key']) || empty($options['secret_key'])) {
            return current_user_can('manage_options')
                ? '<div class="scbc-notice scbc-error">Please configure Stripe keys in Settings > Stripe Booking.</div>'
                : '<div class="scbc-notice scbc-error">Booking is currently unavailable.</div>';
        }

        wp_enqueue_style('scbc-style');
        wp_enqueue_script('scbc-booking');
        $requested_month = isset($_GET['scbc_month']) ? $this->sanitize_month_key(wp_unslash($_GET['scbc_month'])) : '';
        $available_months = $this->get_available_month_filters();
        if (empty($requested_month) && !empty($available_months[0]['value'])) {
            $requested_month = (string) $available_months[0]['value'];
        }
        $first_page = $this->get_public_slots_page(1, self::FRONTEND_PAGE_SIZE, $requested_month);
        $session_expectations_copy = isset($options['session_expectations_copy']) ? (string) $options['session_expectations_copy'] : '';
        $cancellation_policy_copy = isset($options['cancellation_policy_copy']) ? (string) $options['cancellation_policy_copy'] : '';
        wp_localize_script('scbc-booking', 'SCBC_DATA', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'publishableKey' => $options['publishable_key'],
            'programSessions' => self::PROGRAM_SESSIONS,
            'buttonLabel' => 'Book 6 Week Session',
            'pageSize' => self::FRONTEND_PAGE_SIZE,
            'requestedMonth' => $requested_month,
            'messages' => array(
                'error' => 'Could not start checkout. Please try again.',
                'loading' => 'Starting checkout...',
                'loadingSlots' => 'Loading schedules...',
                'loadMore' => 'Load More Schedules',
                'noMore' => 'No more schedules',
                'loadError' => 'Could not load schedules right now.',
                'modalButton' => 'Continue to Payment',
            ),
        ));

        ob_start();
        $notice = isset($_GET['scbc_booking']) ? sanitize_text_field(wp_unslash($_GET['scbc_booking'])) : '';
        $notice_slot = isset($_GET['scbc_slot']) ? absint(wp_unslash($_GET['scbc_slot'])) : 0;
        $notice_session = isset($_GET['scbc_session']) ? sanitize_text_field(wp_unslash($_GET['scbc_session'])) : '';
        $notice_email = isset($_GET['scbc_email']) ? sanitize_email(wp_unslash($_GET['scbc_email'])) : '';
        if ($notice === 'success') {
            echo '<div class="scbc-notice scbc-success">Payment confirmed. Your booking is reserved.';
            if ($notice_slot > 0 && !empty($notice_session)) {
                $tz = $this->get_slot_timezone($notice_slot);
                $start_raw_notice = (string) get_post_meta($notice_slot, '_scbc_start_datetime', true);
                $start_ts_notice = $this->get_slot_timestamp($start_raw_notice, $tz);
                if ($start_ts_notice > 0) {
                    echo ' Timezone: ' . esc_html($tz . ' ' . $this->get_gmt_offset_label($tz, $start_ts_notice)) . '.';
                    echo ' GMT offset means how many hours this schedule is ahead or behind UTC.';
                }
                $ics_url = add_query_arg(
                    array(
                        'scbc_download_ics' => '1',
                        'slot_id' => $notice_slot,
                        'session_id' => $notice_session,
                    ),
                    home_url('/')
                );
                echo ' <a href="' . esc_url($ics_url) . '" class="scbc-ics-link">Download iCal</a>';
            }
            if (!empty($notice_email)) {
                $used = $this->count_bookings_for_email($notice_email);
                $left = max(0, self::PROGRAM_SESSIONS - $used);
                $portal_url = add_query_arg('scbc_email', rawurlencode($notice_email), home_url('/'));
                echo ' Sessions used: ' . esc_html((string) $used . '/' . (string) self::PROGRAM_SESSIONS) . '.';
                echo ' Remaining: ' . esc_html((string) $left) . '.';
                echo ' <a href="' . esc_url($portal_url) . '" class="scbc-ics-link">Open Client Portal</a>';
            }
            echo '</div>';
        } elseif ($notice === 'pending') {
            echo '<div class="scbc-notice scbc-success">Payment submitted. Confirmation will appear shortly.</div>';
        } elseif ($notice === 'cancel') {
            echo '<div class="scbc-notice scbc-error">Payment was canceled. No booking was made.</div>';
        }

        echo '<div class="scbc-program-banner">';
        echo '<strong>6 Week Mentorship Program</strong> with ' . esc_html((string) self::PROGRAM_SESSIONS) . ' total sessions.';
        echo '</div>';
        echo '<div class="scbc-email-wrap">';
        echo '<label for="scbc-customer-email"><strong>Client Email</strong></label>';
        echo '<input type="email" id="scbc-customer-email" class="scbc-email-input" placeholder="Required: you@example.com" required>';
        echo '</div>';

        echo '<div class="scbc-list-toolbar">';
        echo '<label for="scbc-month-filter"><strong>Filter by Month</strong></label>';
        echo '<select id="scbc-month-filter" class="scbc-month-filter">';
        echo '<option value="">All Upcoming Months</option>';
        foreach ($available_months as $month_option) {
            echo '<option value="' . esc_attr($month_option['value']) . '"' . selected($requested_month, $month_option['value'], false) . '>' . esc_html($month_option['label']) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div id="scbc-slot-list" class="scbc-slot-list">';
        if (!empty($first_page['slots'])) {
            echo $this->render_public_slot_groups($first_page['slots'], strtoupper($options['currency']));
        } else {
            echo '<p class="scbc-empty-list">No schedules are available right now.</p>';
        }
        echo '</div>';

        $has_more = $first_page['page'] < $first_page['max_pages'];
        echo '<div class="scbc-list-actions">';
        echo '<button id="scbc-load-more" class="scbc-nav-btn" data-page="' . esc_attr((string) $first_page['page']) . '" data-max-pages="' . esc_attr((string) $first_page['max_pages']) . '"' . ($has_more ? '' : ' disabled') . '>' . esc_html($has_more ? 'Load More Schedules' : 'No more schedules') . '</button>';
        echo '</div>';
        echo '<div id="scbc-pagination" class="scbc-pagination-wrap">' . $this->render_pagination_controls($first_page['page'], $first_page['max_pages']) . '</div>';

        echo '<div id="scbc-slot-modal" class="scbc-modal" aria-hidden="true">';
        echo '<div class="scbc-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="scbc-modal-title">';
        echo '<button type="button" class="scbc-modal-close" id="scbc-modal-close" aria-label="Close">x</button>';
        echo '<h3 id="scbc-modal-title">Booking Details</h3>';
        echo '<div id="scbc-modal-details" class="scbc-modal-details"></div>';
        echo '<div class="scbc-modal-policy">';
        echo '<p><strong>Session Expectations</strong></p>';
        echo '<p>' . nl2br(esc_html($session_expectations_copy)) . '</p>';
        echo '<p><strong>Cancellation Policy</strong></p>';
        echo '<p>' . nl2br(esc_html($cancellation_policy_copy)) . '</p>';
        echo '</div>';
        echo '<button type="button" id="scbc-modal-book-btn" class="scbc-book-btn" data-slot-id="">Continue to Payment</button>';
        echo '</div>';
        echo '</div>';
        return ob_get_clean();
    }

    private function render_pagination_controls($page, $max_pages)
    {
        $current = max(1, absint($page));
        $max = max(1, absint($max_pages));
        if ($max <= 1) {
            return '';
        }

        $pages = array(1, $max);
        for ($i = max(1, $current - 2); $i <= min($max, $current + 2); $i++) {
            $pages[] = $i;
        }
        $pages = array_values(array_unique($pages));
        sort($pages, SORT_NUMERIC);

        $html = '<div class="scbc-pagination" role="navigation" aria-label="Schedule pagination">';
        $prev_page = max(1, $current - 1);
        $next_page = min($max, $current + 1);

        $html .= '<button type="button" class="scbc-page-nav" data-page="' . esc_attr((string) $prev_page) . '"' . ($current <= 1 ? ' disabled' : '') . '>Prev</button>';
        $last_printed = 0;
        foreach ($pages as $num) {
            if ($last_printed > 0 && $num > ($last_printed + 1)) {
                $html .= '<span class="scbc-page-gap" aria-hidden="true">...</span>';
            }
            $html .= '<button type="button" class="scbc-page-btn' . ($num === $current ? ' is-active' : '') . '" data-page="' . esc_attr((string) $num) . '"' . ($num === $current ? ' aria-current="page"' : '') . '>' . esc_html((string) $num) . '</button>';
            $last_printed = $num;
        }
        $html .= '<button type="button" class="scbc-page-nav" data-page="' . esc_attr((string) $next_page) . '"' . ($current >= $max ? ' disabled' : '') . '>Next</button>';
        $html .= '</div>';

        return $html;
    }

    private function sanitize_month_key($month)
    {
        $value = sanitize_text_field((string) $month);
        return preg_match('/^\d{4}-\d{2}$/', $value) ? $value : '';
    }

    private function get_next_preview_slot()
    {
        $slots = $this->collect_public_slots('');
        if (empty($slots)) {
            return array(
                'title' => 'Sample Session',
                'date' => 'No upcoming slots',
                'time' => 'Set an upcoming slot to preview real data',
                'duration' => '60 min',
                'price' => 0,
            );
        }
        $slot = $slots[0];
        $timestamp = isset($slot['timestamp']) ? (int) $slot['timestamp'] : 0;
        $timezone = isset($slot['timezone']) ? (string) $slot['timezone'] : 'UTC';
        return array(
            'title' => (string) $slot['title'],
            'date' => $this->format_slot_datetime((string) $slot['start_raw'], $timezone, 'D, M j'),
            'time' => $this->format_slot_datetime((string) $slot['start_raw'], $timezone, get_option('time_format')) . ' ' . $timezone . ' ' . $this->get_gmt_offset_label($timezone, $timestamp),
            'duration' => (string) $this->get_slot_duration_minutes((int) $slot['id']) . ' min',
            'price' => (float) $slot['price'],
        );
    }

    private function get_available_month_filters()
    {
        $all_slots = $this->collect_public_slots('');
        $months = array();
        foreach ($all_slots as $slot) {
            $month_key = wp_date('Y-m', (int) $slot['timestamp'], new DateTimeZone($slot['timezone']));
            if (!isset($months[$month_key])) {
                $month_ts = strtotime($month_key . '-01 00:00:00');
                $months[$month_key] = array(
                    'value' => $month_key,
                    'label' => wp_date('F Y', (int) $month_ts),
                );
            }
        }
        ksort($months);
        return array_values($months);
    }

    private function collect_public_slots($month)
    {
        $month_key = $this->sanitize_month_key($month);
        $query_args = array(
            'post_type' => 'scbc_slot',
            'posts_per_page' => 2000,
            'post_status' => 'publish',
            'meta_key' => '_scbc_start_datetime',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'fields' => 'ids',
        );
        if (!empty($month_key)) {
            $query_args['meta_query'] = array(
                'relation' => 'AND',
                array(
                    'key' => '_scbc_start_datetime',
                    'value' => $month_key . '-01T00:00',
                    'compare' => '>=',
                    'type' => 'CHAR',
                ),
                array(
                    'key' => '_scbc_start_datetime',
                    'value' => gmdate('Y-m-d\TH:i', strtotime('+1 month', strtotime($month_key . '-01 00:00:00'))),
                    'compare' => '<',
                    'type' => 'CHAR',
                ),
            );
        }

        $query = new WP_Query($query_args);
        $slots = array();
        $now_ts = current_time('timestamp', true);
        foreach ($query->posts as $slot_id) {
            $start_raw = (string) get_post_meta($slot_id, '_scbc_start_datetime', true);
            $timezone = $this->get_slot_timezone((int) $slot_id);
            $timestamp = $this->get_slot_timestamp($start_raw, $timezone);
            if ($timestamp < $now_ts) {
                continue;
            }
            $capacity = $this->get_slot_capacity((int) $slot_id);
            $booked_count = $this->get_slot_booked_count((int) $slot_id);
            $spots_left = max(0, $capacity - $booked_count);
            if ($spots_left < 1) {
                continue;
            }
            $slots[] = array(
                'id' => (int) $slot_id,
                'title' => get_the_title((int) $slot_id),
                'timestamp' => $timestamp,
                'start_raw' => $start_raw,
                'timezone' => $timezone,
                'price' => (float) get_post_meta((int) $slot_id, '_scbc_price', true),
                'capacity' => $capacity,
                'booked_count' => $booked_count,
                'spots_left' => $spots_left,
                'booked' => false,
            );
        }
        wp_reset_postdata();
        return $slots;
    }

    private function get_public_slots_page($page, $per_page, $month)
    {
        $all_slots = $this->collect_public_slots($month);
        $page_num = max(1, absint($page));
        $limit = max(1, absint($per_page));
        $total = count($all_slots);
        $max_pages = max(1, (int) ceil($total / $limit));
        if ($page_num > $max_pages) {
            $page_num = $max_pages;
        }
        $offset = ($page_num - 1) * $limit;
        return array(
            'slots' => array_slice($all_slots, $offset, $limit),
            'page' => $page_num,
            'max_pages' => $max_pages,
            'total' => $total,
        );
    }

    private function render_public_slot_groups($slots, $currency)
    {
        $grouped = array();
        foreach ($slots as $slot) {
            $month_key = wp_date('Y-m', (int) $slot['timestamp'], new DateTimeZone($slot['timezone']));
            if (!isset($grouped[$month_key])) {
                $grouped[$month_key] = array();
            }
            $grouped[$month_key][] = $slot;
        }
        ksort($grouped);

        $html = '';
        foreach ($grouped as $month_key => $month_slots) {
            $month_ts = strtotime($month_key . '-01 00:00:00');
            $html .= '<section class="scbc-list-view" data-month-key="' . esc_attr($month_key) . '">';
            $html .= '<h3 class="scbc-list-month">' . esc_html(wp_date('F Y', (int) $month_ts)) . '</h3>';
            $html .= '<div class="scbc-list-grid">';
            foreach ($month_slots as $slot) {
                $gmt = $this->get_gmt_offset_label($slot['timezone'], (int) $slot['timestamp']);
                $date_label = $this->format_slot_datetime($slot['start_raw'], $slot['timezone'], 'D, M j');
                $time_label = $this->format_slot_datetime($slot['start_raw'], $slot['timezone'], get_option('time_format')) . ' ' . $slot['timezone'] . ' ' . $gmt;
                $duration_label = (string) $this->get_slot_duration_minutes((int) $slot['id']) . ' min';
                $spots_label = (string) $slot['spots_left'] . ' of ' . (string) $slot['capacity'];
                $price_label = $currency . ' ' . number_format_i18n((float) $slot['price'], 2);

                $html .= '<article class="scbc-list-card">';
                $html .= '<div class="scbc-list-card-top">';
                $html .= '<h4>' . esc_html($slot['title']) . '</h4>';
                $html .= '<span class="scbc-list-date">' . esc_html($date_label) . '</span>';
                $html .= '</div>';
                $html .= '<div class="scbc-list-detail">' . esc_html($time_label) . '</div>';
                $html .= '<div class="scbc-list-detail">Duration: ' . esc_html($duration_label) . '</div>';
                $html .= '<div class="scbc-list-detail">Spots Left: ' . esc_html($spots_label) . '</div>';
                $html .= '<div class="scbc-list-price">' . esc_html($price_label) . '</div>';
                $html .= '<button class="scbc-book-btn scbc-open-modal" data-slot-id="' . esc_attr((string) $slot['id']) . '" data-slot-title="' . esc_attr($slot['title']) . '" data-slot-date="' . esc_attr($date_label) . '" data-slot-time="' . esc_attr($time_label) . '" data-slot-duration="' . esc_attr($duration_label) . '" data-slot-spots="' . esc_attr($spots_label) . '" data-slot-price="' . esc_attr($price_label) . '">Book 6 Week Session</button>';
                $html .= '</article>';
            }
            $html .= '</div>';
            $html .= '</section>';
        }
        return $html;
    }

    public function render_client_portal_shortcode()
    {
        $email = isset($_GET['scbc_email']) ? sanitize_email(wp_unslash($_GET['scbc_email'])) : '';
        ob_start();
        echo '<div class="scbc-portal">';
        echo '<h3>Client Portal 6 Week Mentorship</h3>';
        echo '<form method="get">';
        echo '<input type="email" name="scbc_email" value="' . esc_attr($email) . '" placeholder="Required: enter your booking email" class="scbc-email-input" required> ';
        echo '<button class="scbc-nav-btn" type="submit">Open</button>';
        echo '</form>';

        if (!empty($email)) {
            $entries = $this->get_booking_entries_by_email($email, 50);
            $used = count($entries);
            $left = max(0, self::PROGRAM_SESSIONS - $used);
            echo '<p><strong>Sessions Used:</strong> ' . esc_html((string) $used . '/' . (string) self::PROGRAM_SESSIONS) . ' | <strong>Remaining:</strong> ' . esc_html((string) $left) . '</p>';
            if (!empty($entries)) {
                echo '<ul class="scbc-portal-list">';
                foreach ($entries as $entry) {
                    $slot_id = (int) $entry['slot_id'];
                    $timezone = $this->sanitize_timezone($entry['slot_timezone']);
                    $schedule = $this->format_slot_datetime($entry['slot_start'], $timezone, get_option('date_format') . ' ' . get_option('time_format'));
                    $ics_url = add_query_arg(
                        array(
                            'scbc_download_ics' => '1',
                            'slot_id' => $slot_id,
                            'session_id' => $entry['session_id'],
                        ),
                        home_url('/')
                    );
                    echo '<li>' . esc_html(get_the_title($slot_id) . ' - ' . $schedule . ' ' . $timezone) . ' <a href="' . esc_url($ics_url) . '">iCal</a></li>';
                }
                echo '</ul>';
            }
        }
        echo '</div>';
        return ob_get_clean();
    }

    private function get_requested_month($param_name)
    {
        $month = isset($_GET[$param_name]) ? sanitize_text_field(wp_unslash($_GET[$param_name])) : gmdate('Y-m', current_time('timestamp'));
        return preg_match('/^\d{4}-\d{2}$/', $month) ? $month : gmdate('Y-m', current_time('timestamp'));
    }

    private function get_slots_for_month($month, $include_booked)
    {
        $start = $month . '-01 00:00';
        $end = gmdate('Y-m-d H:i', strtotime('+1 month', strtotime($start . ':00')));
        $meta_query = array(
            array('key' => '_scbc_start_datetime', 'value' => $start, 'compare' => '>=', 'type' => 'CHAR'),
            array('key' => '_scbc_start_datetime', 'value' => $end, 'compare' => '<', 'type' => 'CHAR'),
        );

        $query = new WP_Query(array(
            'post_type' => 'scbc_slot',
            'posts_per_page' => 500,
            'post_status' => 'publish',
            'meta_key' => '_scbc_start_datetime',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => $meta_query,
        ));

        $slots = array();
        while ($query->have_posts()) {
            $query->the_post();
            $slot_id = get_the_ID();
            $start_raw = (string) get_post_meta($slot_id, '_scbc_start_datetime', true);
            $timezone = $this->get_slot_timezone($slot_id);
            $timestamp = $this->get_slot_timestamp($start_raw, $timezone);
            $capacity = $this->get_slot_capacity($slot_id);
            $booked_count = $this->get_slot_booked_count($slot_id);
            $spots_left = max(0, $capacity - $booked_count);

            if (!$timestamp || (!$include_booked && ($timestamp < current_time('timestamp', true) || $spots_left < 1))) {
                continue;
            }

            $slots[] = array(
                'id' => $slot_id,
                'title' => get_the_title($slot_id),
                'timestamp' => $timestamp,
                'start_raw' => $start_raw,
                'timezone' => $timezone,
                'price' => (float) get_post_meta($slot_id, '_scbc_price', true),
                'capacity' => $capacity,
                'booked_count' => $booked_count,
                'spots_left' => $spots_left,
                'booked' => $spots_left < 1,
            );
        }
        wp_reset_postdata();
        return $slots;
    }

    private function group_slots_by_day($slots)
    {
        $grouped = array();
        foreach ($slots as $slot) {
            $day = isset($slot['start_raw']) ? substr($slot['start_raw'], 0, 10) : gmdate('Y-m-d', $slot['timestamp']);
            if (!isset($grouped[$day])) {
                $grouped[$day] = array();
            }
            $grouped[$day][] = $slot;
        }
        return $grouped;
    }

    private function render_calendar_table($month, $slots_by_day, $admin_view, $density = 'detailed')
    {
        $currency = strtoupper($this->get_settings()['currency']);
        $density_class = $density === 'compact' ? 'scbc-density-compact' : 'scbc-density-detailed';

        echo '<div class="scbc-mobile-calendar scbc-calendar-cards-only ' . esc_attr($density_class) . '">';
        $has_mobile_slots = !empty($slots_by_day);
        $day_keys = array_keys($slots_by_day);
        sort($day_keys, SORT_STRING);
        foreach ($day_keys as $date_key) {
            $day_slots = isset($slots_by_day[$date_key]) ? $slots_by_day[$date_key] : array();
            if (empty($day_slots)) {
                continue;
            }
            usort($day_slots, function ($a, $b) {
                $at = isset($a['timestamp']) ? (int) $a['timestamp'] : 0;
                $bt = isset($b['timestamp']) ? (int) $b['timestamp'] : 0;
                return $at <=> $bt;
            });
            $day_total = 0.0;
            foreach ($day_slots as $slot) {
                $day_total += isset($slot['price']) ? (float) $slot['price'] : 0.0;
            }
            $first_slot = $day_slots[0];
            $earliest_label = 'Earliest: ' . $this->format_slot_datetime(
                (string) $first_slot['start_raw'],
                (string) $first_slot['timezone'],
                get_option('time_format')
            );
            $date_ts = strtotime($date_key . ' 00:00:00');
            echo '<article class="scbc-day-card">';
            echo '<header class="scbc-day-card-head"><h4>' . esc_html(wp_date('D, M j', (int) $date_ts)) . '</h4><span>' . esc_html((string) count($day_slots)) . ' slot' . (count($day_slots) === 1 ? '' : 's') . '<br><small class="scbc-day-total">' . esc_html($currency . ' ' . number_format_i18n($day_total, 2) . ' total') . '</small><br><small class="scbc-day-earliest">' . esc_html($earliest_label) . '</small></span></header>';
            echo '<div class="scbc-day-card-slots">';
            foreach ($day_slots as $slot) {
                echo $this->render_slot_item_markup($slot, $admin_view, $currency);
            }
            echo '</div>';
            echo '</article>';
        }
        if (!$has_mobile_slots) {
            echo '<div class="scbc-day-empty">No schedules this month.</div>';
        }
        echo '</div>';
        return;
    }

    private function render_slot_item_markup($slot, $admin_view, $currency)
    {
        $gmt = $this->get_gmt_offset_label($slot['timezone'], (int) $slot['timestamp']);
        $duration = $this->get_slot_duration_minutes((int) $slot['id']);
        $tier = $this->get_price_tier((float) $slot['price']);
        $html = '<div class="scbc-slot-item scbc-price-tier-' . esc_attr($tier['key']) . '">';
        if ($admin_view) {
            $edit_url = get_edit_post_link((int) $slot['id'], '');
            $html .= '<div class="scbc-slot-title"><a href="' . esc_url((string) $edit_url) . '">' . esc_html($slot['title']) . '</a></div>';
        } else {
            $html .= '<div class="scbc-slot-title">' . esc_html($slot['title']) . '</div>';
        }
        $html .= '<div class="scbc-tier-badge">' . esc_html($tier['label']) . '</div>';
        $html .= '<div class="scbc-slot-time">' . esc_html($this->format_slot_datetime($slot['start_raw'], $slot['timezone'], get_option('time_format'))) . ' ' . esc_html($slot['timezone']) . ' ' . esc_html($gmt) . '</div>';
        $html .= '<div class="scbc-slot-meta">Duration: ' . esc_html((string) $duration) . ' min</div>';
        $html .= '<div class="scbc-slot-price">' . esc_html($currency . ' ' . number_format_i18n($slot['price'], 2)) . '</div>';
        $html .= '<div class="scbc-slot-spots">Spots Left: ' . esc_html((string) $slot['spots_left']) . ' of ' . esc_html((string) $slot['capacity']) . '</div>';
        if ($admin_view) {
            $last_email = (string) get_post_meta((int) $slot['id'], '_scbc_customer_email', true);
            $html .= '<div class="scbc-admin-status ' . ($slot['booked'] ? 'is-booked' : 'is-open') . '">' . ($slot['booked'] ? 'Full' : 'Open') . ' ' . esc_html((string) $slot['booked_count']) . '/' . esc_html((string) $slot['capacity']) . '</div>';
            if (!empty($last_email)) {
                $html .= '<div class="scbc-slot-meta">Last Client: ' . esc_html($last_email) . '</div>';
            }
        } else {
            if ($slot['spots_left'] > 0) {
                $html .= '<button class="scbc-book-btn" data-slot-id="' . esc_attr((string) $slot['id']) . '">Book 6 Week Session</button>';
            } else {
                $html .= '<div class="scbc-admin-status is-booked">Full</div>';
            }
        }
        $html .= '</div>';
        return $html;
    }

    public function ajax_create_checkout_session()
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        $slot_id = isset($_POST['slot_id']) ? absint(wp_unslash($_POST['slot_id'])) : 0;
        $customer_email = isset($_POST['customer_email']) ? sanitize_email(wp_unslash($_POST['customer_email'])) : '';
        $this->log_event('checkout_session_request', 'Checkout session creation requested.', array(
            'slot_id' => $slot_id,
            'customer_email' => $customer_email,
            'user_ip' => $this->get_request_ip(),
        ));
        if (!$slot_id || get_post_type($slot_id) !== 'scbc_slot') {
            $this->log_event('checkout_session_failed', 'Invalid booking slot on checkout creation.', array('slot_id' => $slot_id), 'warning');
            wp_send_json_error(array('message' => 'Invalid booking slot.'), 400);
        }
        if (empty($customer_email) || !is_email($customer_email)) {
            $this->log_event('checkout_session_failed', 'Invalid customer email on checkout creation.', array('slot_id' => $slot_id, 'customer_email' => $customer_email), 'warning');
            wp_send_json_error(array('message' => 'A valid client email is required.'), 400);
        }
        $booked_total = $this->count_bookings_for_email($customer_email);
        if ($booked_total >= self::PROGRAM_SESSIONS) {
            $this->log_event('checkout_session_blocked', 'Email reached max sessions.', array('slot_id' => $slot_id, 'customer_email' => $customer_email, 'booked_total' => $booked_total), 'warning');
            wp_send_json_error(array('message' => 'This email already completed all 6 sessions.'), 409);
        }
        $start_raw = (string) get_post_meta($slot_id, '_scbc_start_datetime', true);
        $timezone = $this->get_slot_timezone($slot_id);
        $price = (float) get_post_meta($slot_id, '_scbc_price', true);
        $timestamp = $this->get_slot_timestamp($start_raw, $timezone);
        $spots_left = $this->get_slot_capacity($slot_id) - $this->get_slot_booked_count($slot_id);
        if ($spots_left < 1) {
            $this->log_event('checkout_session_blocked', 'Slot is full for checkout creation.', array('slot_id' => $slot_id), 'warning');
            wp_send_json_error(array('message' => 'This slot is already full.'), 409);
        }
        if (!$timestamp || $timestamp < current_time('timestamp', true)) {
            $this->log_event('checkout_session_blocked', 'Slot is no longer available for checkout creation.', array('slot_id' => $slot_id), 'warning');
            wp_send_json_error(array('message' => 'This slot is no longer available.'), 409);
        }
        if ($price <= 0) {
            $this->log_event('checkout_session_failed', 'Invalid slot price for checkout creation.', array('slot_id' => $slot_id, 'price' => $price), 'error');
            wp_send_json_error(array('message' => 'Invalid slot price.'), 400);
        }

        $settings = $this->get_settings();
        if (empty($settings['secret_key']) || empty($settings['publishable_key'])) {
            $this->log_event('checkout_session_failed', 'Stripe keys are missing.', array('slot_id' => $slot_id), 'error');
            wp_send_json_error(array('message' => 'Stripe is not configured.'), 500);
        }

        $return_url = isset($_POST['return_url']) ? esc_url_raw(wp_unslash($_POST['return_url'])) : home_url('/');
        $return_host = wp_parse_url($return_url, PHP_URL_HOST);
        $site_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        if (!$return_url || !$return_host || !$site_host || strtolower($return_host) !== strtolower($site_host)) {
            $return_url = home_url('/');
        }

        $success_url = add_query_arg(array(
            'scbc_stripe_success' => '1',
            'slot_id' => $slot_id,
            'session_id' => '{CHECKOUT_SESSION_ID}',
        ), $return_url);
        $cancel_url = add_query_arg(array('scbc_booking' => 'cancel'), $return_url);

        $body = array(
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'mode' => 'payment',
            'payment_method_types[0]' => 'card',
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => $settings['currency'],
            'line_items[0][price_data][unit_amount]' => (int) round($price * 100),
            'line_items[0][price_data][product_data][name]' => get_the_title($slot_id),
            'customer_creation' => 'always',
            'customer_email' => $customer_email,
            'metadata[slot_id]' => (string) $slot_id,
            'metadata[customer_email]' => $customer_email,
            'metadata[program]' => '6_week',
        );

        $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $settings['secret_key'],
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => $body,
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            $this->log_event('checkout_session_failed', 'Stripe API request failed during checkout session creation.', array('slot_id' => $slot_id, 'error' => $response->get_error_message()), 'error');
            wp_send_json_error(array('message' => $response->get_error_message()), 500);
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code >= 300 || empty($data['id'])) {
            $this->log_event('checkout_session_failed', 'Stripe API returned error during checkout creation.', array('slot_id' => $slot_id, 'http_code' => $code, 'response' => $data), 'error');
            wp_send_json_error(array('message' => isset($data['error']['message']) ? $data['error']['message'] : 'Stripe request failed.'), 500);
        }
        $this->log_event('checkout_session_created', 'Stripe checkout session created.', array('slot_id' => $slot_id, 'session_id' => (string) $data['id'], 'customer_email' => $customer_email));
        wp_send_json_success(array('sessionId' => $data['id']));
    }

    public function ajax_fetch_slots()
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        $page = isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1;
        $month = isset($_POST['month']) ? $this->sanitize_month_key(wp_unslash($_POST['month'])) : '';
        $paged = $this->get_public_slots_page($page, self::FRONTEND_PAGE_SIZE, $month);
        $currency = strtoupper($this->get_settings()['currency']);
        $this->log_event('slots_fetched', 'Frontend slots page fetched.', array(
            'page' => $paged['page'],
            'month' => $month,
            'rows' => count($paged['slots']),
            'user_ip' => $this->get_request_ip(),
        ));
        wp_send_json_success(array(
            'html' => $this->render_public_slot_groups($paged['slots'], $currency),
            'page' => $paged['page'],
            'maxPages' => $paged['max_pages'],
            'hasMore' => $paged['page'] < $paged['max_pages'],
            'isEmpty' => empty($paged['slots']),
            'paginationHtml' => $this->render_pagination_controls($paged['page'], $paged['max_pages']),
        ));
    }

    public function handle_checkout_return()
    {
        if (is_admin()) {
            return;
        }
        $is_success = isset($_GET['scbc_stripe_success']) && $_GET['scbc_stripe_success'] === '1';
        if (!$is_success) {
            return;
        }

        $slot_id = isset($_GET['slot_id']) ? absint(wp_unslash($_GET['slot_id'])) : 0;
        $session_id = isset($_GET['session_id']) ? sanitize_text_field(wp_unslash($_GET['session_id'])) : '';
        if (!$slot_id || !$session_id) {
            $this->log_event('checkout_return_invalid', 'Checkout return missing slot or session.', array('slot_id' => $slot_id, 'session_id' => $session_id), 'warning');
            $this->redirect_with_notice('cancel');
        }
        if ($this->is_session_already_processed($session_id)) {
            $this->log_event('checkout_return_already_processed', 'Checkout return session already processed.', array('slot_id' => $slot_id, 'session_id' => $session_id));
            $this->redirect_with_notice('success', array('scbc_slot' => $slot_id, 'scbc_session' => $session_id));
        }

        $settings = $this->get_settings();
        if (empty($settings['secret_key'])) {
            $this->log_event('checkout_return_failed', 'Checkout return failed because Stripe secret key is missing.', array('slot_id' => $slot_id), 'error');
            $this->redirect_with_notice('cancel');
        }
        $session_data = $this->fetch_stripe_session($session_id, $settings['secret_key']);
        if (!$session_data) {
            $this->log_event('checkout_return_pending', 'Checkout return session could not be confirmed yet.', array('slot_id' => $slot_id, 'session_id' => $session_id), 'warning');
            $this->redirect_with_notice('pending');
        }

        $paid = isset($session_data['payment_status']) && $session_data['payment_status'] === 'paid';
        $slot_match = isset($session_data['metadata']['slot_id']) && (int) $session_data['metadata']['slot_id'] === $slot_id;
        if (!$paid || !$slot_match) {
            $this->log_event('checkout_return_pending', 'Checkout return not paid or slot mismatch.', array('slot_id' => $slot_id, 'session_id' => $session_id, 'paid' => $paid, 'slot_match' => $slot_match), 'warning');
            $this->redirect_with_notice('pending');
        }

        $this->finalize_booking($slot_id, $session_data, 'success_redirect');
        $success_email = isset($session_data['customer_details']['email']) ? sanitize_email($session_data['customer_details']['email']) : '';
        $extra = array('scbc_slot' => $slot_id, 'scbc_session' => $session_id);
        if (!empty($success_email)) {
            $extra['scbc_email'] = $success_email;
        }
        $this->log_event('checkout_return_success', 'Checkout return finalized booking.', array('slot_id' => $slot_id, 'session_id' => $session_id, 'customer_email' => $success_email));
        $this->redirect_with_notice('success', $extra);
    }

    public function register_webhook_route()
    {
        register_rest_route('scbc/v1', '/stripe-webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook_request'),
            'permission_callback' => '__return_true',
        ));
    }

    public function handle_webhook_request(WP_REST_Request $request)
    {
        $settings = $this->get_settings();
        if (empty($settings['webhook_secret'])) {
            $this->log_event('webhook_failed', 'Webhook secret is not configured.', array(), 'error');
            return new WP_REST_Response(array('error' => 'Webhook secret is not configured.'), 400);
        }

        $payload = $request->get_body();
        $signature = $request->get_header('stripe-signature');
        if (!$this->verify_stripe_signature($payload, $signature, $settings['webhook_secret'])) {
            $this->log_event('webhook_failed', 'Webhook signature verification failed.', array('user_ip' => $this->get_request_ip()), 'warning');
            return new WP_REST_Response(array('error' => 'Invalid signature.'), 400);
        }

        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['id'])) {
            $this->log_event('webhook_failed', 'Webhook payload is invalid.', array(), 'warning');
            return new WP_REST_Response(array('error' => 'Invalid event payload.'), 400);
        }
        if ($this->is_event_already_processed($event['id'])) {
            $this->log_event('webhook_skipped', 'Webhook event already processed.', array('event_id' => (string) $event['id']));
            return new WP_REST_Response(array('status' => 'already_processed'), 200);
        }

        $event_type = isset($event['type']) ? $event['type'] : '';
        $this->log_event('webhook_received', 'Webhook event received.', array('event_id' => (string) $event['id'], 'event_type' => (string) $event_type));
        if ($event_type === 'checkout.session.completed' || $event_type === 'checkout.session.async_payment_succeeded') {
            $session = isset($event['data']['object']) ? $event['data']['object'] : array();
            $slot_id = isset($session['metadata']['slot_id']) ? absint($session['metadata']['slot_id']) : 0;
            if ($slot_id > 0) {
                $this->finalize_booking($slot_id, $session, 'webhook');
            }
        }

        $this->mark_event_processed($event['id']);
        $this->log_event('webhook_processed', 'Webhook event processed.', array('event_id' => (string) $event['id'], 'event_type' => (string) $event_type));
        return new WP_REST_Response(array('status' => 'ok'), 200);
    }

    private function verify_stripe_signature($payload, $signature_header, $secret)
    {
        if (empty($payload) || empty($signature_header) || empty($secret)) {
            return false;
        }

        $timestamp = '';
        $signatures = array();
        foreach (explode(',', $signature_header) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) {
                continue;
            }
            if ($kv[0] === 't') {
                $timestamp = $kv[1];
            }
            if ($kv[0] === 'v1') {
                $signatures[] = $kv[1];
            }
        }
        if (empty($timestamp) || empty($signatures)) {
            return false;
        }
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }
        return false;
    }

    private function is_event_already_processed($event_id)
    {
        return (bool) get_transient('scbc_evt_' . md5($event_id));
    }

    private function mark_event_processed($event_id)
    {
        set_transient('scbc_evt_' . md5($event_id), 1, DAY_IN_SECONDS * 7);
    }

    private function fetch_stripe_session($session_id, $secret_key)
    {
        $response = wp_remote_get('https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($session_id), array(
            'headers' => array('Authorization' => 'Bearer ' . $secret_key),
            'timeout' => 30,
        ));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 300) {
            $this->log_event('stripe_session_fetch_failed', 'Stripe session lookup failed.', array(
                'session_id' => (string) $session_id,
                'error' => is_wp_error($response) ? $response->get_error_message() : '',
                'http_code' => is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response),
            ), 'warning');
            return false;
        }
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        return is_array($data) ? $data : false;
    }

    private function get_bookings_table_name()
    {
        global $wpdb;
        return $wpdb->prefix . 'scbc_bookings';
    }

    private function get_logs_table_name()
    {
        global $wpdb;
        return $wpdb->prefix . 'scbc_logs';
    }

    private function log_event($event_key, $message, $context = array(), $level = 'info')
    {
        global $wpdb;
        $table = $this->get_logs_table_name();
        $safe_level = in_array($level, array('info', 'warning', 'error'), true) ? $level : 'info';
        $safe_context = is_array($context) ? $context : array();
        $encoded_context = wp_json_encode($safe_context);
        if ($encoded_context === false) {
            $encoded_context = '{}';
        }
        $wpdb->insert(
            $table,
            array(
                'level' => $safe_level,
                'event_key' => sanitize_key((string) $event_key),
                'message' => sanitize_text_field((string) $message),
                'context_json' => sanitize_textarea_field((string) $encoded_context),
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }

    private function get_request_ip()
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', (string) wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
            if (!empty($parts[0])) {
                return sanitize_text_field(trim($parts[0]));
            }
        }
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field((string) wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    }

    public static function activate()
    {
        $instance = new self();
        $instance->create_or_update_schema();
    }

    public static function deactivate()
    {
        $timestamp = wp_next_scheduled('scbc_hourly_reminder_event');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'scbc_hourly_reminder_event');
        }
    }

    private function maybe_upgrade_schema()
    {
        $installed = get_option('scbc_db_version', '');
        if ($installed !== self::DB_VERSION) {
            $this->create_or_update_schema();
        }
    }

    private function create_or_update_schema()
    {
        global $wpdb;
        $table = $this->get_bookings_table_name();
        $logs_table = $this->get_logs_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            slot_id bigint(20) unsigned NOT NULL,
            session_id varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL DEFAULT '',
            amount_total bigint(20) unsigned NOT NULL DEFAULT 0,
            currency varchar(16) NOT NULL DEFAULT '',
            booking_source varchar(64) NOT NULL DEFAULT '',
            slot_start varchar(32) NOT NULL DEFAULT '',
            slot_timezone varchar(64) NOT NULL DEFAULT '',
            reminder_24h_sent tinyint(1) unsigned NOT NULL DEFAULT 0,
            reminder_24h_sent_at datetime NULL,
            booked_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY slot_id (slot_id),
            KEY booked_at (booked_at),
            KEY reminder_24h_sent (reminder_24h_sent)
        ) {$charset_collate};";
        dbDelta($sql);
        $logs_sql = "CREATE TABLE {$logs_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            level varchar(16) NOT NULL DEFAULT 'info',
            event_key varchar(120) NOT NULL DEFAULT '',
            message text NOT NULL,
            context_json longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY level (level),
            KEY event_key (event_key),
            KEY created_at (created_at)
        ) {$charset_collate};";
        dbDelta($logs_sql);
        update_option('scbc_db_version', self::DB_VERSION);
        $this->log_event('schema_updated', 'Database schema checked and updated.', array('db_version' => self::DB_VERSION));
    }

    private function insert_booking_entry($slot_id, $session_data, $source)
    {
        global $wpdb;
        $session_id = isset($session_data['id']) ? sanitize_text_field($session_data['id']) : '';
        if (empty($session_id)) {
            $this->log_event('booking_entry_skipped', 'Booking entry insert skipped because session id is empty.', array('slot_id' => (int) $slot_id), 'warning');
            return;
        }
        $table = $this->get_bookings_table_name();
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE session_id = %s LIMIT 1", $session_id));
        if (!empty($exists)) {
            $this->log_event('booking_entry_skipped', 'Booking entry insert skipped because session already exists.', array('slot_id' => (int) $slot_id, 'session_id' => $session_id));
            return;
        }

        $wpdb->insert(
            $table,
            array(
                'slot_id' => (int) $slot_id,
                'session_id' => $session_id,
                'customer_email' => isset($session_data['customer_details']['email']) ? sanitize_email($session_data['customer_details']['email']) : '',
                'amount_total' => isset($session_data['amount_total']) ? (int) $session_data['amount_total'] : 0,
                'currency' => isset($session_data['currency']) ? sanitize_text_field($session_data['currency']) : '',
                'booking_source' => sanitize_text_field($source),
                'slot_start' => (string) get_post_meta($slot_id, '_scbc_start_datetime', true),
                'slot_timezone' => $this->get_slot_timezone($slot_id),
                'reminder_24h_sent' => 0,
                'booked_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s')
        );
        $this->log_event('booking_entry_inserted', 'Booking entry inserted.', array('slot_id' => (int) $slot_id, 'session_id' => $session_id, 'source' => sanitize_text_field($source)));
    }

    private function get_booking_entries($limit = 200)
    {
        $result = $this->get_booking_entries_page(1, $limit, '', '', '');
        return $result['rows'];
    }

    private function get_booking_entries_page($page = 1, $per_page = 25, $search = '', $date_from = '', $date_to = '')
    {
        global $wpdb;
        $table = $this->get_bookings_table_name();
        $safe_page = max(1, (int) $page);
        $safe_limit = max(1, min(5000, (int) $per_page));
        $offset = ($safe_page - 1) * $safe_limit;
        $search = sanitize_text_field((string) $search);
        $date_from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date_from) ? (string) $date_from : '';
        $date_to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date_to) ? (string) $date_to : '';

        $where_parts = array();
        $params = array();
        if (!empty($search)) {
            $where_parts[] = "(customer_email LIKE %s OR session_id LIKE %s)";
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($date_from)) {
            $where_parts[] = "DATE(booked_at) >= %s";
            $params[] = $date_from;
        }
        if (!empty($date_to)) {
            $where_parts[] = "DATE(booked_at) <= %s";
            $params[] = $date_to;
        }

        $where_sql = '';
        if (!empty($where_parts)) {
            $where_sql = ' WHERE ' . implode(' AND ', $where_parts) . ' ';
        }

        $count_sql = "SELECT COUNT(*) FROM {$table}{$where_sql}";
        $total = (int) $wpdb->get_var($params ? $wpdb->prepare($count_sql, $params) : $count_sql);

        $data_sql = "SELECT * FROM {$table}{$where_sql} ORDER BY booked_at DESC LIMIT %d OFFSET %d";
        $data_params = $params;
        $data_params[] = $safe_limit;
        $data_params[] = $offset;
        $rows = $wpdb->get_results($wpdb->prepare($data_sql, $data_params), ARRAY_A);

        return array(
            'rows' => is_array($rows) ? $rows : array(),
            'total' => $total,
            'total_pages' => max(1, (int) ceil($total / $safe_limit)),
        );
    }

    private function get_booking_entry_by_session($session_id)
    {
        global $wpdb;
        $table = $this->get_bookings_table_name();
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE session_id = %s LIMIT 1", sanitize_text_field((string) $session_id));
        $row = $wpdb->get_row($sql, ARRAY_A);
        return is_array($row) ? $row : null;
    }

    private function get_booking_entries_by_email($email, $limit = 100)
    {
        global $wpdb;
        $email = sanitize_email((string) $email);
        if (empty($email)) {
            return array();
        }
        $table = $this->get_bookings_table_name();
        $safe_limit = max(1, min(500, (int) $limit));
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE customer_email = %s ORDER BY booked_at ASC LIMIT %d", $email, $safe_limit);
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : array();
    }

    private function count_bookings_for_email($email)
    {
        return count($this->get_booking_entries_by_email($email, self::PROGRAM_SESSIONS + 1));
    }

    public function ensure_reminder_cron()
    {
        if (!wp_next_scheduled('scbc_hourly_reminder_event')) {
            wp_schedule_event(time() + 300, 'hourly', 'scbc_hourly_reminder_event');
            $this->log_event('reminder_cron_scheduled', 'Reminder cron event was scheduled.', array());
        }
    }

    public function process_scheduled_reminders()
    {
        $this->maybe_purge_old_logs();
        global $wpdb;
        $table = $this->get_bookings_table_name();
        $rows = $wpdb->get_results("SELECT * FROM {$table} WHERE reminder_24h_sent = 0 ORDER BY booked_at DESC LIMIT 500", ARRAY_A);
        if (!is_array($rows) || empty($rows)) {
            return;
        }

        $now = time();
        foreach ($rows as $row) {
            $slot_id = (int) $row['slot_id'];
            $email = sanitize_email((string) $row['customer_email']);
            if ($slot_id < 1 || empty($email)) {
                continue;
            }

            $start_ts = $this->get_slot_timestamp((string) $row['slot_start'], (string) $row['slot_timezone']);
            if ($start_ts < 1) {
                continue;
            }
            $diff = $start_ts - $now;
            if ($diff < (23 * HOUR_IN_SECONDS) || $diff > (25 * HOUR_IN_SECONDS)) {
                continue;
            }

            $timezone = $this->sanitize_timezone((string) $row['slot_timezone']);
            $schedule = $this->format_slot_datetime((string) $row['slot_start'], $timezone, get_option('date_format') . ' ' . get_option('time_format'));
            $ics_url = add_query_arg(
                array(
                    'scbc_download_ics' => '1',
                    'slot_id' => $slot_id,
                    'session_id' => (string) $row['session_id'],
                ),
                home_url('/')
            );
            $subject = $this->render_reminder_template('subject', array(
                'session_title' => get_the_title($slot_id),
                'schedule' => $schedule,
                'timezone' => $timezone,
                'gmt_offset' => $this->get_gmt_offset_label($timezone, $start_ts),
                'ics_url' => $ics_url,
                'site_name' => get_bloginfo('name'),
            ));
            $message = $this->render_reminder_template('body', array(
                'session_title' => get_the_title($slot_id),
                'schedule' => $schedule,
                'timezone' => $timezone,
                'gmt_offset' => $this->get_gmt_offset_label($timezone, $start_ts),
                'ics_url' => $ics_url,
                'site_name' => get_bloginfo('name'),
            ));
            wp_mail($email, $subject, $message);
            $this->log_event('reminder_sent', '24 hour reminder email sent.', array(
                'slot_id' => $slot_id,
                'email' => $email,
                'session_id' => (string) $row['session_id'],
            ));

            $wpdb->update(
                $table,
                array(
                    'reminder_24h_sent' => 1,
                    'reminder_24h_sent_at' => current_time('mysql'),
                ),
                array('id' => (int) $row['id']),
                array('%d', '%s'),
                array('%d')
            );
        }
    }

    private function maybe_purge_old_logs()
    {
        $today = current_time('Y-m-d');
        $last = (string) get_option('scbc_logs_last_purge_date', '');
        if ($last === $today) {
            return;
        }
        $this->purge_old_logs(90);
        update_option('scbc_logs_last_purge_date', $today, false);
    }

    private function purge_old_logs($days)
    {
        global $wpdb;
        $table = $this->get_logs_table_name();
        $retention_days = max(1, absint($days));
        $cutoff = gmdate('Y-m-d H:i:s', strtotime('-' . $retention_days . ' days'));
        $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE created_at < %s", $cutoff));
        $this->log_event('logs_purged', 'Old logs were purged by retention policy.', array(
            'retention_days' => $retention_days,
            'cutoff_utc' => $cutoff,
            'deleted_rows' => is_numeric($deleted) ? (int) $deleted : 0,
        ));
    }

    private function get_program_dashboard_stats()
    {
        $rows = $this->get_booking_entries(5000);
        if (empty($rows)) {
            return array(
                'active_clients' => 0,
                'completed_clients' => 0,
                'total_sessions' => 0,
                'remaining_sessions' => 0,
            );
        }
        $by_email = array();
        foreach ($rows as $row) {
            $email = sanitize_email((string) $row['customer_email']);
            if (empty($email)) {
                continue;
            }
            if (!isset($by_email[$email])) {
                $by_email[$email] = 0;
            }
            $by_email[$email]++;
        }
        $active = 0;
        $completed = 0;
        $remaining = 0;
        $total = 0;
        foreach ($by_email as $count) {
            $count = min(self::PROGRAM_SESSIONS, (int) $count);
            $total += $count;
            if ($count >= self::PROGRAM_SESSIONS) {
                $completed++;
            } else {
                $active++;
            }
            $remaining += max(0, self::PROGRAM_SESSIONS - $count);
        }
        return array(
            'active_clients' => $active,
            'completed_clients' => $completed,
            'total_sessions' => $total,
            'remaining_sessions' => $remaining,
        );
    }

    private function render_reminder_template($type, $tokens)
    {
        $settings = $this->get_settings();
        $defaults = array(
            'subject' => 'Reminder 6 Week Mentorship session in 24 hours',
            'body' => "Your mentorship session is coming up in about 24 hours.\n\nSession: {session_title}\nSchedule: {schedule} {timezone} ({gmt_offset})\nAdd to calendar: {ics_url}\n\n{site_name}",
        );
        $source = $type === 'subject' ? 'subject' : 'body';
        $setting_key = $source === 'subject' ? 'reminder_subject' : 'reminder_body';
        $template = isset($settings[$setting_key]) ? trim((string) $settings[$setting_key]) : '';
        if ($template === '') {
            $template = $defaults[$source];
        }

        $replace = array();
        foreach ($tokens as $k => $v) {
            $replace['{' . $k . '}'] = (string) $v;
        }
        return strtr($template, $replace);
    }

    private function get_preset_date_range($preset)
    {
        $preset = sanitize_text_field((string) $preset);
        if (empty($preset)) {
            return array('from' => '', 'to' => '');
        }

        $now = current_datetime();
        if ($preset === 'today') {
            $d = $now->format('Y-m-d');
            return array('from' => $d, 'to' => $d);
        }

        if ($preset === 'this_week') {
            $day_index = (int) $now->format('w');
            $start = clone $now;
            $end = clone $now;
            $start->modify('-' . $day_index . ' days');
            $end->modify('+' . (6 - $day_index) . ' days');
            return array('from' => $start->format('Y-m-d'), 'to' => $end->format('Y-m-d'));
        }

        if ($preset === 'this_month') {
            $start = clone $now;
            $end = clone $now;
            $start->modify('first day of this month');
            $end->modify('last day of this month');
            return array('from' => $start->format('Y-m-d'), 'to' => $end->format('Y-m-d'));
        }

        return array('from' => '', 'to' => '');
    }

    private function get_admin_calendar_density()
    {
        $user_id = get_current_user_id();
        $density = '';
        if (isset($_GET['scbc_density'])) {
            $requested = sanitize_text_field(wp_unslash($_GET['scbc_density']));
            if ($requested === 'compact' || $requested === 'detailed') {
                $density = $requested;
                if ($user_id > 0) {
                    update_user_meta($user_id, 'scbc_calendar_density', $density);
                }
            }
        }
        if ($density === '' && $user_id > 0) {
            $stored = get_user_meta($user_id, 'scbc_calendar_density', true);
            if ($stored === 'compact' || $stored === 'detailed') {
                $density = $stored;
            }
        }
        if ($density === '') {
            $density = 'detailed';
        }
        return $density;
    }

    private function get_price_tier($price)
    {
        $amount = (float) $price;
        $settings = $this->get_settings();
        $standard_max = max(0, (float) $settings['tier_standard_max']);
        $premium_max = max($standard_max, (float) $settings['tier_premium_max']);
        if ($amount <= $standard_max) {
            return array('key' => 'standard', 'label' => 'Standard');
        }
        if ($amount <= $premium_max) {
            return array('key' => 'premium', 'label' => 'Premium');
        }
        return array('key' => 'elite', 'label' => 'Elite');
    }

    private function sanitize_timezone($timezone)
    {
        $timezone = sanitize_text_field((string) $timezone);
        if (in_array($timezone, timezone_identifiers_list(), true)) {
            return $timezone;
        }
        $site_tz = wp_timezone_string();
        return !empty($site_tz) ? $site_tz : 'UTC';
    }

    private function get_slot_timezone($slot_id)
    {
        $stored = get_post_meta($slot_id, '_scbc_timezone', true);
        return $this->sanitize_timezone($stored);
    }

    private function get_slot_capacity($slot_id)
    {
        $capacity = (int) get_post_meta($slot_id, '_scbc_capacity', true);
        return max(1, $capacity);
    }

    private function get_default_duration_minutes()
    {
        $settings = $this->get_settings();
        $minutes = isset($settings['default_duration_minutes']) ? (int) $settings['default_duration_minutes'] : 60;
        return max(5, $minutes);
    }

    private function get_slot_duration_minutes($slot_id)
    {
        $minutes = (int) get_post_meta($slot_id, '_scbc_duration_minutes', true);
        if ($minutes < 5) {
            $minutes = $this->get_default_duration_minutes();
        }
        return max(5, $minutes);
    }

    private function get_slot_booked_count($slot_id)
    {
        $count = (int) get_post_meta($slot_id, '_scbc_booked_count', true);
        $capacity = $this->get_slot_capacity($slot_id);
        $legacy_booked = (bool) get_post_meta($slot_id, '_scbc_booked', true);
        if ($count < 1 && $legacy_booked) {
            $count = $capacity;
        }
        return max(0, min($count, $capacity));
    }

    private function get_slot_timestamp($start_raw, $timezone)
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', (string) $start_raw, new DateTimeZone($this->sanitize_timezone($timezone)));
        if ($dt instanceof DateTimeImmutable) {
            return $dt->getTimestamp();
        }
        $fallback = strtotime((string) $start_raw);
        return $fallback ? (int) $fallback : 0;
    }

    private function format_slot_datetime($start_raw, $timezone, $format)
    {
        $timestamp = $this->get_slot_timestamp($start_raw, $timezone);
        if ($timestamp < 1) {
            return (string) $start_raw;
        }
        return wp_date($format, $timestamp, new DateTimeZone($this->sanitize_timezone($timezone)));
    }

    private function get_gmt_offset_label($timezone, $timestamp)
    {
        $tz = new DateTimeZone($this->sanitize_timezone($timezone));
        $offset = $tz->getOffset((new DateTimeImmutable('@' . (int) $timestamp)));
        $hours = (int) floor(abs($offset) / 3600);
        $minutes = (int) floor((abs($offset) % 3600) / 60);
        $sign = $offset >= 0 ? '+' : '-';
        return 'GMT' . $sign . str_pad((string) $hours, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT);
    }

    private function is_session_already_processed($session_id)
    {
        return (bool) get_transient('scbc_paid_session_' . md5((string) $session_id));
    }

    private function mark_session_processed($session_id)
    {
        set_transient('scbc_paid_session_' . md5((string) $session_id), 1, DAY_IN_SECONDS * 30);
    }

    private function finalize_booking($slot_id, $session_data, $source)
    {
        $session_id = isset($session_data['id']) ? sanitize_text_field($session_data['id']) : '';
        if (!empty($session_id) && $this->is_session_already_processed($session_id)) {
            $this->log_event('booking_finalize_skipped', 'Finalize booking skipped because session was already processed.', array('slot_id' => (int) $slot_id, 'session_id' => $session_id));
            return;
        }

        $customer_email = '';
        if (isset($session_data['customer_details']['email'])) {
            $customer_email = sanitize_email($session_data['customer_details']['email']);
        } elseif (isset($session_data['metadata']['customer_email'])) {
            $customer_email = sanitize_email($session_data['metadata']['customer_email']);
        }

        if (!empty($customer_email) && $this->count_bookings_for_email($customer_email) >= self::PROGRAM_SESSIONS) {
            if (!empty($session_id)) {
                $this->mark_session_processed($session_id);
            }
            $this->log_event('booking_finalize_blocked', 'Finalize booking blocked because program sessions are completed.', array('slot_id' => (int) $slot_id, 'session_id' => $session_id, 'customer_email' => $customer_email), 'warning');
            return;
        }

        $capacity = $this->get_slot_capacity($slot_id);
        $booked_count = $this->get_slot_booked_count($slot_id);
        if ($booked_count < $capacity) {
            $booked_count++;
            update_post_meta($slot_id, '_scbc_booked_count', $booked_count);
            update_post_meta($slot_id, '_scbc_booked', $booked_count >= $capacity ? 1 : 0);
            if (!empty($session_id)) {
                update_post_meta($slot_id, '_scbc_booking_session', $session_id);
            }
            update_post_meta($slot_id, '_scbc_booking_source', sanitize_text_field($source));
            update_post_meta($slot_id, '_scbc_paid_at', current_time('mysql'));
        }

        if (!empty($customer_email)) {
            update_post_meta($slot_id, '_scbc_customer_email', $customer_email);
        }

        if (!empty($session_id)) {
            $this->mark_session_processed($session_id);
        }

        $this->insert_booking_entry($slot_id, $session_data, $source);
        $this->send_admin_notification($slot_id, $session_data);
        $this->send_customer_notification($slot_id, $session_data);
        $this->log_event('booking_finalized', 'Booking finalized and notifications dispatched.', array(
            'slot_id' => (int) $slot_id,
            'session_id' => $session_id,
            'source' => sanitize_text_field($source),
            'customer_email' => $customer_email,
            'booked_count' => $this->get_slot_booked_count($slot_id),
            'capacity' => $this->get_slot_capacity($slot_id),
        ));
    }

    private function send_admin_notification($slot_id, $session_data)
    {
        $settings = $this->get_settings();
        $to = !empty($settings['admin_email']) ? $settings['admin_email'] : get_option('admin_email');
        if (empty($to)) {
            $this->log_event('admin_email_skipped', 'Admin notification skipped because admin email is missing.', array('slot_id' => (int) $slot_id), 'warning');
            return;
        }
        $sent = wp_mail(
            $to,
            'New booking paid: ' . get_the_title($slot_id),
            $this->build_branded_email_html($slot_id, $session_data, 'admin'),
            array('Content-Type: text/html; charset=UTF-8')
        );
        $this->log_event($sent ? 'admin_email_sent' : 'admin_email_failed', $sent ? 'Admin notification sent.' : 'Admin notification failed to send.', array('slot_id' => (int) $slot_id, 'to' => $to), $sent ? 'info' : 'error');
    }

    private function send_customer_notification($slot_id, $session_data)
    {
        $to = isset($session_data['customer_details']['email']) ? sanitize_email($session_data['customer_details']['email']) : '';
        if (empty($to)) {
            $this->log_event('customer_email_skipped', 'Customer notification skipped because customer email is missing.', array('slot_id' => (int) $slot_id), 'warning');
            return;
        }
        $sent = wp_mail(
            $to,
            'Booking confirmed: ' . get_the_title($slot_id),
            $this->build_branded_email_html($slot_id, $session_data, 'customer'),
            array('Content-Type: text/html; charset=UTF-8')
        );
        $this->log_event($sent ? 'customer_email_sent' : 'customer_email_failed', $sent ? 'Customer notification sent.' : 'Customer notification failed to send.', array('slot_id' => (int) $slot_id, 'to' => $to), $sent ? 'info' : 'error');
    }

    private function build_branded_email_html($slot_id, $session_data, $audience)
    {
        $settings = $this->get_settings();
        $brand_name = !empty($settings['brand_name']) ? $settings['brand_name'] : get_bloginfo('name');
        $brand_color = !empty($settings['brand_color']) ? $settings['brand_color'] : '#0ea5e9';
        $title = get_the_title($slot_id);
        $start_raw = (string) get_post_meta($slot_id, '_scbc_start_datetime', true);
        $timezone = $this->get_slot_timezone($slot_id);
        $date = $this->format_slot_datetime($start_raw, $timezone, get_option('date_format') . ' ' . get_option('time_format'));
        $amount = isset($session_data['amount_total']) ? number_format_i18n(((float) $session_data['amount_total']) / 100, 2) : '';
        $currency = isset($session_data['currency']) ? strtoupper(sanitize_text_field($session_data['currency'])) : strtoupper($settings['currency']);
        $customer_email = isset($session_data['customer_details']['email']) ? sanitize_email($session_data['customer_details']['email']) : 'Unknown';
        $capacity = $this->get_slot_capacity($slot_id);
        $booked_count = $this->get_slot_booked_count($slot_id);
        $spots_left = max(0, $capacity - $booked_count);
        $headline = $audience === 'admin' ? 'New Booking Paid' : 'Booking Confirmed';
        $intro = $audience === 'admin' ? 'A new booking was paid on your site.' : 'Thank you. Your booking payment is confirmed.';
        $extra = $audience === 'admin'
            ? '<p style="margin:0 0 8px;"><strong>Customer Email:</strong> ' . esc_html($customer_email) . '</p><p style="margin:0 0 8px;"><strong>Slot ID:</strong> ' . esc_html((string) $slot_id) . '</p><p style="margin:0;"><strong>Booked:</strong> ' . esc_html((string) $booked_count) . '/' . esc_html((string) $capacity) . '</p>'
            : '';

        return '<div style="font-family:Arial,sans-serif;background:#f5f7fb;padding:24px;"><div style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #dbe2ea;"><div style="background:' . esc_attr($brand_color) . ';padding:18px 22px;color:#ffffff;font-size:20px;font-weight:700;">' . esc_html($brand_name) . '</div><div style="padding:22px;color:#1f2937;"><h2 style="margin:0 0 10px;">' . esc_html($headline) . '</h2><p style="margin:0 0 16px;">' . esc_html($intro) . '</p><p style="margin:0 0 8px;"><strong>Booking:</strong> ' . esc_html($title) . '</p><p style="margin:0 0 8px;"><strong>Schedule:</strong> ' . esc_html($date . ' ' . $timezone) . '</p><p style="margin:0 0 8px;"><strong>Amount:</strong> ' . esc_html($currency . ' ' . $amount) . '</p><p style="margin:0 0 8px;"><strong>Spots Left:</strong> ' . esc_html((string) $spots_left) . '</p>' . $extra . '</div></div></div>';
    }

    public function handle_ics_download()
    {
        if (!isset($_GET['scbc_download_ics']) || $_GET['scbc_download_ics'] !== '1') {
            return;
        }

        $slot_id = isset($_GET['slot_id']) ? absint(wp_unslash($_GET['slot_id'])) : 0;
        $session_id = isset($_GET['session_id']) ? sanitize_text_field(wp_unslash($_GET['session_id'])) : '';
        if ($slot_id < 1 || empty($session_id)) {
            $this->log_event('ics_download_failed', 'iCal download failed because request is invalid.', array('slot_id' => $slot_id, 'session_id' => $session_id), 'warning');
            wp_die('Invalid iCal request.');
        }

        $entry = $this->get_booking_entry_by_session($session_id);
        if (!is_array($entry) || (int) $entry['slot_id'] !== $slot_id) {
            $this->log_event('ics_download_failed', 'iCal download failed because booking entry was not found.', array('slot_id' => $slot_id, 'session_id' => $session_id), 'warning');
            wp_die('Booking entry not found.');
        }

        $ics = $this->build_ics_content($slot_id, $session_id);
        $filename = 'booking-' . $slot_id . '-' . preg_replace('/[^A-Za-z0-9]/', '', $session_id) . '.ics';

        nocache_headers();
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        $this->log_event('ics_downloaded', 'iCal file downloaded.', array('slot_id' => $slot_id, 'session_id' => $session_id, 'user_ip' => $this->get_request_ip()));
        echo $ics;
        exit;
    }

    private function build_ics_content($slot_id, $session_id)
    {
        $start_raw = (string) get_post_meta($slot_id, '_scbc_start_datetime', true);
        $timezone = $this->get_slot_timezone($slot_id);
        $start_ts = $this->get_slot_timestamp($start_raw, $timezone);
        $duration_minutes = $this->get_slot_duration_minutes($slot_id);
        $end_ts = $start_ts + ($duration_minutes * MINUTE_IN_SECONDS);
        $gmt_label = $this->get_gmt_offset_label($timezone, $start_ts);
        $tz = new DateTimeZone($timezone);
        $start_local = (new DateTimeImmutable('@' . $start_ts))->setTimezone($tz);
        $end_local = (new DateTimeImmutable('@' . $end_ts))->setTimezone($tz);

        $title = wp_strip_all_tags((string) get_the_title($slot_id));
        $description = 'Booking confirmed for slot #' . $slot_id . ' for ' . $duration_minutes . ' minutes. Timezone ' . $timezone . ' ' . $gmt_label . '.';
        $site_name = wp_strip_all_tags((string) get_bloginfo('name'));
        $uid = sanitize_key($session_id) . '@' . parse_url(home_url('/'), PHP_URL_HOST);
        $dtstamp = gmdate('Ymd\\THis\\Z');
        $dtstart_local = $start_local->format('Ymd\\THis');
        $dtend_local = $end_local->format('Ymd\\THis');
        $vtimezone = $this->build_vtimezone_block($timezone, $start_ts);

        return "BEGIN:VCALENDAR\r\n"
            . "VERSION:2.0\r\n"
            . "PRODID:-//{$site_name}//SCBC//EN\r\n"
            . "CALSCALE:GREGORIAN\r\n"
            . "METHOD:PUBLISH\r\n"
            . $vtimezone
            . "BEGIN:VEVENT\r\n"
            . "UID:{$uid}\r\n"
            . "DTSTAMP:{$dtstamp}\r\n"
            . 'DTSTART;TZID=' . $this->escape_ics_text($timezone) . ':' . $dtstart_local . "\r\n"
            . 'DTEND;TZID=' . $this->escape_ics_text($timezone) . ':' . $dtend_local . "\r\n"
            . 'SUMMARY:' . $this->escape_ics_text($title) . "\r\n"
            . 'DESCRIPTION:' . $this->escape_ics_text($description) . "\r\n"
            . 'LOCATION:' . $this->escape_ics_text(home_url('/')) . "\r\n"
            . "STATUS:CONFIRMED\r\n"
            . "END:VEVENT\r\n"
            . "END:VCALENDAR\r\n";
    }

    private function build_vtimezone_block($timezone, $reference_ts)
    {
        $tzid = $this->sanitize_timezone($timezone);
        $tz = new DateTimeZone($tzid);
        $year = (int) (new DateTimeImmutable('@' . $reference_ts))->setTimezone($tz)->format('Y');
        $from_ts = (new DateTimeImmutable(($year - 1) . '-01-01 00:00:00', new DateTimeZone('UTC')))->getTimestamp();
        $to_ts = (new DateTimeImmutable(($year + 1) . '-12-31 23:59:59', new DateTimeZone('UTC')))->getTimestamp();
        $transitions = $tz->getTransitions($from_ts, $to_ts);

        $block = "BEGIN:VTIMEZONE\r\n";
        $block .= 'TZID:' . $this->escape_ics_text($tzid) . "\r\n";
        $block .= 'X-LIC-LOCATION:' . $this->escape_ics_text($tzid) . "\r\n";

        if (!is_array($transitions) || count($transitions) < 2) {
            $offset = $tz->getOffset((new DateTimeImmutable('@' . $reference_ts)));
            $offset_text = $this->format_ics_offset($offset);
            $block .= "BEGIN:STANDARD\r\n";
            $block .= "DTSTART:19700101T000000\r\n";
            $block .= 'TZOFFSETFROM:' . $offset_text . "\r\n";
            $block .= 'TZOFFSETTO:' . $offset_text . "\r\n";
            $block .= "TZNAME:LOCAL\r\n";
            $block .= "END:STANDARD\r\n";
            $block .= "END:VTIMEZONE\r\n";
            return $block;
        }

        $has_block = false;
        $prev = $transitions[0];
        for ($i = 1; $i < count($transitions); $i++) {
            $cur = $transitions[$i];
            if (!isset($cur['ts'], $cur['offset'], $cur['isdst'])) {
                $prev = $cur;
                continue;
            }
            $transition_year = (int) (new DateTimeImmutable('@' . (int) $cur['ts']))->setTimezone($tz)->format('Y');
            if ($transition_year !== $year) {
                $prev = $cur;
                continue;
            }
            $has_block = true;
            $block .= $cur['isdst'] ? "BEGIN:DAYLIGHT\r\n" : "BEGIN:STANDARD\r\n";
            $block .= 'DTSTART:' . (new DateTimeImmutable('@' . (int) $cur['ts']))->setTimezone($tz)->format('Ymd\\THis') . "\r\n";
            $block .= 'TZOFFSETFROM:' . $this->format_ics_offset(isset($prev['offset']) ? (int) $prev['offset'] : (int) $cur['offset']) . "\r\n";
            $block .= 'TZOFFSETTO:' . $this->format_ics_offset((int) $cur['offset']) . "\r\n";
            $block .= 'TZNAME:' . $this->escape_ics_text(isset($cur['abbr']) ? (string) $cur['abbr'] : 'LOCAL') . "\r\n";
            $block .= $cur['isdst'] ? "END:DAYLIGHT\r\n" : "END:STANDARD\r\n";
            $prev = $cur;
        }

        if (!$has_block) {
            $offset = $tz->getOffset((new DateTimeImmutable('@' . $reference_ts)));
            $offset_text = $this->format_ics_offset($offset);
            $block .= "BEGIN:STANDARD\r\n";
            $block .= "DTSTART:19700101T000000\r\n";
            $block .= 'TZOFFSETFROM:' . $offset_text . "\r\n";
            $block .= 'TZOFFSETTO:' . $offset_text . "\r\n";
            $block .= "TZNAME:LOCAL\r\n";
            $block .= "END:STANDARD\r\n";
        }

        $block .= "END:VTIMEZONE\r\n";
        return $block;
    }

    private function format_ics_offset($seconds)
    {
        $seconds = (int) $seconds;
        $sign = $seconds >= 0 ? '+' : '-';
        $abs = abs($seconds);
        $hours = (int) floor($abs / 3600);
        $minutes = (int) floor(($abs % 3600) / 60);
        return $sign . str_pad((string) $hours, 2, '0', STR_PAD_LEFT) . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT);
    }

    private function escape_ics_text($value)
    {
        $value = str_replace("\\", "\\\\", (string) $value);
        $value = str_replace(';', '\;', $value);
        $value = str_replace(',', '\,', $value);
        return str_replace(array("\r", "\n"), array('', '\n'), $value);
    }

    private function redirect_with_notice($notice, $extra_args = array())
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        $current_url = $request_uri ? home_url($request_uri) : home_url('/');
        $clean_url = remove_query_arg(array('scbc_stripe_success', 'slot_id', 'session_id'), $current_url);
        $args = array_merge(array('scbc_booking' => $notice), is_array($extra_args) ? $extra_args : array());
        $target = add_query_arg($args, $clean_url);
        wp_safe_redirect($target);
        exit;
    }

    private function get_settings()
    {
        $settings = get_option(self::OPTION_KEY, array());
        return wp_parse_args($settings, array(
            'publishable_key' => '',
            'secret_key' => '',
            'webhook_secret' => '',
            'currency' => 'usd',
            'admin_email' => '',
            'default_duration_minutes' => 60,
            'admin_desktop_columns' => 4,
            'tier_standard_max' => 300,
            'tier_premium_max' => 700,
            'reminder_subject' => 'Reminder 6 Week Mentorship session in 24 hours',
            'reminder_body' => "Your mentorship session is coming up in about 24 hours.\n\nSession: {session_title}\nSchedule: {schedule} {timezone} ({gmt_offset})\nAdd to calendar: {ics_url}\n\n{site_name}",
            'session_expectations_copy' => "This reserves one mentorship session inside your 6 week program.\nPlease join five minutes early and be ready with your questions.",
            'cancellation_policy_copy' => 'Reschedule or cancel at least 24 hours before start time. Late cancel or no show may count as a used session.',
            'brand_name' => get_bloginfo('name'),
            'brand_color' => '#0ea5e9',
        ));
    }
}

register_activation_hook(__FILE__, array('Stripe_Calendar_Booking_Cards', 'activate'));
register_deactivation_hook(__FILE__, array('Stripe_Calendar_Booking_Cards', 'deactivate'));
new Stripe_Calendar_Booking_Cards();
