<?php
/** @var NewsletterCustomFormsAdmin $this */
defined('ABSPATH') || exit;

$form = $this->get_form((int) $_GET['id']);

if (!$form) {
    die('Invalid form ID');
}

require_once NEWSLETTER_INCLUDES_DIR . '/controls.php';
$controls = new \NewsletterControls();

switch ($form->editor) {
    case 0:
        require __DIR__ . '/design-code.php';
        break;
    case 1:
        require __DIR__ . '/design-ai.php';
        break;
    default:
        echo 'Ops.';
}
