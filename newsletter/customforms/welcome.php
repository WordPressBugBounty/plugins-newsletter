<?php

namespace TNP\Forms;

/* @var $this Addon */

defined('ABSPATH') || exit;

require_once NEWSLETTER_INCLUDES_DIR . '/controls.php';
$controls = new \NewsletterControls();

$form = $this->get_form((int) $_GET['id']);

if (!$form) {
    die('Form not found');
}

if (!$controls->is_action()) {

    $email = \Newsletter::instance()->get_email($form->welcome_email_id);
    if (!$email) {
        $email = [];
        $email['type'] = 'welcome';
        $email['editor'] = \NewsletterEmails::EDITOR_COMPOSER;
        $email['message'] = '';
        $email['track'] = \Newsletter::instance()->get_option('track');
        $email['subject'] = 'Welcome';
        $email['status'] = 'sent';
        $email = \NewsletterEmails::instance()->save_email($email);
        $form->welcome_email_id = $email->id;
        $this->save_form($form);
    }
    $email = \Newsletter::instance()->get_email($form->welcome_email_id);
    \TNP_Composer::prepare_controls($controls, $email);
    $controls->data['welcome_email'] = $form->welcome_email;
} else {
    if ($controls->is_action('save')) {
        $form->welcome_email = $controls->data['welcome_email'];
        $this->save_form($form);
        $email = \Newsletter::instance()->get_email($form->welcome_email_id);
        \TNP_Composer::update_email($email, $controls);
        if (empty($email->subject)) {
            $email->subject = 'Welcome';
        }
        $email->track = \Newsletter::instance()->get_option('track');
        $email->status = 'sent';
        $email = \NewsletterEmails::instance()->save_email($email);
        $controls->add_toast_saved();
        \TNP_Composer::prepare_controls($controls, $email);
    }
}
?>
<script>
    var tnp_preset_show = false;
    jQuery(function () {
        jQuery('#options-welcome_email').on('change', function () {
            if (this.value === '1') {
                jQuery('#tnp-builder').show();
            } else {
                jQuery('#tnp-builder').hide();
            }
        });
        if (document.getElementById('options-welcome_email').value === '1') {
            jQuery('#tnp-builder').show();
        } else {
            jQuery('#tnp-builder').hide();
        }
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

        <form method="post" id="tnpc-form" action="" onsubmit="tnpc_save(this); return true;">

            <?php $controls->init(); ?>

            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Which welcome email to use', 'newsletter') ?></th>
                    <td>
                        <?php $controls->select('welcome_email', ['' => 'Use the default welcome email', '1' => 'Use this custom welcome email', '2' => 'Do not send']); ?>
                    </td>
                </tr>
            </table>

            <div class="tnp-buttons">
                <?php $controls->button_save(); ?>
            </div>

            <?php $controls->composer_fields_v2() ?>

        </form>

        <script>
            var tnpSidebarFolded = document.body.classList.contains('folded');
        </script>
        <?php $controls->composer_load_v2(true, false) ?>
        <script>
            // The composer folds the WP sidebar on small screens, keep it as it was
            if (!tnpSidebarFolded) {
                document.body.classList.remove('folded');
            }
        </script>

    </div>

</div>
