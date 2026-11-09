
<?php

defined('ABSPATH') || exit;

class NewsletterCustomFormsAdmin extends NewsletterModuleAdmin {

    static $instance;
    var $table;

    /**
     * @return NewsletterFormsAdmin
     */
    static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct() {
        global $wpdb;

        parent::__construct('customforms');
        $this->table = $wpdb->prefix . 'newsletter_customforms';
        $this->store = NewsletterStore::singleton();
    }

    function wp_loaded() {
        if (defined('DOING_AJAX') && DOING_AJAX && $this->is_allowed()) {
            add_action('wp_ajax_newsletter_customforms_prompt', [$this, 'ajax_prompt']);
            add_action('wp_ajax_newsletter_customforms_render', [$this, 'ajax_render']);
            add_action('wp_ajax_newsletter_customforms_save', [$this, 'ajax_save']);
        }
    }

    function admin_menu() {
        $this->add_admin_page('index', __('Forms', 'newsletter'));
        $this->add_admin_page('settings', __('Settings', 'newsletter'));
        $this->add_admin_page('edit', __('Edit', 'newsletter'));
        $this->add_admin_page('design', __('Designer', 'newsletter'));
        $this->add_admin_page('welcome', __('Welcome', 'newsletter'));
    }

    function kses($text) {
        $allowed = wp_kses_allowed_html('post');
        $allowed['input'] = ['class' => true, 'placeholder' => true, 'type' => true, 'style' => true, 'size' => true, 'dir' => true, 'name' => true, 'required' => true, 'value' => true, 'aria-*' => true];
        $allowed['style'] = ['type' => true];
        $allowed['textarea'] = ['class' => true, 'placeholder' => true, 'dir' => true, 'style' => true, 'name' => true, 'required' => true, 'aria-*' => true];
        $allowed['select'] = ['class' => true, 'dir' => true, 'style' => true, 'name' => true, 'required' => true, 'aria-*' => true];
        $allowed['option'] = ['value' => true, 'selected' => true];
        if (!isset($allowed['button'])) {
            $allowed['button'] = ['class' => true, 'dir' => true, 'name' => true, 'style' => true, 'aria-*' => true];
        }
        return wp_kses($text, $allowed);
    }

    function ajax_save() {
        check_ajax_referer('tnp-ai');

        $id = (int) $_GET['id'];
        $form = $this->get_form($id);
        if (!$form) {
            wp_send_json_error();
        }

        $json = json_decode(file_get_contents('php://input'), true);
        if (!is_array($json) || !isset($json['content'])) {
            wp_send_json_error();
        }

        $data = [];
        $data['id'] = $form->id;
        $data['content'] = $this->kses($json['content']);
        $this->save_form($data);
        wp_send_json_success($data);
    }

    function ajax_render() {
        check_ajax_referer('tnp');
        $form = $this->get_form($_GET['id']);
        if (!$form) {
            echo 'Form not available';
            die();
        }
        echo '<html>';
        echo '<head>';
        echo '<link rel="stylesheet" href="', esc_attr(Newsletter::instance()->plugin_url()), '/style.css">';
        $css = Newsletter::instance()->get_main_option('css');

        if (!empty($css)) {
            echo '<style>';
            echo wp_kses_post($css);
            echo '</style>';
        }

        echo '</head>';
        echo '<body style="background-color: white;">';
        echo NewsletterCustomForms::instance()->render($form);
        echo '</body>';
        echo '</html>';
        die();
    }

    function ajax_prompt() {
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
            $message .= 'Previous requests, already applied to the current form HTML:' . "\n";
            foreach ($history as $item) {
                $message .= '- ' . wp_strip_all_tags($item) . "\n";
            }
            $message .= "\n";
        }
        if (!empty($html)) {
            $message .= 'Current form HTML: """' . $html . '"""' . "\n\n" . 'Use it as starting point for the new modifications.' . "\n\n";
        }
        $message .= 'New change request: ' . $prompt;

        if ($image_data) {
            $message .= "\n\n" . 'Use the provided image as design reference.';
        }

        //$this->logger->debug($message);

        $builder = wp_ai_client_prompt($message);

        if ($image_data) {
            $builder->with_file($image_data, $image_mime);
        }

        $instructions = file_get_contents(__DIR__ . '/instructions.md');
        $instructions = str_replace(['{customfields_max}'], [NEWSLETTER_PROFILE_MAX], $instructions);

        $b = '';
        $cfs = $this->get_customfields_public();
        foreach ($cfs as $cf) {
            $name = str_replace('|', '', $cf->name);
            $b .= '| ' . $name . ' | ';
            switch ($cf->type) {
                case 'select':
                    $b .= '<select name="np' . $cf->id . '"></select>';
                    break;
                case 'text':
                    $b .= '<input type="text" name="np' . $cf->id . '">';
                    break;
                case 'texatarea':
                    $b .= '<textarea name="np' . $cf->id . '"></textarea>';
                    break;
                default:
                    $b .= '<input type="text">';
            }
            $b .= " |\n";
        }


//        $b = '';
//        $cfs = $this->get_customfields_public();
//        foreach ($cfs as $cf) {
//            $b .= 'Custom field ' . $cf->id . '. Label: "' . $cf->name . '". Name: `np' . $cf->id . '`. HTML element: <input type="text">';
//            $b .= "\n";
//        }

        $b .= "\n";
        $colors = $this->get_brand_colors();
        $instructions = str_replace(['{customfields}', '{customfields_max}', '{colors}'], [$b, NEWSLETTER_PROFILE_MAX, $this->get_colors_table($colors)], $instructions);

        if (NEWSLETTER_DEBUG) {
            file_put_contents(__DIR__ . '/instructions-generated.md', $instructions);
        }

        $builder->using_system_instruction($instructions);
        $builder->as_json_response(
                [
                    'type' => 'object',
                    'required' => ['html', 'explanation'],
                    'properties' => [
                        'html' => [
                            'type' => 'string',
                            'description' => 'The HTML generated with the form fields and the CSS style block if necessary'
                        ],
                        'explanation' => [
                            'type' => 'string',
                            'description' => 'Description of what has been done or not done or explanation for requests not related to the form design'
                        ]
                    ],
                    'additionalProperties' => false
                ]
        );

        $result = $builder->generate_text_result();

        if (is_wp_error($result)) {
            wp_send_json_success(['html' => $html, 'explanation' => 'Nothing changed, there was an error: ' . wp_strip_all_tags($result->get_error_message())]);
        }

        $model = $result->getModelMetadata()->getName();

        $reply = $result->toText();
        //$this->logger->debug('Model: ' . $result->getModelMetadata()->getName());
        //$this->logger->debug($reply);

        $data = json_decode($reply);
        if (!is_object($data) || !isset($data->html)) {
            wp_send_json_success(['html' => $html, 'explanation' => 'Nothing changed, the AI returned an invalid answer. Please try again.']);
        }

        $data->html = $this->kses($data->html);
        if (NEWSLETTER_DEBUG) {
            $data->explanation = 'Model: ' . esc_html($model) . '. ' . wp_strip_all_tags($data->explanation ?? '');
        } else {
            $data->explanation = wp_strip_all_tags($data->explanation ?? '');
        }
        wp_send_json_success($data);
    }

    function get_brand_colors() {
        $colors = array();

        // 1. Check Global Styles / theme.json (Modern Block Themes)
        if (function_exists('wp_get_global_settings')) {
            $global_settings = wp_get_global_settings(array('color', 'palette'));

            // wp_get_global_settings can return nested arrays (e.g., 'theme', 'custom', 'default')
            if (!empty($global_settings)) {
                // Flatten the arrays if they are grouped by origin
                $palette_sources = isset($global_settings[0]) ? $global_settings : array_values($global_settings);

                foreach ($palette_sources as $source) {
                    if (is_array($source)) {
                        foreach ($source as $item) {
                            if (isset($item['color']) && strpos($item['color'], 'var') === false) {
                                $slug = $item['slug'] ?? sanitize_title($item['name'] ?? 'color');
                                $colors[$slug] = $item['color'];
                            }
                        }
                    }
                }
            }
        }

        // 2. Check traditional Editor Color Palette (Classic / Hybrid Themes)
        if (empty($colors)) {
            $theme_support = get_theme_support('editor-color-palette');
            if (!empty($theme_support[0]) && is_array($theme_support[0])) {
                foreach ($theme_support[0] as $item) {
                    if (isset($item['color']) && strpos($item['color'], 'var') === false) {
                        $slug = $item['slug'] ?? sanitize_title($item['name'] ?? 'color');
                        $colors[$slug] = $item['color'];
                    }
                }
            }
        }

        // 3. Fallback: Check common Theme Mods (Customizer settings)
        if (empty($colors)) {
            // Many classic themes store primary/accent colors in specific customizer keys
            $common_keys = array('primary_color', 'accent_color', 'theme_color', 'site_color', 'main_color');
            foreach ($common_keys as $key) {
                $mod_color = get_theme_mod($key);
                if ($mod_color && strpos($mod_color, 'var') === false) {
                    $colors[$key] = $mod_color;
                }
            }
        }

        return $colors;
    }

    function get_colors_table($colors) {
        if (empty($colors) || !is_array($colors)) {
            return 'No colors found.';
        }

        $markdown = "| Slug / Key | Color Code |\n";
        $markdown .= "| :--- | :--- |\n";

        foreach ($colors as $slug => $hex) {
            $safe_slug = esc_html($slug);
            $safe_hex = esc_html($hex);

            $markdown .= "| `{$safe_slug}` | `{$safe_hex}` |\n";
        }

        return $markdown;
    }

    function get_forms() {
        $forms = $this->store->get_all($this->table);
        foreach ($forms as $form) {
            $this->deserialize($form);
        }
        return $forms;
    }

    function get_form($id) {
        $form = $this->store->get_single($this->table, $id);
        if ($form) {
            $this->deserialize($form);
        }
        return $form;
    }

    function deserialize($form) {
        $form->autoresponders = wp_parse_list($form->autoresponders ?? []);
        $form->lists = wp_parse_list($form->lists ?? []);
    }

    function save_form($form) {
        $data = (array) $form;

        if (isset($data['name'])) {
            $data['name'] = wp_strip_all_tags($data['name']);
        }

        if (!empty($data['autoresponders'])) {
            $data['autoresponders'] = implode(',', $data['autoresponders']);
        }
        if (!empty($data['lists'])) {
            $data['lists'] = implode(',', $data['lists']);
        }

        if (isset($data['optin']) && !in_array($data['optin'], ['', 'single', 'double'], true)) {
            $data['optin'] = '';
        }

        if (isset($data['welcome_page_id'])) {
            $data['welcome_page_id'] = (int) $data['welcome_page_id'];
        }

        $data = $this->store->save($this->table, $data);
        return $this->get_form($data->id);
    }

    function save_form_content($form) {
        $data = ['id' => $form->id, 'content' => $form->content];
        $data = $this->store->save($this->table, $data);
        return $this->get_form($data->id);
    }

    function delete_form($id) {
        $this->store->delete($this->table, $id);
    }
}
