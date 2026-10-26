<?php
/** @var NewsletterUsersAdmin $this */
/** @var NewsletterControls $controls */
defined('ABSPATH') || exit;

require_once NEWSLETTER_INCLUDES_DIR . '/controls.php';
$controls = new NewsletterControls();

if ($controls->is_action('save')) {
    $email = Newsletter::sanitize_email($controls->data['email']);

    if (!$email) {
        $controls->errors = __('Wrong email address.', 'newsletter');
    } else {
        $u = $this->get_user_by_email($email);
        if ($u) {
            $controls->errors = __('This subscriber already exists.', 'newsletter');
        } else {
            $data = [];
            $data['email'] = $email;
            $data['status'] = 'C';
            $data['sex'] = 'n';

            $user = $this->save_user($data);

            $controls->js_redirect('?page=newsletter_users_edit&id=' . ((int) $user->id));
        }
    }
}
?>

<div class="wrap" id="tnp-wrap">

    <?php include NEWSLETTER_ADMIN_HEADER; ?>

    <div id="tnp-heading">

        <h2><?php esc_html_e('New Subscriber', 'newsletter') ?></h2>

    </div>

    <div id="tnp-body" class="tnp-users tnp-users-new">

        <?php $controls->show(); ?>

        <form method="post" action="">
            <?php $controls->init(); ?>

            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Email', 'newsletter') ?></th>
                    <td>
                        <?php $controls->text_email('email', 60); ?>
                        <?php $controls->button('save', '&raquo;'); ?>

                    </td>
                </tr>
            </table>

        </form>
    </div>

    <?php include NEWSLETTER_ADMIN_FOOTER; ?>

</div>
