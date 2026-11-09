<?php

defined('ABSPATH') || exit;

class NewsletterCustomForms extends NewsletterModule {

    static $instance;

    /**
     * @return NewsletterCustomForms
     */
    static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct() {
        parent::__construct('customforms');
    }

    function get_form($id) {
        global $wpdb;
        $store = NewsletterStore::singleton();
        $form = $store->get_single($wpdb->prefix . 'newsletter_customforms', $id);
        if ($form) {
            $this->deserialize($form);
        }
        return $form;
    }

    function deserialize($form) {
        // Negative IDs mean "disable" the autoresponder. Do not use wp_parse_id_list()
        $form->autoresponders = wp_parse_list($form->autoresponders ?? []);
        $form->lists = wp_parse_list($form->lists ?? []);
    }

    function get_signature($form) {
        return hash_hmac('sha256', $form->id, $form->token);
    }

    function get_form_key($form) {
        // The token can be used directly, it is used just to create something like an uuid
        return $form->id . '-' . $this->get_signature($form);
    }

    function get_form_by_key($key) {

        if (!$key || !is_string($key)) {
            return null;
        }

        list($id, $signature) = explode('-', $key, 2);

        $form = $this->get_form($id);
        if (!$form) {
            return null;
        }

        if (!hash_equals($this->get_signature($form), $signature)) {
            return null;
        }

        return $form;
    }

    /**
     *
     * @param TNP_Subscription $subscription
     * @param object $form
     */
    function update_subscription($subscription, $form) {
        if ($form->welcome_email_id) {
            $subscription->welcome_email_id = $form->welcome_email_id;
        }

        if ($form->welcome_page_id) {
            $subscription->welcome_page_id = $form->welcome_page_id;
        }

        if ($form->lists) {
            $subscription->data->add_lists($form->lists);
        }

        if ($form->autoresponders) {
            $subscription->autoresponders = $form->autoresponders;
        }

        if ($form->optin) {
            $subscription->optin = $form->optin;
        }
    }

    function render($form, $attrs = []) {
        $content = $form->content;

        $map = [
            '[first-name' => '[newsletter_field name="first_name"',
            '[last-name' => '[newsletter_field name="last_name"',
            '[email' => '[newsletter_field name="email"',
            '[button' => '[newsletter_field name="button"',
            '[lists-dropdown' => '[newsletter_field name="lists" layout="dropdown"',
            '[lists' => '[newsletter_field name="lists"',
            '[privacy' => '[newsletter_field name="privacy"',
            '[tracking' => '[newsletter_field name="track"',
            '[gender' => '[newsletter_field name="gender"',
        ];

        $lists = $this->get_lists_public();
        foreach ($lists as $list) {
            $map['[list-' . $list->id] = '[newsletter_field name="list" number="' . $list->id . '"';
        }

        $cfs = $this->get_customfields_public();
        foreach ($cfs as $cf) {
            $map['[custom-field-' . $cf->id] = '[newsletter_field name="customfield" number="' . $cf->id . '"';
        }

        // Replace the shortcodes
        $content = str_replace(array_keys($map), array_values($map), $content);

        // Sionce the AI editor should provide the full CSS, we do not add the standard class to avoid conflicts
        if ($form->editor === '1') {
            $b = '<div class="tnp ' . esc_attr(sanitize_html_class($attrs['class'] ?? '')) . '">' . "\n";
        } else {
            $b = '<div class="tnp tnp-subscription ' . esc_attr(sanitize_html_class($attrs['class'] ?? '')) . '">' . "\n";
        }

        $b .= '<form method="post" action="' . esc_attr($this->build_action_url('s')) . '"';

        if (!empty($attrs['id'])) {
            //$buffer .= ' id="' . esc_attr($attrs['id']) . '"';
        }

        if (!empty($attrs['ajax'])) {
            $b .= ' class="tnp-ajax"';
        }

        $b .= '>' . "\n";
        $b .= '<input type="hidden" name="nfk" value="' . esc_attr(NewsletterCustomForms::instance()->get_form_key($form)) . '">';
        if (strpos($content, 'name="ntr_cb"') !== false) {
            $b .= '<input type="hidden" name="ntr"  value="1">';
        }
        $b .= $content;
        $b .= '</form></div>';

        return do_shortcode($b);
    }
}

NewsletterCustomForms::instance();
