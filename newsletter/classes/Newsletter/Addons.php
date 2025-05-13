<?php

namespace Newsletter;

defined('ABSPATH') || exit;

class Addons {

    /**
     * Get the latest addons information but keeping the old one if the update fails.
     *
     * @return \Newsletter\WP_Error|bool
     */
    static function update() {
        update_option('newsletter_addons_updated', time(), false);

        // HTTP is ok here
        $url = "http://www.thenewsletterplugin.com/wp-content/extensions.json?ver=" . NEWSLETTER_VERSION;
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            return $response;
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error(wp_remote_retrieve_response_code($response), 'HTTP Error');
        }

        $addons = json_decode(wp_remote_retrieve_body($response));

        // Not clear cases
        if (!$addons || !is_array($addons)) {
            return new WP_Error('invalid', 'Invalid JSON');
        }
        update_option('newsletter_addons', $addons, false);
        return true;
    }

    static function clear() {
        update_option('newsletter_addons_updated', 0, false);
    }

    static function get_option_array($key) {
        $value = get_option($key, []);
        if (!is_array($value)) {
            return [];
        }
        return $value;
    }

    static function get_addons() {

        $updated = (int) get_option('newsletter_addons_updated');

        if ($updated < time() - DAY_IN_SECONDS*3) {
            self::update(); // This may fail, we use the old values
        }

        return self::get_option_array('newsletter_addons');
    }
}
