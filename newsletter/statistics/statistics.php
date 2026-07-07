<?php

defined('ABSPATH') || exit;

/**
 * Manages the clicks and opens tracking.
 */
class NewsletterStatistics extends NewsletterModule {

    static $instance;

    const SENT_NONE = 0;
    const SENT_READ = 1;
    const SENT_CLICK = 2;

    var $relink_email_id;
    var $relink_user_id;
    var $relink_email_token;
    var $relink_key = '';
    var $relink_url = '';
    var $relink_url_type = '';

    /**
     * @return NewsletterStatistics
     */
    static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct() {
        parent::__construct('statistics');
        add_action('wp_loaded', [$this, 'hook_wp_loaded']);
        add_action('rest_api_init', [$this, 'hook_rest_api_init']);
    }

    function hook_rest_api_init() {
        // Open tracking route
        register_rest_route('tnp', '/o/(?P<email_id>[\d]+)/(?P<user_id>[\d]+)/(?P<signature>.+).gif', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => function ($request) {
                $this->logger->debug('REST open tracking');
                if (!$this->check_signature($request['email_id'] . '/' . $request['user_id'], $request['signature'])) {
                    $this->logger->debug('Invalid open signature');
                    return;
                }

                $this->register_open((int) $request['email_id'], (int) $request['user_id']);
                $this->send_tracking_image();
            },
            'permission_callback' => '__return_true',
        ));

        // Click tracking route
        register_rest_route('tnp', '/l/(?P<email_id>[\d]+)/(?P<user_id>[\d]+)/(?P<url>.+)/(?P<signature>.+)', ['methods' => WP_REST_Server::READABLE,
            'callback' => function ($request) {
                $this->logger->debug('REST link tracking');
                if (!$this->check_signature($request['email_id'] . '/' . $request['user_id'] . '/' . $request['url'], $request['signature'])) {
                    $this->logger->debug('Invalid link signature');
                    die('Invalid signature');
                    return;
                }

                $url = $this->base64url_decode($request['url']);
                $this->logger->debug('URL: ' . $url);
                $this->register_click((int) $request['email_id'], (int) $request['user_id'], $url);
            },
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     *
     * @param int $email_id
     * @param int $user_id
     * @param string $url
     * @return bool
     */
    function register_click($email_id, $user_id, $url) {

        $email = $this->get_email($email_id);
        $user = $this->get_user($user_id);

        if ($email && $user) {
            $this->logger->debug('Valid user and email');
            $this->set_user_cookie($user);

            $is_action = strpos($url, '?na=') !== false || strpos($url, '&na=') !== false;

            $ip = $this->process_ip($this->get_remote_ip());

            if ($is_action) {
                // Track a Newsletter action as an email open and not a click
                $this->update_open_value(self::SENT_READ, $user_id, $email_id, $ip);
                $this->logger->debug('Click on action link');
            } else {
                $url = apply_filters('newsletter_pre_save_url', $url, $email, $user);
                if ($email) {
                    $this->add_click($url, $user->id, $email->id, $ip);
                    $this->update_open_value(self::SENT_CLICK, $user->id, $email->id, $ip);
                    $this->logger->debug('Click registered');
                }
            }
            $this->update_user_ip($user, $ip);
            $this->update_user_last_activity($user);

            $this->reset_stats_time($email_id);

            header('Location: ' . sanitize_url(apply_filters('newsletter_redirect_url', $url, $email, $user)));
            die();
        } else {
            // Test email and/or test user, deleted email or user.
            // We should possibly block the redirect for missing email or user but it will break sent emails.
            header('Location: ' . sanitize_url($url));
            die();
        }
    }

    /**
     *
     * @param int $email_id
     * @param int $user_id
     * @return bool
     */
    function register_open($email_id, $user_id) {

        $email = $this->get_email($email_id);
        if (!$email) {
            $this->logger->debug('Invalid email ID: ' . $email_id);
            return false;
        }

        $user = $this->get_user($user_id);
        if (!$user) {
            $this->logger->debug('Invalid user ID: ' . $user_id);
            return false;
        }

        $this->logger->debug('Registering');

        $ip = $this->process_ip($this->get_remote_ip());

        $this->add_click('', $user_id, $email_id, $ip);
        $this->update_open_value(self::SENT_READ, $user_id, $email_id, $ip);
        $this->reset_stats_time($email_id);

        $this->update_user_last_activity($user);
        return true;
    }

    function send_tracking_image() {
        header('Content-Type: image/gif', true);
        echo base64_decode('_R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        die();
    }

    function hook_wp_loaded() {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            add_action('wp_ajax_tnptr', [$this, 'tracking']);
            add_action('wp_ajax_nopriv_tnptr', [$this, 'tracking']);
            return;
        }

        $this->tracking();
    }

    function get_key() {
        return $this->get_main_option('key');
    }

    function tracking() {

        if (isset($_GET['nltr'])) {

            // Patch for links with ;
            $parts = explode(';', base64_decode($_GET['nltr']));
            $email_id = (int) array_shift($parts);
            $user_id = (int) array_shift($parts);
            $signature = array_pop($parts);
            $anchor = array_pop($parts); // No more used
            // The remaining elements are the url splitted when it contains ";"
            $url = implode(';', $parts);

            if (empty($url)) {
                $this->dienow('Invalid link', 'The tracking link contains invalid data (missing subscriber or original URL)', 404);
            }

            $host = parse_url($url, PHP_URL_HOST);
            $blog_host = parse_url(home_url(), PHP_URL_HOST);

            $verified = $signature == md5($email_id . ';' . $user_id . ';' . $url . ';' . $anchor . $this->get_main_option('key'));

            // For matching hosts the redirect is safe even without the signature
            if ($host !== $blog_host) {
                // Protection against open-redirect
                if (!$verified) {
                    $this->dienow('Invalid link', 'The link signature (which grants a valid redirection and protects from open redirect attacks) is not valid.', 404);
                }
            }

            // Test emails, anyway the link was signed
            if (empty($email_id) || empty($user_id)) {
                header('Location: ' . esc_url_raw($url));
                die();
            }

            if ($user_id) {
                $user = $this->get_user($user_id);
                if (!$user) {
                    $this->dienow(__('Subscriber not found', 'newsletter'), 'This tracking link contains a reference to a subscriber no more present', 404);
                } else {
                    if ($verified) {
                        $this->set_user_cookie($user);
                    }
                }
            }

            $email = $this->get_email($email_id);
            if (!$email) {
                $this->dienow('Invalid newsletter', 'The link originates from a newsletter not found (it could have been deleted)', 404);
            }

            // Check for bots

            setcookie('tnpe', $email->id . '-' . $email->token, time() + 60 * 60 * 24 * 365, '/');

            // Quick fix to fix
            $is_action = strpos($url, '?na=');
            if (!$is_action) {
                $is_action = strpos($url, '&na=');
            }

            $ip = $this->get_remote_ip();
            $ip = $this->process_ip($ip);

            if ($verified) {
                if (!$is_action) {
                    $url = apply_filters('newsletter_pre_save_url', $url, $email, $user);
                    $this->add_click($url, $user_id, $email_id, $ip);
                    $this->update_open_value(self::SENT_CLICK, $user_id, $email_id, $ip);
                } else {
                    // Track a Newsletter action as an email read and not a click
                    $this->update_open_value(self::SENT_READ, $user_id, $email_id, $ip);
                }
                $this->update_user_ip($user, $ip);
                $this->update_user_last_activity($user);
            }

            $this->reset_stats_time($email_id);

            header('Location: ' . apply_filters('newsletter_redirect_url', $url, $email, $user));
            die();
        }


        if (isset($_GET['noti'])) {

            $this->logger->debug('Default open tracking: ' . $_GET['noti']);

            list($email_id, $user_id, $signature) = explode(';', base64_decode($_GET['noti']), 3);

            $email = $this->get_email($email_id);
            if (!$email) {
                $this->logger->error('Open tracking request for unexistant email');
                die();
            }

            if ($email->token) {
                //$this->logger->debug('Signature: ' . $signature);
                $s = md5($email_id . $user_id . $email->token);
                if ($s != $signature) {
                    $this->logger->error('Open tracking request with wrong signature. Email token: ' . $email->token);
                    die();
                }
            } else {
                $this->logger->info('Email with no token hence not signature to check');
            }

            $this->register_open($email_id, $user_id);
            $this->send_tracking_image();
        }
    }

    /**
     * Reset the timestamp which indicates the specific email stats must be recalculated.
     *
     * @global wpdb $wpdb
     * @param int $email_id
     */
    function reset_stats_time($email_id) {
        global $wpdb;
        if (!$email_id) {
            return;
        }
        $wpdb->update(NEWSLETTER_EMAILS_TABLE, ['stats_time' => 0], ['id' => $email_id]);
    }

    function relink($text, $email_id, $user_id, $email_token = '') {
        $this->relink_email_id = $email_id;
        $this->relink_user_id = $user_id;
        $this->relink_email_token = $email_token;
        $this->relink_url_type = Newsletter::instance()->get_main_option('tracking_links') ?? '';
        if (!$this->relink_url_type) {
            $this->relink_url_type = Newsletter::instance()->get_main_option('links') ?? '';
        }

        if (empty($this->relink_key)) {
            if (defined('NEWSLETTER_RELINK_KEY')) {
                $this->relink_key = NEWSLETTER_RELINK_KEY;
            } else {
                $this->relink_key = $this->get_main_option('key');
            }
        }

        if ($this->relink_url_type === 'ajax') {
            $this->relink_url = admin_url('admin-ajax.php?action=tnptr&nltr=');
        } else {
            $this->relink_url = home_url('/') . '?nltr=';
        }

        $text = preg_replace_callback('/(<[aA][^>]+href[\s]*=[\s]*["\'])([^>"\']+)(["\'][^>]*>)(.*?)(<\/[Aa]>)/is', [$this, 'relink_callback'], $text);

        // Open tracking image
        $signature = md5($email_id . $user_id . $email_token);

        if ($this->relink_url_type === 'ajax') {
            $url = admin_url('admin-ajax.php?action=tnptr&noti=') . rawurlencode(base64_encode($email_id . ';' . $user_id . ';' . $signature));
        } else {
            $url = home_url('/') . '?noti=' . rawurlencode(base64_encode($email_id . ';' . $user_id . ';' . $signature));
        }

        $img1 = '<img alt="" src="' . esc_attr($url) . '"/>';

        // New REST tracking (always added)
        $signature = $this->get_signature($email_id . '/' . $user_id);
        $src = rest_url('tnp/o/' . $email_id . '/' . $user_id . '/' . $signature . '.gif');
        $img2 = '<img alt="Separator" src="' . esc_attr($src) . '">';

        $text = str_replace('</body>', "\n" . $img1 . "\n" . $img2 . "\n" . '</body>', $text);
        return $text;
    }

    function relink_callback($matches) {
        $href = trim(str_replace('&amp;', '&', $matches[2]));

        //$this->logger->debug('Relink ' . $href);
        // Do not replace URL which are tags (special case for ElasticEmail)
        if (strpos($href, '{') === 0) {
            return $matches[0];
        }

        // Do not relink anchors
        if (substr($href, 0, 1) === '#') {
            return $matches[0];
        }
        // Do not relink mailto:
        if (substr($href, 0, 7) === 'mailto:') {
            return $matches[0];
        }

        if ($this->relink_url_type === 'rest') {
            $encoded_href = $this->base64url_encode($href);
            $uri = $this->relink_email_id . '/' . $this->relink_user_id . '/' . $encoded_href;
            $signature = $this->get_signature($uri);
            $url = rest_url('/tnp/l/' . $uri . '/' . $signature);
            return $matches[1] . $url . $matches[3] . $matches[4] . $matches[5];
        }


        $anchor = ''; // No more used
        $r = $this->relink_email_id . ';' . $this->relink_user_id . ';' . $href . ';' . $anchor;
        $r = $r . ';' . md5($r . $this->relink_key);
        $r = base64_encode($r);
        $r = rawurlencode($r);

        $url = $this->relink_url . $r;

        return $matches[1] . $url . $matches[3] . $matches[4] . $matches[5];
    }

    function update_stats($email) {
        global $wpdb;

        $wpdb->query($wpdb->prepare("update " . NEWSLETTER_SENT_TABLE . " s1 join " . $wpdb->prefix . "newsletter_stats s2 on s1.user_id=s2.user_id and s1.email_id=s2.email_id and s1.email_id=%d set s1.open=1, s1.ip=s2.ip", $email->id));
        $wpdb->query($wpdb->prepare("update " . NEWSLETTER_SENT_TABLE . " s1 join " . $wpdb->prefix . "newsletter_stats s2 on s1.user_id=s2.user_id and s1.email_id=s2.email_id and s2.url<>'' and s1.email_id=%d set s1.open=2, s1.ip=s2.ip", $email->id));
    }

    function reset_stats($email) {
        global $wpdb;
        $email_id = $this->to_int_id($email);
        $this->query("delete from " . NEWSLETTER_SENT_TABLE . " where email_id=" . $email_id);
        $this->query("delete from " . NEWSLETTER_STATS_TABLE . " where email_id=" . $email_id);

        $wpdb->update(NEWSLETTER_EMAILS_TABLE, [
            'sent' => 0,
            'error_count' => 0,
            'unsub_count' => 0,
            'open_count' => 0,
            'click_count' => 0,
            'stats_time' => 0
                ], ['id' => $email->id]);
    }

    function add_click($url, $user_id, $email_id, $ip = null) {
        global $wpdb;
        if (is_null($ip)) {
            $ip = $this->get_remote_ip();
        }

        $ip = $this->process_ip($ip);

        if (strlen($url) > 254) {
            $url = substr($url, 0, 254);
        }

        $this->insert(NEWSLETTER_STATS_TABLE, array(
            'email_id' => $email_id,
            'user_id' => $user_id,
            'url' => $url,
            'ip' => $ip
                )
        );
    }

    function update_open_value($value, $user_id, $email_id, $ip = null) {
        global $wpdb;
        if (is_null($ip)) {
            $ip = $this->get_remote_ip();
        }
        $ip = $this->process_ip($ip);
        $this->query($wpdb->prepare("update " . NEWSLETTER_SENT_TABLE . " set open=%d, ip=%s where email_id=%d and user_id=%d and open<%d limit 1", $value, $ip, $email_id, $user_id, $value));
    }

    /** For compatibility */
    function get_statistics_url($email_id) {
        $page = apply_filters('newsletter_statistics_view', 'newsletter_statistics_view');
        return 'admin.php?page=' . $page . '&amp;id=' . $email_id;
    }

    /** For compatibility */
    function get_index_url() {
        $page = apply_filters('newsletter_statistics_index', 'newsletter_statistics_index');
        return 'admin.php?page=' . $page;
    }

    /**
     * Used by Automated.
     *
     * @deprecated
     *
     * @param type $email_id
     * @return type
     */
    function get_total_count($email_id) {
        $report = $this->get_statistics($email_id);
        return $report->total;
    }

    function get_statistics($email) {
        return NewsletterStatisticsAdmin::instance()->get_statistics($email);
    }

    /**
     * Used by Automated.
     *
     * @deprecated
     *
     * @param type $email_id
     * @return type
     */
    function get_open_count($email_id) {
        $report = $this->get_statistics($email_id);
        return $report->open_count;
    }

    /**
     * Used by Automated.
     *
     * @deprecated
     *
     * @param type $email_id
     * @return type
     */
    function get_click_count($email_id) {
        $report = $this->get_statistics($email_id);
        return $report->click_count;
    }

    function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    function base64url_decode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

NewsletterStatistics::instance();

