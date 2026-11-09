<?php
defined('ABSPATH') || exit;

// {color} is replaced with the value of the color field shown on that card
$tnp_prompts = [
    ['Simple signup', 'Create a simple form with just an email field and a subscribe button.'],
    ['Name and email', 'Create a form with first name, last name and email, plus a privacy policy checkbox.'],
    ['Inline layout', 'Put the email field and the subscribe button side by side on one line, like a footer newsletter bar.'],
    ['Two columns', 'Arrange first name and last name side by side in two columns, with the email below them at full width.'],
    ['Minimal style', 'Restyle the form with a clean, minimal look: thin borders, rounded corners and plenty of spacing.'],
    ['Brand color', 'Use {color} as the main color of the form, for the button and the focused field borders.'],
    ['Dark theme', 'Give the form a dark background with light text, for use on a dark website section.'],
    ['GDPR ready', 'Add the privacy policy consent and the tracking consent checkboxes below the email field.'],
    ['Personalization', 'Add the gender field and all my custom fields after the email.'],
    ['Friendlier copy', 'Add a short heading "Join our newsletter" and change the button text to "Count me in".'],
];
?>
<style>
    #tnp-customforms-prompts-modal {
        max-width: 720px;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, .2);
    }
    #tnp-customforms-prompts-modal h3 {
        margin: 0 0 4px;
        font-size: 1.1rem;
    }
    #tnp-customforms-prompts-modal .intro {
        margin: 0 0 16px;
        color: var(--tnp-gray-light);
    }
    #tnp-prompts {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    #tnp-prompts .tnp-prompt {
        padding: 12px 14px;
        border: 1px solid #e5e5e5;
        border-radius: 10px;
        cursor: pointer;
        transition: border-color .15s, background-color .15s;
    }
    #tnp-prompts .tnp-prompt:hover,
    #tnp-prompts .tnp-prompt:focus {
        outline: none;
        border-color: var(--tnp-blue-light);
        background: var(--tnp-blue-lighter);
    }
    #tnp-prompts .tnp-prompt strong {
        display: block;
        margin-bottom: 4px;
        color: var(--tnp-gray-darker);
    }
    #tnp-prompts .tnp-prompt span {
        font-size: .85rem;
        line-height: 1.4;
        color: var(--tnp-gray);
    }
    #tnp-prompts .tnp-prompt-color {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 8px;
    }
    #tnp-prompts .tnp-prompt-color i {
        width: 20px;
        height: 20px;
        border: 1px solid #ddd;
        border-radius: 50%;
    }
    #tnp-prompts .tnp-prompt-color input {
        width: 90px;
        min-height: 0;
        padding: 2px 8px;
        font-family: monospace;
        border: 1px solid #ddd;
        border-radius: 6px;
    }
    #tnp-prompts .tnp-prompt-color input.invalid {
        border-color: var(--tnp-red-dark);
    }
    @media (max-width: 600px) {
        #tnp-prompts {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- Always keep the wrapper DIV with the class "modal" and it's id -->
<div id="tnp-customforms-prompts-modal" aria-hidden="true" class="modal">
    <h3>Prompt examples</h3>
    <p class="intro">Click an example to send it to the AI.</p>
    <div id="tnp-prompts">
        <?php foreach ($tnp_prompts as $p) { ?>
            <div class="tnp-prompt" role="button" tabindex="0" data-prompt="<?= esc_attr($p[1]) ?>">
                <strong><?= esc_html($p[0]) ?></strong>
                <span><?= esc_html(str_replace('{color}', 'your color', $p[1])) ?></span>
                <?php if (strpos($p[1], '{color}') !== false) { ?>
                    <div class="tnp-prompt-color">
                        <i style="background: #e74c3c"></i>
                        <input type="text" value="#e74c3c" maxlength="7" aria-label="Color (hex)">
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</div>

<script>
    jQuery(function ($) {

        function tnpSendPrompt(card) {
            var prompt = card.dataset.prompt;

            var color = card.querySelector('.tnp-prompt-color input');
            if (color) {
                var hex = color.value.trim();
                if (hex.charAt(0) !== '#') {
                    hex = '#' + hex;
                }
                if (!/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(hex)) {
                    color.classList.add('invalid');
                    color.focus();
                    return;
                }
                prompt = prompt.replace('{color}', hex);
            }

            // A reply is still in progress
            if (document.getElementById('tnp-chat-send').disabled) {
                return;
            }

            document.getElementById('tnp-chat-input').value = prompt;
            $.modal.close();
            tnpChatSend();
        }

        $('#tnp-prompts .tnp-prompt').on('click', function (e) {
            // Clicking the color field is for typing, not sending
            if (e.target.tagName === 'INPUT') {
                return;
            }
            tnpSendPrompt(this);
        }).on('keydown', function (e) {
            if (e.key === 'Enter' || (e.key === ' ' && e.target === this)) {
                e.preventDefault();
                tnpSendPrompt(this);
            }
        });

        $('#tnp-prompts .tnp-prompt-color input').on('input', function () {
            this.classList.remove('invalid');
            var hex = this.value.trim();
            if (hex.charAt(0) !== '#') {
                hex = '#' + hex;
            }
            if (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(hex)) {
                this.previousElementSibling.style.background = hex;
            }
        });
    });
</script>
