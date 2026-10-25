<?php
/** @var NewsletterUsersAdmin $this */
/** @var NewsletterControls $controls */
/** @var NewsletterLogger $logger */
/** @var wpdb $wpdb */
defined('ABSPATH') || exit;

$user = $this->get_user((int) ($_GET['id'] ?? -1));

if (!$user) {
    echo 'Subscriber not found.';
    return;
}
?>

<div class="wrap tnp-users tnp-users-edit" id="tnp-wrap">

    <?php include NEWSLETTER_ADMIN_HEADER; ?>

    <div id="tnp-heading">
        <?php $controls->title_help('/subscribers-and-management/') ?>
        <?php include __DIR__ . '/edit-nav.php' ?>
    </div>

    <div id="tnp-body">
        <h2><?= esc_html($user->email) ?></h2>
        <?php $controls->show(); ?>

        <form method="post" action="">

            <?php $controls->init(); ?>


            <?php
            $logs = $wpdb->get_results($wpdb->prepare("select * from {$wpdb->prefix}newsletter_user_logs where user_id=%d order by id desc limit 1000", $user->id));
            ?>
            <?php if (empty($logs)) { ?>
                <p>No logs available</p>
            <?php } else { ?>
                <p>Only public lists are recorded.</p>
                <table class="widefat" style="width: auto">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?php esc_html_e('Date', 'newsletter'); ?></th>
                            <th><?php esc_html_e('Event', 'newsletter'); ?></th>
                            <th>IP</th>
                            <th><?php esc_html_e('Data', 'newsletter'); ?></th>
                        </tr>

                    <tbody>
                        <?php foreach ($logs as $log) { ?>
                            <?php
                            $data = json_decode($log->data, ARRAY_A);
                            if (isset($data['new']))
                                $data = $data['new'];
                            ?>
                            <tr>
                                <td><?php echo esc_html($log->id) ?></td>
                                <td><?php $controls->echo_date($log->created) ?></td>
                                <td><?php echo esc_html($log->source) ?></td>
                                <td><?php echo esc_html($log->ip) ?></td>
                                <td>
                                    <?php
                                    if (is_array($data)) {
                                        foreach ($data as $key => $value) {
                                            echo esc_html(str_replace('_', ' ', $key)), ': ', esc_html($value), '<br>';
                                        }
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>

                </table>
            <?php } ?>


        </form>
    </div>

    <?php include NEWSLETTER_ADMIN_FOOTER; ?>

</div>
