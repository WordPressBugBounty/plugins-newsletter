<?php

defined('ABSPATH') || exit;

class NewsletterComposerAdmin extends NewsletterModuleAdmin {

    static $instance;

    /**
     * @return NewsletterComposerAdmin
     */
    static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct() {
        parent::__construct('composer');
    }

    function wp_loaded() {
//        if (defined('DOING_AJAX') && DOING_AJAX && $this->is_allowed()) {
//            add_action('wp_ajax_tnp_composer_block', [$this, 'ajax_tnp_composer_block']);
//        }
    }

}
