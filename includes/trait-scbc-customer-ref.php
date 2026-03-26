<?php
if (!defined('ABSPATH')) {
    exit;
}

trait SCBC_Customer_Ref_Trait
{
    private function create_customer_ref_token($customer_email, $slot_id = 0, $session_id = '')
    {
        $email = sanitize_email((string) $customer_email);
        if (empty($email) || !is_email($email)) {
            return '';
        }

        $token = strtolower(wp_generate_password(24, false, false));
        $payload = array(
            'email' => $email,
            'slot_id' => absint($slot_id),
            'session_id' => sanitize_text_field((string) $session_id),
            'created_at' => current_time('mysql'),
        );
        set_transient('scbc_customer_ref_' . $token, $payload, DAY_IN_SECONDS * 30);

        if (method_exists($this, 'log_event')) {
            $this->log_event('customer_ref_created', 'Customer reference token was created.', array(
                'slot_id' => absint($slot_id),
                'session_id' => sanitize_text_field((string) $session_id),
            ));
        }

        return $token;
    }

    private function resolve_customer_email_from_ref($token)
    {
        $safe_token = sanitize_key((string) $token);
        if ($safe_token === '') {
            return '';
        }

        $payload = get_transient('scbc_customer_ref_' . $safe_token);
        if (!is_array($payload) || empty($payload['email'])) {
            return '';
        }

        $email = sanitize_email((string) $payload['email']);
        return (is_email($email)) ? $email : '';
    }

    private function resolve_customer_email_from_request()
    {
        $ref = isset($_GET['customer_ref']) ? sanitize_key(wp_unslash($_GET['customer_ref'])) : '';
        if (!empty($ref)) {
            $email_from_ref = $this->resolve_customer_email_from_ref($ref);
            if (!empty($email_from_ref)) {
                return $email_from_ref;
            }
        }

        $legacy_email = isset($_GET['customer_email']) ? sanitize_email(wp_unslash($_GET['customer_email'])) : '';
        if (!empty($legacy_email) && is_email($legacy_email)) {
            return $legacy_email;
        }

        $scbc_email = isset($_GET['scbc_email']) ? sanitize_email(wp_unslash($_GET['scbc_email'])) : '';
        if (!empty($scbc_email) && is_email($scbc_email)) {
            return $scbc_email;
        }

        return '';
    }
}
