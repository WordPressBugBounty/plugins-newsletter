<?php

/**
 * @property string $to
 * @property string $to_name
 * @property string $subject
 * @property string $body
 * @property array $headers
 * @property string $from
 * @property string $from_name
 */
class TNP_Mailer_Message {

    var $ch; // Transient variable for mailers with turbo send option
    var $to = '';
    var $to_name = '';
    var $headers = [];
    var $user_id = 0;
    var $email_id = 0;
    var $error = '';
    var $subject = '';
    var $body = '';
    var $body_text = '';
    var $from = '';
    var $from_name = '';
}

/**
 * A basic class able to send one or more TNP_Mailer_Message objects using a
 * delivery method (wp-mail(), SMTP, API, ...).
 */
class NewsletterMailer {

    const ERROR_GENERIC = '1';
    const ERROR_FATAL = '2';

    /* @var NewsletterLogger */

    var $logger;
    var $name;
    var $options;
    private $delta;
    protected $batch_size = 1;
    protected $speed = 0;

    public function __construct($name, $options = []) {
        $this->name = $name;
        $this->options = $options;
        if (!empty($this->options['speed'])) {
            $this->speed = max(0, (int) $this->options['speed']);
        }
        if (!empty($this->options['turbo'])) {
            $this->batch_size = max(1, (int) $this->options['turbo']);
        }
    }

    public function get_name() {
        return $this->name;
    }

    public function get_description() {
        return ucfirst($this->name) . ' Addon';
    }

    public function get_batch_size() {
        return $this->batch_size;
    }

    public function get_speed() {
        return $this->speed;
    }

    function send_with_stats($message) {
        $this->delta = microtime(true);
        $r = $this->send($message);
        $this->delta = microtime(true) - $this->delta;
        return $r;
    }

    /**
     *
     * @param TNP_Mailer_Message $message
     * @return bool|WP_Error
     */
    public function send($message) {
        $message->error = 'No mailing system available';
        return new WP_Error(self::ERROR_FATAL, 'No mailing system available');
    }

    public function send_batch_with_stats($messages) {
        $this->delta = microtime(true);
        $r = $this->send_batch($messages);
        $this->delta = microtime(true) - $this->delta;
        return $r;
    }

    function get_capability() {
        return (int) (3600 * $this->batch_size / $this->delta);
    }

    /**
     *
     * @param TNP_Mailer_Message[] $messages
     * @return bool|WP_Error
     */
    public function send_batch($messages) {

        // We should not get there is the batch size is one, the caller should use "send()". We can get
        // there if the array of messages counts to one, since could be the last of a series of chunks.
        if ($this->batch_size == 1 || count($messages) == 1) {
            $last_result = true;
            foreach ($messages as $message) {
                $r = $this->send($message);
                if (is_wp_error($r)) {
                    $last_result = $r;
                }
            }
            return $last_result;
        }

        // We should always get there
        if (count($messages) <= $this->batch_size) {
            return $this->send_chunk($messages);
        }

        // We should not get here, since it is not optimized
        $chunks = array_chunk($message, $this->batch_size);
        $last_result = true;
        foreach ($chunks as $chunk) {
            $r = $this->send_chunk($chunk);
            if (is_wp_error($r)) {
                $last_result = $r;
            }
        }
        return $last_result;
    }

    /**
     * This one should be implemented by specilized classes.
     *
     * @param TNP_Mailer_Message[] $messages
     * @return bool|WP_Error
     */
    protected function send_chunk($messages) {
        $last_result = true;
        foreach ($messages as $message) {
            $r = $this->send($message);
            if (is_wp_error($r)) {
                $last_result = $r;
            }
        }
        return $last_result;
    }

    /**
     * @return NewsletterLogger
     */
    function get_logger() {
        if ($this->logger) {
            return $this->logger;
        }
        $this->logger = new NewsletterLogger($this->name . '-mailer');
        return $this->logger;
    }

    /**
     * Original mail function simulation for compatibility.
     * @deprecated
     *
     * @param string $to
     * @param string $subject
     * @param array $message
     * @param array $headers
     * @param bool $enqueue
     * @param type $from Actually ignored
     * @return type
     */
    public function mail($to, $subject, $message, $headers = null, $enqueue = false, $from = false) {
        $mailer_message = new TNP_Mailer_Message();
        $mailer_message->to = $to;
        $mailer_message->subject = $subject;
        $mailer_message->headers = $headers;
        $mailer_message->body = $message['html'];
        $mailer_message->body_text = $message['text'];

        return !is_wp_error($this->send($mailer_message));
    }

    /**
     * Used by bounce detection.
     *
     * @param int $time
     */
    function save_last_run($time) {
        update_option($this->prefix . '_last_run', $time);
    }

    /**
     * Used by bounce detection.
     *
     * @param int $time
     */
    function get_last_run() {
        return (int) get_option($this->prefix . '_last_run', 0);
    }
}

/**
 * Standard Mailer which uses the wp_mail() function of WP.
 */
class NewsletterDefaultMailer extends NewsletterMailer {

    var $filter_active = false;

    /** @var WP_Error */
    var $last_error = null;

    /**
     * Static to be accessed in the hook: on some installation the object $this is not working, we're still trying to understand why
     * @var TNP_Mailer_Message
     */
    var $current_message = null;

    function __construct() {
        parent::__construct('default');
        add_action('wp_mail_failed', [$this, 'hook_wp_mail_failed']);
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    }

    function hook_wp_mail_failed($error) {
        $this->last_error = $error;
    }

    function get_description() {
        // TODO: check if overloaded
        return ' WordPress wp_mail() function';
    }

    function get_speed() {
        return (int) Newsletter::instance()->options['scheduler_max'];
    }

    /**
     *
     * @param PHPMailer $mailer
     * @return
     */
    function fix_mailer($mailer) {

        // If there is not a current message, wp_mail() was not called by us
        if (is_null($this->current_message)) {
            return;
        }

        $newsletter = Newsletter::instance();
        if (isset($this->current_message->encoding)) {
            $mailer->Encoding = $this->current_message->encoding;
        } else {
            $encoding = $newsletter->get_main_option('content_transfer_encoding');
            if (!empty($encoding)) {
                $mailer->Encoding = $encoding;
            } else {
                //$mailer->Encoding = 'base64';
            }
        }

        /* @var $mailer PHPMailer */
        $mailer->Sender = $newsletter->get_main_option('return_path');

        // If there is an HTML body AND a text body, add the text part.
        if (!empty($this->current_message->body) && !empty($this->current_message->body_text)) {
            $mailer->AltBody = $this->current_message->body_text;
        }

        $mailer->XMailer = false;

        return $mailer; // It's not a filter...
    }

    /**
     *
     * @param TNP_Mailer_Message $message
     * @return \WP_Error|boolean
     */
    function send($message) {

        $logger = $this->get_logger();

        if (!$this->filter_active) {
            add_action('phpmailer_init', array($this, 'fix_mailer'), 100);
            $this->filter_active = true;
        }

        $newsletter = Newsletter::instance();
        $wp_mail_headers = [];
        if (empty($message->from)) {
            $message->from = $newsletter->get_sender_email();
        }

        if (empty($message->from_name)) {
            $message->from_name = $newsletter->get_sender_name();
        }

        $wp_mail_headers[] = 'From: "' . $message->from_name . '" <' . $message->from . '>';

        $reply_to = $newsletter->get_reply_to();
        if (!empty($reply_to)) {
            $wp_mail_headers[] = 'Reply-To: ' . $reply_to;
        }

        // Manage from and from name

        if (!empty($message->headers)) {
            foreach ($message->headers as $key => $value) {
                $wp_mail_headers[] = $key . ': ' . $value;
            }
        }

        if (!empty($message->body)) {
            $wp_mail_headers[] = 'Content-Type: text/html;charset=UTF-8';
            $body = $message->body;
        } elseif (!empty($message->body_text)) {
            $wp_mail_headers[] = 'Content-Type: text/plain;charset=UTF-8';
            $body = $message->body_text;
        } else {
            $message->error = 'Empty body';
            return new WP_Error(self::ERROR_GENERIC, 'Message format');
        }

        $this->last_error = null;

        $this->current_message = $message;

        // To avoid to show errors/warnings by code executed before
        error_clear_last();

        $r = wp_mail($message->to, $message->subject, $body, $wp_mail_headers);

        $this->current_message = null;

        if (!$r) {
            if ($this->last_error && is_wp_error($this->last_error)) {
                $error_message = $this->last_error->get_error_message();

                // Still not used
                $error_data = $this->last_error->get_error_data();
                $error_code = '';
                if (isset($mail_data['phpmailer_exception_code'])) {
                    $error_code = $mail_data['phpmailer_exception_code'];
                }

                if (stripos($error_message, 'Could not instantiate mail function') || stripos($error_message, 'Failed to connect to mailserver')) {
                    return new WP_Error(self::ERROR_FATAL, $error_message);
                } else {
                    return new WP_Error(self::ERROR_GENERIC, $error_message);
                }
            }

            // This code should be removed when sure...
            $last_error = error_get_last();
            if (is_array($last_error)) {
                $message->error = $last_error['message'];

                if (stripos($message->error, 'Could not instantiate mail function') || stripos($message->error, 'Failed to connect to mailserver')) {
                    return new WP_Error(self::ERROR_FATAL, $last_error['message']);
                } else {
                    return new WP_Error(self::ERROR_GENERIC, $last_error['message']);
                }
            } else {
                $message->error = 'No error explanation reported';
                return new WP_Error(self::ERROR_GENERIC, 'No error message reported');
            }
        }
        return true;
    }
}
