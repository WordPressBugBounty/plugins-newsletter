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

    $email = \Newsletter::instance()->get_email($form->activation_email_id);
    if (!$email) {
        $email = [];
        $email['type'] = 'activation';
        $email['editor'] = \NewsletterEmails::EDITOR_COMPOSER;
        $email['message'] = '';
        $email['track'] = \Newsletter::instance()->get_option('track');
        $email['subject'] = 'Confirm your subscription';
        $email['status'] = 'sent';
        $email = \NewsletterEmails::instance()->save_email($email);
        $form->activation_email_id = $email->id;
        $this->save_form($form);
    }
    $email = \Newsletter::instance()->get_email($form->activation_email_id);
    \TNP_Composer::prepare_controls($controls, $email);
    $controls->data['activation_email'] = $form->activation_email;
} else {
    if ($controls->is_action('save')) {
        $form->activation_email = $controls->data['activation_email'];
        $this->save_form($form);
        $email = \Newsletter::instance()->get_email($form->activation_email_id);
        \TNP_Composer::update_email($email, $controls);
        if (empty($email->subject)) {
            $email->subject = 'Confirm your subscription';
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
        jQuery('#options-activation_email').on('change', function () {
            if (this.value === '1') {
                jQuery('#tnp-composer-container').show();
            } else {
                jQuery('#tnp-composer-container').hide();
            }
        });
        if (document.getElementById('options-activation_email').value === '1') {
            jQuery('#tnp-composer-container').show();
        } else {
            jQuery('#tnp-composer-container').hide();
        }
    });
</script>
<div class="wrap" id="tnp-wrap">
    <?php include NEWSLETTER_ADMIN_HEADER; ?>
    <div id="tnp-heading">

        <h2><?php echo esc_html($form->name) ?></h2>
        <?php include __DIR__ . '/nav.php' ?>

    </div>

    <div id="tnp-body" class="tnp-automated-edit">


        <?php $controls->show(); ?>





        <form method="post" id="tnpc-form" action="" onsubmit="tnpc_save(this); return true;">
            <?php $controls->init(); ?>

            <p>
                <?php $controls->button_save() ?>
                <?php $controls->select('activation_email', ['' => 'Use the default activation email', '1' => 'Use this custom activation email', '2' => 'Do not send']); ?>
                <?php $controls->button_icon_statistics(\NewsletterStatisticsAdmin::instance()->get_statistics_url($form->activation_email_id), ['secondary' => true]) ?>
            </p>
            <?php $controls->composer_fields_v2() ?>

        </form>

        <div id="tnp-composer-container">
            <p style="font-weight: bold">
                Attention! A button with URL set with the value {subscription_confirm_url} is required.
            </p>
            <?php $controls->composer_load_v2(true, false) ?>
        </div>

    </div>
</div>

