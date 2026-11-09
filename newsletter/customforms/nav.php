<ul class="tnp-nav">
    <li class="tnp-nav-title"><a href="?page=newsletter_customforms_index"><i class="fas fa-chevron-left"></i></a></li>
    <li><a href="?page=newsletter_customforms_edit&id=<?=(int)$form->id?>"><?php esc_html_e('Settings', 'newsletter') ?></a></li>
    <li><a href="?page=newsletter_customforms_design&id=<?=(int)$form->id?>"><?php esc_html_e('Design', 'newsletter') ?></a></li>
    <?php if (NEWSLETTER_DEBUG) { ?>
        <li><a href="?page=newsletter_forms_activation&id=<?php echo rawurlencode($form->id) ?>"><?php esc_html_e('Activation email', 'newsletter') ?></a></li>
    <?php } ?>
    <li><a href="?page=newsletter_customforms_welcome&id=<?=(int)$form->id?>"><?php esc_html_e('Welcome email', 'newsletter') ?></a></li>
</ul>

