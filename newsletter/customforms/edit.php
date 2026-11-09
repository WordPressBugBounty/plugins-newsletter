<?php
/** @var NewsletterCustomFormsAdmin $this */
defined('ABSPATH') || exit;

$form = $this->get_form((int) $_GET['id']);

if (!$form) {
    die('Invalid form ID');
}

require_once NEWSLETTER_INCLUDES_DIR . '/controls.php';
$controls = new \NewsletterControls();

if ($controls->is_action('switch')) {
    $data = ['id' => $form->id, 'editor' => 1];
    $this->save_form($data);
    $controls->js_redirect('admin.php?page=newsletter_customforms_edit&id=' . $form->id);
}

if ($controls->is_action('save')) {
    $controls->data['id'] = $form->id;
    //$controls->data['content'] = $this->kses($controls->data['content']);
    $controls->data['name'] = wp_strip_all_tags($controls->data['name']);
    $form = $this->save_form($controls->data);
    $controls->add_toast_saved();
}

if ($controls->is_action('reset')) {
    $controls->data['id'] = $form->id;
    $content = file_get_contents(__DIR__ . '/form.html');
    $controls->data['content'] = $content;
    $form = $this->save_form($controls->data);
    $controls->add_toast_saved();
}

if ($controls->is_action('delete')) {
    $this->delete_form($form->id);
    $controls->js_redirect('admin.php?page=newsletter_customforms_index');
    return;
}

if (!$controls->is_action()) {
    $controls->set_data($form);
}
?>

<?php include NEWSLETTER_INCLUDES_DIR . '/codemirror.php'; ?>
<style>
    #tnp-code-editor {
        max-width: 500px;
    }
    .CodeMirror {
        border: 1px solid #ddd;
    }
    .CodeMirror-scroll {
    }
</style>

<script>
    var editor;
    jQuery(function () {
        editor = CodeMirror.fromTextArea(document.getElementById("options-content"), {
            lineNumbers: true,
            mode: 'css',
            extraKeys: {"Ctrl-Space": "autocomplete"}
        });

        document.getElementById('tnp-code-preview').srcdoc = document.getElementById('options-content').value;
    });

    var tnp_ai_nonce = '<?= esc_js(wp_create_nonce('tnp-ai')) ?>';
    var tnp_ai_generating = false;
    function tnp_ai_generate(button) {
        if (tnp_ai_generating)
            return;
        tnp_ai_generating = true;
        button.innerHTML = '...';
        button.disabled = true;
        editor.save();
        aprompt = document.getElementById('prompt').value;
        html = editor.getValue(); //document.getElementById('options-content').value;
        jQuery.post(ajaxurl, {
            prompt: aprompt,
            html: html,
            action: 'newsletter_customforms_prompt',
            _ajax_nonce: tnp_ai_nonce
        }).done(function (response) {
            if (!response.success) {
                alert(response.data);
                editor.setValue(response.data.html);
            } else {

                // Success response, populate the editor with the new content
                console.log(response.data.html);
                document.getElementById('options-content').value = response.data.html;
                editor.setValue(response.data.html);
                //document.getElementById('tnp-code-preview').innerHTML = response.data.html;
                document.getElementById('tnp-code-preview').srcdoc = response.data.html;
                document.getElementById('prompt').value = '';
            }
            button.innerHTML = '<i class="fas fa-paper-plane"></i>';
            tnp_ai_generating = false;
            button.disabled = false;
        }).fail(function (response) {
            alert('Failed');

        });
    }
</script>

<div class="wrap tnp-forms tnp-forms-edit" id="tnp-wrap">

    <?php include NEWSLETTER_ADMIN_HEADER; ?>

    <div id="tnp-heading">
        <?php include __DIR__ . '/nav.php' ?>

    </div>

    <div id="tnp-body">
        <h2><?= esc_html($form->name) ?></h2>
        <?php $controls->show(); ?>

        <form method="post" action="" id="tnpc-form">

            <?php $controls->init(); ?>


            <table class="form-table">

                <tr>
                    <th><?php esc_html_e('Name', 'newsletter'); ?></th>
                    <td>
                        <?php $controls->text('name'); ?>
                    </td>
                </tr>


                <tr>
                    <th>Add subscribers to these lists</th>
                    <td><?php $controls->lists() ?></td>
                </tr>

                <tr valign="top">
                    <th><?php esc_html_e('Opt-in', 'newsletter') ?></th>
                    <td>
                        <?php $controls->select('optin', array('' => 'Default', 'double' => 'Double opt-in', 'single' => 'Single opt-in')); ?>
                        <p class="description">
                            Double opt-in asks for subscription confirmation with an activation email.
                        </p>
                    </td>
                </tr>
                <tr valign="top">
                    <th><?php esc_html_e('Welcome page', 'newsletter') ?></th>
                    <td>
                        <?php $controls->page('welcome_page_id', 'Default', '', true); ?>
                        <p class="description">

                        </p>
                    </td>
                </tr>
            </table>


            <h3>Email series</h3>
            <table class="form-table">
                <tr>
                    <th>Add subscribers to these series</th>
                    <td><?php $controls->autoresponders_on_off(); ?></td>
                </tr>

            </table>


            <div class="tnp-buttons">
                <?php $controls->button_save(); ?>
                <?php $controls->button_delete(); ?>
                <?php $controls->button_reset(); ?>
            </div>

        </form>


    </div>

</div>

