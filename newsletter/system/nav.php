<?php
/** @var array $system_warnings */
?>
<ul class="tnp-nav">
    <li class="tnp-nav-title"><?php esc_html_e('System', 'newsletter') ?></li>
    <li>
        <?php if ($system_warnings['status']) { ?>
            <i class="fas fa-exclamation-triangle tnp-menu-warning" style="color: red;"></i>
        <?php } ?>
        <a href="?page=newsletter_system_status">General</a>
    </li>
    <li><a href="?page=newsletter_system_delivery">Sending</a></li>
    <li>
        <?php if ($system_warnings['scheduler']) { ?>
            <i class="fas fa-exclamation-triangle tnp-menu-warning" style="color: red;"></i>
        <?php } ?>
        <a href="?page=newsletter_system_scheduler">Scheduler</a>
    </li>
    <li><a href="?page=newsletter_system_logs">Logs</a></li>
    <?php if (NEWSLETTER_DEBUG) { ?>
        <li><a href="?page=newsletter_system_backup">Settings backup</a></li>
    <?php } ?>
    <li><a href="<?php echo admin_url('site-health.php') ?>" target="_tab">WP Site Health</a></li>
</ul>
