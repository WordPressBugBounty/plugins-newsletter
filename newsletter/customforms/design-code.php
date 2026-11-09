<?php
/** @var NewsletterCustomFormsAdmin $this */
/** @var NewsletterControls $controls */
/** @var object $form */
defined('ABSPATH') || exit;

if ($controls->is_action('switch')) {
    $data = ['id' => $form->id, 'editor' => 1];
    $this->save_form($data);
    $controls->js_redirect('admin.php?page=newsletter_customforms_design&id=' . $form->id);
}

if ($controls->is_action('save')) {
    $controls->data['id'] = $form->id;
    $controls->data['content'] = $this->kses($controls->data['content']);
    $form = $this->save_form($controls->data);
    $controls->add_toast_saved();
}

if (!$controls->is_action()) {
    $controls->set_data($form);
}

$render_url = admin_url('admin-ajax.php') . '?action=newsletter_customforms_render&_ajax_nonce=' . wp_create_nonce('tnp') . '&id=' . $form->id;
?>

<?php include NEWSLETTER_INCLUDES_DIR . '/codemirror.php'; ?>
<style>
    #wpfooter {
        display: none;
    }
    #tnp-editor-container {
        display: flex;
        gap: 20px;
        max-width: 1200px;
        height: 500px;
    }
    #tnp-code-editor-container,
    #tnp-code-preview-container {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
    }

    #tnp-code-editor-container textarea {
        height: 100%;
        min-height: 100%;
        width: 100%;
    }
    .CodeMirror {
        border: 1px solid #ddd;
    }
    .CodeMirror-scroll {
    }
</style>

<script>
    var editor;
    var renderUrl = ajaxurl + '?action=newsletter_customforms_render&_ajax_nonce=<?= esc_js(wp_create_nonce('tnp')) ?>&id=<?= $form->id ?>';

    jQuery(function () {
        const textarea = document.getElementById('options-content');

// Capture the original textarea size
        const computedWidth = window.getComputedStyle(textarea).width;
        const computedHeight = window.getComputedStyle(textarea).height;
        editor = CodeMirror.fromTextArea(document.getElementById("options-content"), {
            lineNumbers: true,
            mode: 'css',
            extraKeys: {"Ctrl-Space": "autocomplete"}
        });
        editor.setSize(computedWidth, computedHeight);
    });
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

            <div id="tnp-editor-container">
                <div id="tnp-code-editor-container">
                    <textarea id="options-content" name="options[content]" style="height: 100%;"><?= esc_html($controls->data['content']) ?></textarea>
                </div>
                <div id="tnp-code-preview-container">
                    <iframe id="tnp-code-preview" src="<?= esc_attr($render_url) ?>"style="border: 1px solid #ddd;
                            width: 100%;
                            height: 100%;"></iframe>
                </div>
            </div>


            <div class="tnp-buttons">
                <?php $controls->button_save(); ?>
                <?php $controls->btn('switch', 'Switch to AI editor', ['confirm' => true, 'secondary' => true]); ?>
            </div>

        </form>


    </div>

</div>

