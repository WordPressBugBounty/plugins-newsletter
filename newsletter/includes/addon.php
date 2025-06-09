<?php

/**
 * User by add-ons as base-class.
 */
class NewsletterAddon {

    var $logger;
    var $admin_logger;
    var $name;
    var $options;
    var $version;
    var $labels;
    var $menu_priority = 100;
    var $weekly_check = true;

    public function __construct($name, $version = '0.0.0', $dir = '') {
        $this->name = $name;
        $this->version = $version;
        if (is_admin()) {
            $old_version = get_option('newsletter_' . $name . '_version');
            if ($version !== $old_version) {
                $this->upgrade($old_version === false);
                update_option('newsletter_' . $name . '_version', $version, false);
            }
        }
        add_action('newsletter_init', [$this, 'init']);
        //Load translations from specific addon /languages/ directory
        load_plugin_textdomain('newsletter-' . $this->name, false, 'newsletter-' . $this->name . '/languages/');

        if ($this->weekly_check && is_admin() && !wp_next_scheduled('newsletter_addon_' . $this->name)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'weekly', 'newsletter_addon_' . $this->name);
        }

        add_action('newsletter_addon_' . $this->name, [$this, 'weekly_check']);

        if ($dir) {
            register_deactivation_hook($dir . '/' . $this->name . '.php', [$this, 'deactivate']);
        }
    }

    /**
     * Method to be overridden and invoked on version change or on first install.
     *
     * @param bool $first_install
     */
    function upgrade($first_install = false) {

    }

    /**
     * Method to be overridden to initialize the add-on. It is invoked when Newsletter
     * fires the <code>newsletter_init</code> event.
     */
    function init() {
        if (is_admin()) {
            if ($this->is_allowed()) {
                add_action('admin_menu', [$this, 'admin_menu'], $this->menu_priority);
                // Should be registered only on our admin page, need to fix the $is_admin_page evaluation moment on
                // NewsletterAdmin class.
                add_action('newsletter_menu', [$this, 'newsletter_menu']);

                // TODO: remove when all addon has been updated
                if (method_exists($this, 'settings_menu')) {
                    add_filter('newsletter_menu_settings', [$this, 'settings_menu']);
                }

                if (method_exists($this, 'subscribers_menu')) {
                    add_filter('newsletter_menu_subscribers', [$this, 'subscribers_menu']);
                }
            }
            add_filter('newsletter_support_data', [$this, 'support_data'], 10, 1);
        }
    }

    /**
     * To be overridden by the single addon.
     */
    function weekly_check() {
        // To be implemented by the single addon
    }

    /**
     * To be overridden by the single addon.
     *
     * @return array
     */
    function get_support_data() {
        return [];
    }

    function support_data($data = []) {
        $d = $this->get_support_data();
        $d = array_merge($d, ['version' => $this->version]);
        $data[$this->name] = $d;
        return $data;
    }

    function deactivate() {
        $logger = $this->get_logger();
        $logger->info($this->name . ' deactivated');

        // The periodic check
        wp_unschedule_hook('newsletter_addon_' . $this->name);
    }

    function admin_menu() {

    }

    function newsletter_menu() {

    }

    function add_settings_menu_page($title, $url) {
        NewsletterAdmin::$menu['settings'][] = ['label' => $title, 'url' => $url];
    }

    function add_subscription_menu_page($title, $url) {
        NewsletterAdmin::$menu['subscription'][] = ['label' => $title, 'url' => $url];
    }

    function add_newsletters_menu_page($title, $url) {
        NewsletterAdmin::$menu['newsletters'][] = ['label' => $title, 'url' => $url];
    }

    function add_subscribers_menu_page($title, $url) {
        NewsletterAdmin::$menu['subscribers'][] = ['label' => $title, 'url' => $url];
    }

    function add_forms_menu_page($title, $url) {
        NewsletterAdmin::$menu['forms'][] = ['label' => $title, 'url' => $url];
    }

    function get_current_language() {
        return Newsletter::instance()->get_current_language();
    }

    function is_all_languages() {
        return empty(NewsletterAdmin::instance()->language());
    }

    function is_allowed() {
        return Newsletter::instance()->is_allowed();
    }

    function is_admin_page() {
        return NewsletterAdmin::instance()->is_admin_page();
    }

    function get_languages() {
        return Newsletter::instance()->get_languages();
    }

    function is_multilanguage() {
        return Newsletter::instance()->is_multilanguage();
    }

    /**
     * General logger for this add-on.
     *
     * @return NewsletterLogger
     */
    function get_logger() {
        if (!$this->logger) {
            $this->logger = new NewsletterLogger($this->name);
        }
//        $this->setup_options();
//        if (!empty($this->options['log_level'])) {
//            if ($this->options['log_level'] > $logger->level) {
//                $logger->level = $this->options['log_level'];
//            }
//        }
        return $this->logger;
    }

    /**
     * Specific logger for administrator actions.
     *
     * @return NewsletterLogger
     */
    function get_admin_logger() {
        if (!$this->admin_logger) {
            $this->admin_logger = new NewsletterLogger($this->name . '-admin');
        }
        return $this->admin_logger;
    }

    /**
     * Loads and prepares the options. It can be used to late initialize the options to save some resources on
     * add-ons which do not need to do something on each page load.
     */
    function setup_options() {
        if ($this->options) {
            return;
        }
        $this->options = $this->get_option_array('newsletter_' . $this->name, []);
    }

    function get_option_array($name) {
        $opt = get_option($name, []);
        if (!is_array($opt)) {
            return [];
        }
        return $opt;
    }

    /**
     * Retrieve the stored options, merged with the specified language set.
     *
     * @param string $language
     * @return array
     */
    function get_options($language = '') {
        if ($language) {
            return array_merge($this->get_option_array('newsletter_' . $this->name), $this->get_option_array('newsletter_' . $this->name . '_' . $language));
        } else {
            return $this->get_option_array('newsletter_' . $this->name);
        }
    }

    /**
     * Saved the options under the correct keys and update the internal $options
     * property.
     * @param array $options
     */
    function save_options($options, $language = '') {
        if ($language) {
            update_option('newsletter_' . $this->name . '_' . $language, $options);
        } else {
            update_option('newsletter_' . $this->name, $options);
            $this->options = $options;
        }
    }

    function merge_defaults($defaults) {
        $options = $this->get_option_array('newsletter_' . $this->name, []);
        $options = array_merge($defaults, $options);
        $this->save_options($options);
    }

    /**
     *
     */
    function setup_labels() {
        if (!$this->labels) {
            $labels = [];
        }
    }

    function get_label($key) {
        if (!$this->options)
            $this->setup_options();

        if (!empty($this->options[$key])) {
            return $this->options[$key];
        }

        if (!$this->labels)
            $this->setup_labels();

        // We assume the required key is defined. If not there is an error elsewhere.
        return $this->labels[$key];
    }

    /**
     * Equivalent to $wpdb->query() but logs the event in case of error.
     *
     * @global wpdb $wpdb
     * @param string $query
     */
    function query($query) {
        global $wpdb;

        $r = $wpdb->query($query);
        if ($r === false) {
            $logger = $this->get_logger();
            $logger->fatal($query);
            $logger->fatal($wpdb->last_error);
        }
        return $r;
    }

    function get_results($query) {
        global $wpdb;
        $r = $wpdb->get_results($query);
        if ($r === false) {
            $this->logger->fatal($query);
            $this->logger->fatal($wpdb->last_error);
        }
        return $r;
    }

    function get_row($query) {
        global $wpdb;
        $r = $wpdb->get_row($query);
        if ($r === false) {
            $this->logger->fatal($query);
            $this->logger->fatal($wpdb->last_error);
        }
        return $r;
    }

    function get_user($id_or_email) {
        return Newsletter::instance()->get_user($id_or_email);
    }

    function show_email_status_label($email) {
        return NewsletterAdmin::instance()->show_email_status_label($email);
    }

    function send_test_email($email, $controls) {
        NewsletterEmailsAdmin::instance()->send_test_email($email, $controls);
    }
}

class NewsletterFormManagerAddon extends NewsletterAddon {

    var $menu_title = null;
    var $menu_description = null;
    var $menu_slug = null;
    var $index_page = null;
    var $edit_page = null;
    var $welcome_page = null;
    var $confirmation_page = null;
    var $logs_page = null;
    var $dir = '';
    var $forms = null; // For caching

    function __construct($name, $version, $dir, $menu_slug = null) {
        parent::__construct($name, $version, $dir);
        $this->dir = $dir;
        $this->menu_slug = $menu_slug;
        if (empty($this->menu_slug)) {
            $this->menu_slug = $this->name;
        }
        $this->setup_options();
    }

    function init() {
        parent::init();

        if (is_admin() && $this->is_allowed()) {

            $this->index_page = 'newsletter_' . $this->menu_slug . '_index';
            $this->edit_page = 'newsletter_' . $this->menu_slug . '_edit';
            $this->welcome_page = 'newsletter_' . $this->menu_slug . '_welcome';
            $this->confirmation_page = 'newsletter_' . $this->menu_slug . '_confirmation';
            $this->logs_page = 'newsletter_' . $this->menu_slug . '_logs';

            add_filter('newsletter_lists_notes', [$this, 'hook_newsletter_lists_notes'], 10, 2);
        }
    }

    function hook_newsletter_lists_notes($notes, $list_id) {
        if (!$this->forms) {
            $this->forms = $this->get_forms();
        }
        foreach ($this->forms as $form) {
            $ok = false;
            $form_options = $this->get_form_options($form->id);
            // Too many years of development
            if (!empty($form_options['lists']) && is_array($form_options['lists']) && in_array($list_id, $form_options['lists'])) {
                $ok = true;
            } elseif (!empty($form_options['preferences_' . $list_id])) {
                $ok = true;
            } elseif (!empty($form_options['preferences']) && is_array($form_options['preferences']) && in_array($list_id, $form_options['preferences'])) {
                $ok = true;
            }
            if ($ok) {
                $notes[] = 'Linked to form "' . $form->title . '"';
            }
        }

        return $notes;
    }

    function hook_newsletter_autoresponder_sources($list, $id) {
        $forms = $this->get_forms();
        foreach ($forms as $form) {
            $settings = $this->get_form_options($form->id);

            if (empty($settings['autoresponders'])) {
                continue;
            }

            if (in_array('' . $id, $settings['autoresponders'])) {
                $s = new Newsletter\Source($form->title, $this->menu_title);
                $s->action = 'on';
                $list[] = $s;
            } elseif (in_array('-' . $id, $settings['autoresponders'])) {
                $s = new Newsletter\Source($form->title, $this->menu_title);
                $s->action = 'off';
                $list[] = $s;
            }
        }
        return $list;
    }

    /**
     * Basic subscription object to collect the data and option of a 3rd party form
     * integration.
     *
     * @param type $form_options
     * @return type
     */
    function get_default_subscription($form_options, $form_id = null) {
        $subscription = NewsletterSubscription::instance()->get_default_subscription();
        $subscription->floodcheck = false;

        if ($form_id) {
            $subscription->data->referrer = sanitize_key($this->name . '-' . $form_id);
        }

        // 'welcome_email' is a flag indicating what to use for that email (0- default, 1 - custom, 2 - don't send)
        if (!empty($form_options['welcome_email'])) {
            if ($form_options['welcome_email'] == '1') {
                $subscription->welcome_email_id = (int) $form_options['welcome_email_id'];
            } else {
                $subscription->welcome_email_id = -1;
            }
        }

        // Not for 3rd party form integration
        if (!empty($form_options['welcome_page_id'])) {
            $subscription->welcome_page_id = (int) $form_options['welcome_page_id'];
        }

        if (!empty($form_options['confirmation_email'])) {
            if ($form_options['confirmation_email'] == '1') {
                $subscription->confirmation_email_id = (int) $form_options['confirmation_email_id'];
            } else {
                $subscription->confirmation_email_id = -1;
            }
        }

        // Not for 3rd party form integration
        if (!empty($form_options['confirmation_page_id'])) {
            $subscription->confirmation_page_id = (int) $form_options['confirmation_page_id'];
        }

        if (!empty($form_options['status'])) {
            $subscription->optin = $form_options['status'];
        }

        $subscription->data->add_lists($form_options['lists'] ?? []);

        // The parser already removes the empty/non scalar values
        $subscription->autoresponders = wp_parse_list($form_options['autoresponders'] ?? []);

        return $subscription;
    }

    function newsletter_menu() {
        $this->add_subscription_menu_page($this->menu_title, '?page=' . $this->index_page);
    }

    function admin_menu() {
        add_submenu_page('newsletter_main_index', $this->menu_title, '<span class="tnp-side-menu">' . $this->menu_title . '</span>', 'exist', $this->index_page,
                function () {
                    require_once NEWSLETTER_INCLUDES_DIR . '/controls.php';
                    $controls = new NewsletterControls();
                    require $this->dir . '/admin/index.php';
                }
        );
        add_submenu_page('admin.php', $this->menu_title, '<span class="tnp-side-menu">' . $this->menu_title . '</span>', 'exist', $this->edit_page,
                function () {
                    require_once NEWSLETTER_INCLUDES_DIR . '/controls.php';
                    $controls = new NewsletterControls();

                    $form = $this->get_form(sanitize_key($_GET['id'] ?? ''));
                    if (!$form) {
                        echo 'Form not found';
                        return;
                    }
                    require $this->dir . '/admin/edit.php';
                }
        );

        if (file_exists($this->dir . '/admin/welcome.php')) {
            add_submenu_page('admin.php', $this->menu_title, '<span class="tnp-side-menu">' . $this->menu_title . '</span>', 'exist', $this->welcome_page,
                    function () {
                        /** @since 8.3.9 */
                        require_once NEWSLETTER_INCLUDES_DIR . '/controls.php';
                        $controls = new NewsletterControls();

                        /** @since 8.3.9 */
                        $form = $this->get_form(sanitize_key($_GET['id'] ?? ''));
                        if (!$form) {
                            echo 'Form not found';
                            return;
                        }

                        require $this->dir . '/admin/welcome.php';
                    }
            );
        }

        /** @since 8.7.5 */
        if (file_exists($this->dir . '/admin/confirmation.php')) {
            add_submenu_page('admin.php', $this->menu_title, '<span class="tnp-side-menu">' . $this->menu_title . '</span>', 'exist', $this->confirmation_page,
                    function () {

                        require_once NEWSLETTER_INCLUDES_DIR . '/controls.php';
                        $controls = new NewsletterControls();

                        $form = $this->get_form(sanitize_key($_GET['id'] ?? ''));
                        if (!$form) {
                            echo 'Form not found';
                            return;
                        }

                        require $this->dir . '/admin/confirmation.php';
                    }
            );
        }

        if (file_exists($this->dir . '/admin/logs.php')) {
            add_submenu_page('admin.php', $this->menu_title, $this->menu_title, 'exist', $this->logs_page,
                    function () {
                        require_once NEWSLETTER_INCLUDES_DIR . '/controls.php';
                        $controls = new NewsletterControls();

                        $form = $this->get_form(sanitize_key($_GET['id'] ?? ''));
                        if (!$form) {
                            echo 'Form not found';
                            return;
                        }
                        require $this->dir . '/admin/logs.php';
                    }
            );
        }
    }

    /**
     * Processes the subscription, logs errors and returns the subscriber.
     *
     * @param TNP_Subscription $subscription
     * @param mixed $form_id
     * @return TNP_User|WP_Error
     */
    function subscribe($subscription, $form_id) {
        $logger = $this->get_logger();
        if ($logger->is_debug) {
            $logger->debug($subscription);
        }

        $user = NewsletterSubscription::instance()->subscribe2($subscription);

        if (is_wp_error($user)) {
            Newsletter\Logs::add($this->name . '-' . $form_id, 'Subcription for ' . $subscription->data->email . ' failed: ' . $user->get_error_message());
        } else {
            Newsletter\Logs::add($this->name . '-' . $form_id, 'Subcription for ' . $subscription->data->email);
        }

        return $user;
    }

    /**
     * Adds a log visible on the "logs" page for the specific form.
     *
     * @param string $form_id
     * @param string $text
     */
    function log($form_id, $text) {
        Newsletter\Logs::add($this->name . '-' . $form_id, $text);
    }

    /**
     * Returns a lists of representations of forms available in the plugin subject of integration.
     * Usually the $fields is not set up on returned objects.
     * Must be implemented.
     *
     * @return TNP_FormManager_Form[] List of forms by 3rd party plugin
     */
    function get_forms() {
        return [];
    }

    /**
     * Build a form general representation of a real form from a form manager plugin extracting
     * only the data required to integrate. The form id is domain of the form manager plugin, so it can be
     * anything.
     * Must be implemented.
     *
     * @param mixed $form_id
     * @return TNP_FormManager_Form
     */
    function get_form($form_id) {
        return null;
    }

    /**
     * Saves the form mapping and integration settings.
     * @param mixed $form_id
     * @param array $data
     */
    public function save_form_options($form_id, $data) {
        $data['autoresponders'] = array_values(array_filter($data['autoresponders'] ?? []));
        update_option('newsletter_' . $this->name . '_' . $form_id, $data, false);
        return $data;
    }

    /**
     * Gets the form mapping and integration settings. Returns an empty array if the dataset is missing.
     * @param mixed $form_id
     * @return array
     */
    public function get_form_options($form_id) {
        return Newsletter::get_option_array('newsletter_' . $this->name . '_' . $form_id);
    }
}

class TNP_FormManager_Form {

    var $id = null;
    var $title = '';
    var $fields = [];
    var $connected = false;
}
