<?php
if (!defined('ABSPATH')) {
    exit;
}

trait SCBC_Reconciliation_Trait
{
    public function ensure_reconciliation_cron()
    {
        if (!wp_next_scheduled('scbc_reconcile_event')) {
            wp_schedule_event(time() + 600, 'hourly', 'scbc_reconcile_event');
            if (method_exists($this, 'log_event')) {
                $this->log_event('reconcile_cron_scheduled', 'Reconciliation cron event was scheduled.', array());
            }
        }
    }

    public function process_scheduled_reconciliation()
    {
        if (!method_exists($this, 'get_settings') || !method_exists($this, 'fetch_stripe_session') || !method_exists($this, 'finalize_booking') || !method_exists($this, 'is_session_already_processed')) {
            return;
        }

        $settings = $this->get_settings();
        if (empty($settings['secret_key'])) {
            if (method_exists($this, 'log_event')) {
                $this->log_event('reconcile_skipped', 'Reconciliation skipped because Stripe secret key is missing.', array(), 'warning');
            }
            return;
        }

        $candidates = $this->get_recent_checkout_candidates(40, 72);
        $checked = 0;
        $finalized = 0;
        $pending = 0;

        foreach ($candidates as $candidate) {
            $session_id = isset($candidate['session_id']) ? sanitize_text_field((string) $candidate['session_id']) : '';
            $slot_id = isset($candidate['slot_id']) ? absint($candidate['slot_id']) : 0;
            if ($session_id === '' || $slot_id < 1) {
                continue;
            }
            if ($this->is_session_already_processed($session_id)) {
                continue;
            }

            $checked++;
            $session_data = $this->fetch_stripe_session($session_id, $settings['secret_key']);
            if (!is_array($session_data)) {
                $pending++;
                continue;
            }

            $paid = isset($session_data['payment_status']) && $session_data['payment_status'] === 'paid';
            $slot_match = isset($session_data['metadata']['slot_id']) && absint($session_data['metadata']['slot_id']) === $slot_id;
            if ($paid && $slot_match) {
                $this->finalize_booking($slot_id, $session_data, 'reconcile_cron');
                $finalized++;
            } else {
                $pending++;
            }
        }

        if (method_exists($this, 'log_event')) {
            $this->log_event('reconcile_completed', 'Reconciliation run completed.', array(
                'checked' => $checked,
                'finalized' => $finalized,
                'pending' => $pending,
            ));
        }
    }

    private function get_recent_checkout_candidates($limit = 40, $hours_back = 72)
    {
        if (!method_exists($this, 'get_logs_table_name')) {
            return array();
        }

        global $wpdb;
        $table = $this->get_logs_table_name();
        $safe_limit = max(1, min(100, absint($limit)));
        $safe_hours = max(1, min(336, absint($hours_back)));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT context_json FROM {$table} WHERE event_key = %s AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d HOUR) ORDER BY id DESC LIMIT %d",
                'checkout_session_created',
                $safe_hours,
                $safe_limit
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return array();
        }

        $items = array();
        $seen = array();
        foreach ($rows as $row) {
            $raw = isset($row['context_json']) ? (string) $row['context_json'] : '';
            if ($raw === '') {
                continue;
            }

            $ctx = json_decode($raw, true);
            if (!is_array($ctx)) {
                $ctx = json_decode(wp_unslash($raw), true);
            }
            if (!is_array($ctx)) {
                continue;
            }

            $session_id = isset($ctx['session_id']) ? sanitize_text_field((string) $ctx['session_id']) : '';
            $slot_id = isset($ctx['slot_id']) ? absint($ctx['slot_id']) : 0;
            if ($session_id === '' || $slot_id < 1) {
                continue;
            }
            if (isset($seen[$session_id])) {
                continue;
            }

            $seen[$session_id] = true;
            $items[] = array(
                'session_id' => $session_id,
                'slot_id' => $slot_id,
            );
        }

        return $items;
    }
}
