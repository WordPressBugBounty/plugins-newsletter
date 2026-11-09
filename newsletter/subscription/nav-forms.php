
<ul class="tnp-nav">
    <li class="tnp-nav-title"><?php esc_html_e('Forms', 'newsletter') ?></li>
    <li><a href="?page=newsletter_subscription_sources">All</a></li>
    <li><a href="?page=newsletter_subscription_form">Standard</a></li>
    <li><a href="?page=newsletter_customforms_index">Custom</a></li>
    <li><a href="?page=newsletter_subscription_inject">Inside posts</a></li>
    <li><a href="?page=newsletter_subscription_popup">Popup</a></li>
    <li><a href="?page=newsletter_subscription_shortcodes">Shortcodes and Widgets</a></li>
    <li><a href="?page=newsletter_subscription_forms">HTML Forms (old)</a></li>
    <?php if (class_exists('NewsletterLeads')) { ?>
    <li><a href="?page=newsletter_leads_index">Leads Addon</a></li>
    <?php } ?>
</ul>
