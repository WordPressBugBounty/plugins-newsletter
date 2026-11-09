<?php
/** @var NewsletterStatisticsAdmin $this */
/** @var NewsletterControls $controls */
/** @var NewsletterLogger $logger */
defined('ABSPATH') || exit;

$debug = NEWSLETTER_DEBUG || isset($_GET['debug']);

if ($controls->is_action()) {
    if ($controls->is_action('save')) {
        $controls->data = $this->get_main_options();

        $controls->add_toast_saved();
    }
} else {
    $controls->data = $this->get_main_options();
}
?>

<div class="wrap tnp-statistics tnp-statistics-settings" id="tnp-wrap">

    <?php include NEWSLETTER_ADMIN_HEADER; ?>

    <div id="tnp-heading">

        <?php //$controls->title_help('/reports-extension')  ?>
        <?php include __DIR__ . '/index-nav.php' ?>

    </div>

    <div id="tnp-body">

        <?php $controls->show() ?>


        <form method="post" action="">
            <?php $controls->init(); ?>

            <div id="tabs">
                <ul>
                    <li><a href="#tabs-general"><?php esc_html_e('General', 'newsletter') ?></a></li>
                    <?php if ($debug) { ?>
                        <li><a href="#tabs-debug">Debug</a></li>
                    <?php } ?>
                </ul>

                <div id="tabs-general">
                    Still no settings right now, but soon you can select the provider and the model to be used.
                </div>

                <?php if ($debug) { ?>
                    <div id="tabs-debug">
                        <pre><?php echo esc_html(wp_json_encode($this->get_db_options(''), JSON_PRETTY_PRINT)) ?></pre>
                    </div>
                <?php } ?>
            </div>

        </form>

    </div>

    <?php include NEWSLETTER_ADMIN_FOOTER; ?>

</div>
