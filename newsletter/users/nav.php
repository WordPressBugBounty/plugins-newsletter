
<ul class="tnp-nav">
    <li class="tnp-nav-title"><?php esc_html_e('Subscribers', 'newsletter'); ?></li>
    <li><a href="?page=newsletter_users_index"><?php esc_html_e('Manage', 'newsletter') ?></a></li>
    <li><a href="?page=newsletter_profile_index"><?php esc_html_e('Profile page', 'newsletter') ?></a></li>
    <li><a href="?page=newsletter_unsubscription_index"><?php esc_html_e('Unsubscribe', 'newsletter') ?></a></li>
    <li><a href="?page=newsletter_users_statistics"><?php esc_html_e('Statistics', 'newsletter') ?></a></li>
    <li><a href="?page=newsletter_users_massive"><?php esc_html_e('Maintenance', 'newsletter') ?></a></li>
    <?php if (!class_exists('NewsletterImport')) { ?>
        <li><a href="?page=newsletter_users_import"><?php esc_html_e('Import/Export', 'newsletter') ?></a></li>
    <?php } else { ?>
        <li><a href="?page=newsletter_import_index"><?php esc_html_e('Import/Export', 'newsletter') ?></a></li>
    <?php } ?>
</ul>
