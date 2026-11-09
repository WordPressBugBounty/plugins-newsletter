
<ul class="tnp-nav">
    <li class="tnp-nav-title"><?php esc_html_e('Subscription', 'newsletter') ?></li>
    <!--<li><a href="?page=newsletter_subscription_index">Overview</a></li>-->
    <li><a href="?page=newsletter_subscription_form">Form</a></li>
    <!--<li><a href="?page=newsletter_subscription_sources">Forms</a></li>-->
    <li><a href="?page=newsletter_subscription_options">Settings</a></li>
    <li><a href="?page=newsletter_subscription_confirmation">Confirmation</a></li>
    <li><a href="?page=newsletter_subscription_welcome">Welcome</a></li>
    <?php if (false && class_exists('NewsletterAutoresponder')) { ?>
        <li class="><a href="?page=newsletter_autoresponder_subscription_index">Welcome series</a></li>
    <?php } else { ?>
        <li><a href="?page=newsletter_subscription_autoresponder">Welcome series</a></li>
    <?php } ?>
    <li><a href="?page=newsletter_subscription_antispam">Antispam</a></li>
    <?php if (NEWSLETTER_DEBUG) { ?>
        <li><a href="?page=newsletter_subscription_debug">Debug</a></li>
    <?php } ?>
</ul>
