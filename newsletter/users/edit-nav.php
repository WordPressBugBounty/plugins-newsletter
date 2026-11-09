<?php
/** @var TNP_User $user */
?>
<ul class="tnp-nav">
    <li class="tnp-nav-title"><a href="?page=newsletter_users_index"><i class="fas fa-chevron-left"></i></a></li>
    <li><a href="?page=newsletter_users_edit&id=<?= (int) $user->id ?>"><?php esc_html_e('General', 'newsletter') ?></a></li>
    <li><a href="?page=newsletter_users_logs&id=<?= (int) $user->id ?>"><?php esc_html_e('Logs', 'newsletter') ?></a></li>
    <li><a href="?page=newsletter_users_newsletters&id=<?= (int) $user->id ?>"><?php esc_html_e('Newsletters', 'newsletter') ?></a></li>
    <li><a href="?page=newsletter_users_autoresponders&id=<?= (int) $user->id ?>"><?php esc_html_e('Autoresponders', 'newsletter') ?></a></li>
</ul>
