<?php

defined('ABSPATH') || exit;

class NewsletterEmailsAdmin extends NewsletterModuleAdmin {

    static $instance;
    var $themes;

    /**
     * @return NewsletterEmailsAdmin
     */
    static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct() {
        parent::__construct('emails');
        $this->themes = new NewsletterThemes('emails');
        // Thank you to plugins that add the WP editor on other admin plugin pages...
        if (isset($_GET['page']) && $_GET['page'] == 'newsletter_emails_edit') {
            global $wp_actions;
            $wp_actions['wp_enqueue_editor'] = 1;
        }

        add_action('wp_ajax_tnpc_test_raw_html', array($this, 'ajax_tnpc_test_raw_html'));

        add_action('wp_ajax_newsletter_emails_ai_prompt', [$this, 'ajax_ai_prompt']);
            add_action('wp_ajax_newsletter_emails_ai_save', [$this, 'ajax_ai_save']);
    }

    function wp_loaded() {
        if (defined('DOING_AJAX') && DOING_AJAX && $this->is_allowed()) {

        }
    }

    function kses($text) {
        return $text;
        $allowed = wp_kses_allowed_html('post');
        $allowed['input'] = ['class' => true, 'placeholder' => true, 'type' => true, 'style' => true, 'size' => true, 'dir' => true, 'name' => true, 'required' => true, 'value' => true, 'aria-*' => true];
        $allowed['style'] = [];
        $allowed['html'] = [];
        $allowed['head'] = [];
        $allowed['body'] = ['class' => true, 'style' => true];

        // TODO: remove forms and inputs
        //$allowed['textarea'] = ['class' => true, 'placeholder' => true, 'dir' => true, 'style' => true, 'name' => true, 'required' => true, 'aria-*' => true];
        //$allowed['select'] = ['class' => true, 'dir' => true, 'style' => true, 'name' => true, 'required' => true, 'aria-*' => true];
        //$allowed['option'] = ['value' => true, 'selected' => true];
        //if (!isset($allowed['button'])) {
        //    $allowed['button'] = ['class' => true, 'dir' => true, 'name' => true, 'style' => true, 'aria-*' => true];
        //}

        return wp_kses($text, $allowed);
    }

    function ajax_ai_save() {
        check_ajax_referer('tnp-ai');

        $id = (int) $_GET['id'];
        $email = $this->get_email($id);
        if (!$email) {
            wp_send_json_error();
        }

        $json = json_decode(file_get_contents('php://input'), true);
        if (!is_array($json) || !isset($json['content'])) {
            wp_send_json_error();
        }

        $data = [];
        $data['id'] = $email->id;
        $data['message'] = $this->kses($json['content']);
        $this->save_email($data);
        wp_send_json_success($data);
    }

    function ajax_ai_prompt() {
        check_ajax_referer('tnp-ai');

        $prompt = '';
        $html = '';
        $image_data = null;
        $image_mime = 'image/jpeg';
        $history = [];

        if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
            $json = json_decode(file_get_contents('php://input'), true);
            $prompt = wp_strip_all_tags(wp_unslash($json['message'] ?? ''));
            $html = $this->kses(wp_unslash($json['html'] ?? ''));
            $image_data = $json['image'] ?? null;
            $image_mime = $json['image_mime'] ?? 'image/jpeg';
            $history = $json['history'] ?? [];
        }

        if (empty($prompt)) {
            wp_send_json_success(['html' => $html]);
        }

        $message = '';
        if (!empty($history)) {
            $message .= 'Previous requests, already applied to the current email HTML:' . "\n";
            foreach ($history as $item) {
                $message .= '- ' . wp_strip_all_tags($item) . "\n";
            }
            $message .= "\n";
        }
        if (!empty($html)) {
            $message .= 'Current email HTML: """' . $html . '"""' . "\n\n" . 'Use it as starting point for the new modifications.' . "\n\n";
        }
        $message .= 'New change request: ' . $prompt;

        if ($image_data) {
            $message .= "\n\n" . 'Use the provided image as design reference.';
        }

        $this->logger->debug($message);

        $builder = wp_ai_client_prompt($message);

        if ($image_data) {
            $builder->with_file($image_data, $image_mime);
        }

        $instructions = file_get_contents(__DIR__ . '/instructions.md');
        //$instructions = str_replace(['{customfields_max}'], [NEWSLETTER_PROFILE_MAX], $instructions);
//        if (NEWSLETTER_DEBUG) {
//            file_put_contents(__DIR__ . '/instructions-generated.md', $instructions);
//            error_log($instructions);
//        }

        $builder->using_system_instruction($instructions);
        $builder->as_json_response(
                [
                    'type' => 'object',
                    'required' => ['html', 'explanation'],
                    'properties' => [
                        'html' => [
                            'type' => 'string',
                            'description' => 'The email full HTML generated, with all best practices for email clients'
                        ],
                        'explanation' => [
                            'type' => 'string',
                            'description' => 'Description of what has been done or not done'
                        ]
                    ],
                    'additionalProperties' => false
                ]
        );

        $result = $builder->generate_text_result();

        if (is_wp_error($result)) {
            wp_send_json_success(['html' => $html, 'explanation' => 'Nothing changed, there was an error: ' . wp_strip_all_tags($result->get_error_message())]);
        }

        $reply = $result->toText();
        $this->logger->debug('Model: ' . $result->getModelMetadata()->getName());
        $this->logger->debug($reply);

        $data = json_decode($reply);
        if (!is_object($data) || !isset($data->html)) {
            wp_send_json_success(['html' => $html, 'explanation' => 'Nothing changed, the AI returned an invalid answer. Please try again.']);
        }

        $data->html = $this->kses($data->html);
        $data->explanation = wp_strip_all_tags($data->explanation ?? '');
        wp_send_json_success($data);
    }

    function admin_menu() {
        //$this->add_menu_page('index', 'Newsletters');
        $this->add_admin_page('list', 'Newsletter List');
        $this->add_admin_page('new', 'Newsletter New');
        $this->add_admin_page('edit', 'Newsletter Edit');
        $this->add_admin_page('logs', 'Newsletter Logs');
        $this->add_admin_page('versions', 'Newsletter Versions');
        $this->add_admin_page('theme', 'Newsletter Themes');
        $this->add_admin_page('composer', 'The Composer');
        $this->add_admin_page('editorhtml', 'HTML Editor');
        $this->add_admin_page('editorhtmlai', 'HTML AI Editor');
        $this->add_admin_page('editortinymce', 'TinyMCE Editor');
        $this->add_admin_page('presets', 'Presets');
        $this->add_admin_page('presets-edit', 'Presets');

        $this->add_admin_page('settings', 'Settings');

        $this->add_admin_page('automated', 'Automated');
        $this->add_admin_page('autoresponder', 'Autoresponder');
    }

    /** Returns the correct admin page to edit the newsletter with the correct editor. */
    function get_editor_url($email_id, $editor_type) {
        switch ($editor_type) {
            case NewsletterEmails::EDITOR_COMPOSER:
                return '?page=newsletter_emails_composer&id=' . urlencode($email_id);
            case NewsletterEmails::EDITOR_HTML:
                return '?page=newsletter_emails_editorhtml&id=' . urlencode($email_id);
            case NewsletterEmails::EDITOR_TINYMCE:
                return '?page=newsletter_emails_editortinymce&id=' . urlencode($email_id);
        }
    }

    function get_edit_button($email, $only_icon = true) {

        $editor_type = $this->get_editor_type($email);
        if ($email->status === TNP_Email::STATUS_DRAFT) {
            $edit_url = $this->get_editor_url($email->id, $editor_type);
        } else {
            $edit_url = '?page=newsletter_emails_edit&id=' . urlencode($email->id);
        }

        $icon_class = 'edit';
        if ($only_icon) {
            return '<a class="button-primary tnpc-button" href="' . $edit_url . '" title="' . esc_attr__('Edit', 'newsletter') . '">' .
                    '<i class="fas fa-' . $icon_class . '"></i></a>';
        } else {
            return '<a class="button-primary tnpc-button" href="' . $edit_url . '" title="' . esc_attr__('Edit', 'newsletter') . '">' .
                    '<i class="fas fa-' . $icon_class . '"></i> ' . __('Edit', 'newsletter') . '</a>';
        }
    }

    /** Returns the correct editor type for the provided newsletter. Contains backward compatibility code. */
    function get_editor_type($email) {
        $email = (object) $email;
        $editor_type = $email->editor;

        // Backward compatibility
        $email_options = maybe_unserialize($email->options);
        if (isset($email_options['composer'])) {
            $editor_type = NewsletterEmails::EDITOR_COMPOSER;
        }
        // End backward compatibility

        return $editor_type;
    }

    private function set_test_subject_to($email) {
        if ($email->subject == '') {
            $email->subject = 'Dummy subject, it was empty (remember to set it)';
        }
        if (!defined('NEWSLETTER_TEST_SUBJECT_POSTFIX')) {
            define('NEWSLETTER_TEST_SUBJECT_POSTFIX', ' [PREVIEW]');
        }
        $email->subject = $email->subject . NEWSLETTER_TEST_SUBJECT_POSTFIX;
    }

    function ajax_tnpc_test_raw_html() {
        check_admin_referer('save');
        if (!$this->is_allowed()) {
            wp_send_json_error('Not allowed', 403);
        }

        require_once NEWSLETTER_INCLUDES_DIR . '/controls.php';

        $controls = new NewsletterControls();

        $email = $this->get_email($controls->data['id']);
        $email->id = 0; // To unlink from the database object

        if ($this->is_html_allowed()) {
            $email->message = $controls->data['message'];
        } else {
            $email->message = wp_kses_post($controls->data['message']);
        }

        $email->subject = wp_strip_all_tags($controls->data['subject']);

        $email->track = $controls->data['track'] ?? (int) Newsletter::instance()->get_option('track');
        if (!empty($controls->data['sender_email'])) {
            $email->options['sender_email'] = $controls->data['sender_email'];
        }
        if (!empty($controls->data['sender_name'])) {
            $email->options['sender_name'] = $controls->data['sender_name'];
        }

        if (isset($_POST['to_email'])) {
            $message = NewsletterEmailsAdmin::instance()->send_test_newsletter_to_email_address($email, $controls->data['test_email']);
            echo $message;
        } else {
            NewsletterEmailsAdmin::instance()->send_test_email($email, $controls);
            if ($controls->messages) {
                echo $controls->messages;
            } else {
                echo $controls->errors;
            }
        }

        die();
    }

    /**
     * Send an email to the test subscribers.
     *
     * @param TNP_Email $email Could be any object with the TNP_Email attributes
     * @param NewsletterControls $controls
     */
    function send_test_email($email, $controls) {
        if (!$email) {
            $controls->errors = __('Newsletter should be saved before sending a test', 'newsletter');
            return;
        }

        $original_subject = $email->subject;
        $this->set_test_subject_to($email);

        $users = NewsletterUsersAdmin::instance()->get_test_users();
        if (count($users) == 0) {
            $controls->errors = '' . __('There are no test subscribers to send to', 'newsletter') .
                    '. <a href="https://www.thenewsletterplugin.com/plugins/newsletter/subscribers-module#test" target="_blank"><strong>' .
                    __('Read more', 'newsletter') . '</strong></a>.';
        } else {
            $r = Newsletter::instance()->send($email, $users, true);
            $emails = [];
            foreach ($users as $user) {
                $emails[] = '<a href="admin.php?page=newsletter_users_edit&id=' . $user->id . '" target="_blank">' . $user->email . '</a>';
            }
            if (is_wp_error($r)) {
                $controls->errors = 'Something went wrong. Check the error logs on status page.<br>';
                $controls->errors .= __('Test subscribers:', 'newsletter');
                $controls->errors .= ' ' . implode(', ', $emails);
                $controls->errors .= '<br>';
                $controls->errors .= '<strong>' . esc_html($r->get_error_message()) . '</strong><br>';
                $controls->errors .= '<a href="https://www.thenewsletterplugin.com/documentation/email-sending-issues" target="_blank"><strong>' . __('Read more about delivery issues', 'newsletter') . '</strong></a>.';
            } else {
                $controls->messages = __('Test sent to:', 'newsletter');
                //$controls->messages .= __('Test subscribers:', 'newsletter');

                $controls->messages .= ' ' . implode(', ', $emails);
                $controls->messages .= '.<br>';
                $controls->messages .= 'If the message does not shows up on the mailbox, check the spam folder and run a test from the '
                        . '<a href="?page=newsletter_system_delivery" target="_blank"><strong>System/Delivery panel</strong></a>.<br>';

                $controls->messages .= '<a href="https://www.thenewsletterplugin.com/documentation/subscribers#test" target="_blank"><strong>' .
                        __('Read more about test subscribers', 'newsletter') . '</strong></a>.<br>';
                $controls->messages .= '<a href="https://www.thenewsletterplugin.com/documentation/email-sending-issues" target="_blank"><strong>' . __('Read more about delivery issues', 'newsletter') . '</strong></a>.';
            }
        }
        $email->subject = $original_subject;
    }

    /**
     * Send an email to the test subscribers.
     *
     * @param TNP_Email $email Could be any object with the TNP_Email attributes
     * @param string $email_address
     *
     * @throws Exception
     */
    function send_test_newsletter_to_email_address($email, $email_address) {

        if (!$email) {
            throw new Exception(__('Newsletter should be saved before send a test', 'newsletter'));
        }

        $this->set_test_subject_to($email);

        $dummy_subscriber = $this->get_user($email_address);

        if (!$dummy_subscriber) {
            $dummy_subscriber = $this->get_dummy_user();
            $dummy_subscriber->email = $email_address;
        }

        $result = Newsletter::instance()->send($email, [$dummy_subscriber], true);

        if (is_wp_error($result)) {
            $error_message = 'Something went wrong. Check the error logs on the System/Logs page.<br>';
            $error_message .= '<br>';
            $error_message .= '<strong>' . esc_html($result->get_error_message()) . '</strong><br>';
            $error_message .= '<a href="https://www.thenewsletterplugin.com/documentation/email-sending-issues" target="_blank"><' . __('Read more about delivery issues', 'newsletter') . '</a>.';
            throw new Exception($error_message);
        }

        $messages = __('Test sent to:', 'newsletter');

        $messages .= ' ' . esc_html($email_address);
        $messages .= '.<br>';
        $messages .= 'If the message does not shows up on the mailbox, check the spam folder and run a test from the '
                . '<a href="?page=newsletter_system_delivery" target="_blank"><strong>System/Delivery panel</strong></a>.<br>';
        $messages .= '<a href="https://www.thenewsletterplugin.com/documentation/email-sending-issues" target="_blank">' . __('Read more about delivery issues', 'newsletter') . '</a>.';

        return $messages;
    }

    function log($email_id, $description) {
        global $current_user;

        if (defined('DOING_CRON') && DOING_CRON) {
            $user = '[cron]';
        } elseif ($current_user) {
            $user = $current_user->user_login;
        } else {
            $user = '[no user]';
        }
        Newsletter\Logs::add('newsletter-' . $email_id, '[' . $user . '] ' . $description);
    }
}
