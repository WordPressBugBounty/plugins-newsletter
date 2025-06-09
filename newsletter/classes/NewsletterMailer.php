<?php

defined('ABSPATH') || exit;

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