<?php
/* @var $this NewsletterCustomForms */

defined('ABSPATH') || exit;

require_once NEWSLETTER_INCLUDES_DIR . '/controls.php';

$controls = new NewsletterControls();

//$logger = $this->get_admin_logger();

if ($controls->is_action('add')) {
    $s = NewsletterSubscription::instance();
    $content = file_get_contents(__DIR__ . '/form.html');

    $data = ['type' => 0, 'name' => 'New form', 'content' => $content, 'token' => $this->get_token()];
    $data = $this->save_form($data);
    $controls->js_redirect('?page=newsletter_customforms_edit&id=' . $data->id);
}

if ($controls->is_action('delete')) {
    $form_id = (int) $_POST['btn'];
    $this->store->delete($this->table, $form_id);
    $controls->add_message_deleted();
}

if ($controls->is_action('copy')) {
    //$logger->info('Copy of series ' . $_POST['btn']);
    //$this->copy_autoresponder($_POST['btn']);
    //$controls->messages .= __('Series duplicated.', 'newsletter-autoresponder');
}

$forms = $this->get_forms();
?>

<div class="wrap tnp-emails tnp-emails-index" id="tnp-wrap">

    <?php include NEWSLETTER_ADMIN_HEADER; ?>

    <div id="tnp-heading">
        <?php include __DIR__ . '/index-nav.php' ?>

    </div>

    <div id="tnp-body">
        <?php $controls->show(); ?>
        <form method="post" action="">
            <?php $controls->init(); ?>

            <div class="tnp-buttons">
                <?php $controls->button('add', 'New form') ?>
            </div>

            <table class="widefat" style="width: 100%">
                <thead>
                    <tr>
                        <th class="tnp-table-id">Id</th>
                        <th><?php esc_html_e('Name', 'newsletter') ?></th>
                        <th>Shortcode</th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($forms as $form) { ?>
                        <tr>
                            <td><?php echo $form->id; ?></td>
                            <td><?php echo esc_html($form->name) ?></td>

                            <td><code>[newsletter_form id="<?= esc_html($form->id) ?>"]</code></td>
                            <td style="white-space: nowrap">
                                <?php $controls->button_icon_edit('?page=newsletter_customforms_edit&id=' . $form->id); ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </form>
    </div>


</div>
