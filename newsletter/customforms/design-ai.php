<?php
/** @var NewsletterCustomFormsAdmin $this */
/** @var NewsletterControls $controls */
/** @var object $form */
defined('ABSPATH') || exit;

if ($controls->is_action('switch')) {
    $data = ['id' => $form->id, 'editor' => 0];
    $this->save_form($data);
    $controls->js_redirect('admin.php?page=newsletter_customforms_design&id=' . $form->id);
}

if ($controls->is_action('save')) {
    $controls->data['id'] = $form->id;
    $controls->data['content'] = $this->kses($controls->data['content']);
    $form = $this->save_form($controls->data);
    $controls->add_toast_saved();
}

if (!$controls->is_action()) {
    $controls->set_data($form);
}

$builder = wp_ai_client_prompt();
if (!$builder->is_supported_for_text_generation()) {
    $controls->errors = 'An AI connector is required to use this form builder';
}

wp_enqueue_style('newsletter-customforms', plugins_url('style.css', __FILE__), [], NEWSLETTER_VERSION);

wp_enqueue_script('newsletter-customforms', plugins_url('script.js', __FILE__), [], NEWSLETTER_VERSION);
?>

<?php //include NEWSLETTER_INCLUDES_DIR . '/codemirror.php';  ?>


<style>
    /* More vertical space: no WP footer and no extra bottom padding (this page only) */
    #wpfooter {
        display: none;
    }
    #wpbody-content {
        padding-bottom: 0;
    }
    /* Gradient behind title, chat and preview, edge to edge like #tnp-heading */
    #tnp-body {
        margin: -1.2rem -20px 0;
        padding: 1.2rem 20px 10px;
        background: linear-gradient(135deg, #fbeae4, #e7f1fa);
    }
    .tnp-ai-header {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        max-width: 1200px;
    }
    #tnp-body .tnp-ai-header h2 {
        margin: 0;
        font-size: 1.3rem;
        font-weight: 600;
    }
    .tnp-ai-header .warning {
        padding: 4px 10px;
        font-size: .85rem;
        color: var(--tnp-orange-dark);
        background: var(--tnp-orange-lighter);
        border-radius: 999px;
    }
    .tnp-ai-header .warning i {
        margin-right: 4px;
        color: inherit;
    }
    #tnp-ai-chat {
        display: flex;
        gap: 20px;
        max-width: 1200px;
        height: 500px;
        margin: 16px 0;
    }
    #tnp-ai-chat .chat,
    #tnp-ai-chat .preview {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
    }
    #tnp-ai-chat .messages {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        margin-bottom: 12px;
        padding: 12px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 12px;
    }
    #tnp-ai-chat .messages:empty::before {
        content: "Describe the form you want, or attach a screenshot.";
        color: var(--tnp-gray-light);
    }
    #tnp-ai-chat .msg {
        position: relative;
        width: fit-content;
        max-width: 80%;
        margin: 12px 0;
        padding: 8px 12px;
        line-height: 1.5;
        color: var(--tnp-text);
        white-space: pre-wrap;
        word-wrap: break-word;
        border-radius: 12px;
    }
    #tnp-ai-chat .msg-user {
        margin-left: auto;
        background: var(--tnp-gray-lighter);
        border-bottom-right-radius: 4px;
    }
    #tnp-ai-chat .msg-bot,
    #tnp-ai-chat .msg-error {
        margin-left: 40px;
        background: #fff;
        border: 1px solid #e5e5e5;
        border-bottom-left-radius: 4px;
    }
    /* Newsletter logo as the assistant avatar */
    #tnp-ai-chat .msg-bot::before,
    #tnp-ai-chat .msg-error::before {
        content: "";
        position: absolute;
        left: -40px;
        bottom: 0;
        width: 32px;
        height: 32px;
        background: #fff url('<?= esc_url(plugins_url('newsletter') . '/admin/images/logo.png') ?>') center / 20px auto no-repeat;
        border: 1px solid #e5e5e5;
        border-radius: 50%;
    }
    #tnp-ai-chat .msg-error {
        color: var(--tnp-red-dark);
        background: var(--tnp-red-lighter);
        border-color: var(--tnp-red-light);
    }
    #tnp-ai-chat .msg img {
        display: block;
        max-width: 200px;
        max-height: 200px;
        margin-top: 8px;
        border-radius: 6px;
    }
    #tnp-ai-chat .composer {
        padding: 8px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 12px;
    }
    #tnp-ai-chat .composer:focus-within {
        border-color: var(--tnp-gray-light);
    }
    #tnp-ai-chat .composer textarea,
    #tnp-ai-chat .composer textarea:focus {
        width: 100%;
        min-height: 40px;
        max-height: 150px;
        padding: 4px;
        border: none;
        outline: none;
        box-shadow: none;
        background: transparent;
        resize: none;
    }
    #tnp-ai-chat .composer-actions {
        display: flex;
        justify-content: space-between;
    }
    #tnp-ai-chat .composer button {
        width: 32px;
        height: 32px;
        border: none;
        border-radius: 50%;
        background: transparent;
        color: var(--tnp-gray);
        cursor: pointer;
    }
    #tnp-ai-chat .composer button.has-image {
        color: var(--tnp-green-dark);
    }
    #tnp-ai-chat #tnp-chat-send {
        background: var(--tnp-blue);
        color: #fff;
    }
    #tnp-ai-chat #tnp-chat-send:disabled {
        opacity: .5;
        cursor: default;
    }
    #tnp-ai-chat .preview {
        overflow: hidden;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 12px;
    }
    #tnp-ai-chat .preview-title {
        padding: 10px;
        font-size: .75rem;
        letter-spacing: .08em;
        text-transform: uppercase;
        text-align: center;
        color: var(--tnp-gray-light);
        border-bottom: 1px solid var(--tnp-gray-lighter);
    }
    #tnp-ai-chat .preview iframe {
        flex: 1;
        min-height: 0;
        border: none;
    }
    /* Prompts button: like the other buttons, but orange */
    #tnp-body .tnpc-button.tnp-prompts-button,
    #tnp-body .tnpc-button.tnp-prompts-button:visited,
    #tnp-body .tnpc-button.tnp-prompts-button:hover,
    #tnp-body .tnpc-button.tnp-prompts-button:active {
        background-color: var(--tnp-orange);
    }
    #tnp-body .tnp-prompts-button i {
        color: inherit;
    }
    @media (max-width: 782px) {
        #tnp-ai-chat {
            flex-direction: column;
        }
    }
</style>

<script>
    var llmUrl = ajaxurl + '?action=newsletter_customforms_prompt&_ajax_nonce=<?= esc_js(wp_create_nonce('tnp-ai')) ?>';
    var saveUrl = ajaxurl + '?action=newsletter_customforms_save&_ajax_nonce=<?= esc_js(wp_create_nonce('tnp-ai')) ?>&id=<?= esc_js($form->id) ?>';
    var tnpChatImage = null;
    var tnpChatImageMime = null;
    var tnpChatUseHistory = true;
    var tnpChatHistory = [];
    var tnpLastHtml = '';
    var tnpPreviewIframe = null;

//    function tnpChatApprove(html) {
//        var contentField = document.getElementById('options-content');
//        if (contentField) {
//            contentField.value = html;
//        }
//        document.getElementById('tnpc-form').act.value = 'save';
//        document.getElementById('tnpc-form').submit();
//    }

    function tnpChatAddMessage(type, text) {
        var el = document.createElement('div');
        el.className = 'msg msg-' + type;
        if (text) {
            el.textContent = text;
        }
        var box = document.getElementById('tnp-chat-messages');
        box.appendChild(el);
        box.scrollTop = box.scrollHeight;
        return el;
    }

    function tnpChatClearImage() {
        tnpChatImage = null;
        tnpChatImageMime = null;
        document.getElementById('tnp-chat-image').value = '';
        document.getElementById('tnp-chat-attach').classList.remove('has-image');
    }

    function tnpChatHandleImage(input) {
        var file = input.files[0];
        if (!file)
            return;

        if (file.size > 5 * 1024 * 1024) {
            alert('Image must be less than 5MB');
            input.value = '';
            return;
        }

        tnpChatImageMime = file.type;

        var reader = new FileReader();
        reader.onload = function (e) {
            tnpChatImage = e.target.result;
            document.getElementById('tnp-chat-attach').classList.add('has-image');
        };
        reader.readAsDataURL(file);
    }

    function tnpChatSend() {
        var input = document.getElementById('tnp-chat-input');
        var message = input.value.trim();

        if (!message && !tnpChatImage)
            return;

        var userMsg = tnpChatAddMessage('user', message);
        if (tnpChatImage) {
            var img = document.createElement('img');
            img.src = tnpChatImage;
            userMsg.appendChild(img);
        }

        tnpChatHistory.push({message: message, image: tnpChatImage});

        input.value = '';
        input.style.height = '';

        var sendBtn = document.getElementById('tnp-chat-send');
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        var html = tnpLastHtml;
        if (!html) {
            html = document.getElementById('options-content').value;
        }

        var history = [];
        if (tnpChatUseHistory && tnpChatHistory.length > 1) {
            history = tnpChatHistory.slice(0, -1).map(function (item) {
                return item.message || '[image]';
            });
        }

        var body = JSON.stringify({
            history: history,
            message: message,
            image: tnpChatImage,
            image_mime: tnpChatImageMime,
            html: html
        });

        tnpChatClearImage();

        fetch(llmUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: body
        }).then(function (response) {
            return response.json();
        }).then(function (data) {
            if (data.success && data.data && data.data.html) {
                tnpChatAddMessage('bot', data.data.explanation || 'Form updated.');
                tnpLastHtml = data.data.html;
                tnpUpdatePreview(data.data.html);
                document.getElementById('options-content').value = data.data.html;
            } else {
                tnpChatAddMessage('bot', 'Sorry, I could not generate a response.');
            }
        }).catch(function () {
            tnpChatAddMessage('error', 'Error: Could not connect to the server.');
        }).finally(function () {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
        });
    }

    // Centers the form inside the preview, the form CSS comes after and can override it
    var tnpPreviewStyle = '<style>body { margin: 0; min-height: 100vh; padding: 16px; box-sizing: border-box;'
            + ' display: flex; flex-direction: column; justify-content: center; }</style>';

    function tnpUpdatePreview(html) {
        if (tnpPreviewIframe && html) {
            tnpPreviewIframe.srcdoc = tnpPreviewStyle + html;
        }
    }

    function tnpSaveFormViaAjax(event) {
        // Only the save goes via ajax, other actions (like "switch") submit the form normally
        if (event.target.act.value !== 'save') {
            return;
        }
        event.preventDefault();

        // Sent as JSON: some server firewalls block form posts containing HTML
        fetch(saveUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({content: document.getElementById('options-content').value})
        }).then(function (response) {
            return response.json();
        }).then(function (data) {
            if (data.success) {
                TNP.toast('Saved');
            } else {
                alert('Error: Could not save the form.');
            }
        }).catch(function () {
            alert('Error: Could not connect to the server.');
        });
    }

    // Size the chat so the whole page fits the viewport
    function tnpFitHeight() {
        var chat = document.getElementById('tnp-ai-chat');
        var content = document.getElementById('wpbody-content');
        var top = chat.getBoundingClientRect().top + window.scrollY;
        var below = content.getBoundingClientRect().bottom - chat.getBoundingClientRect().bottom;
        chat.style.height = Math.max(300, window.innerHeight - top - below) + 'px';
    }

    jQuery(function () {
        tnpFitHeight();
        window.addEventListener('resize', tnpFitHeight);

        var input = document.getElementById('tnp-chat-input');
        input.addEventListener('input', function () {
            this.style.height = '';
            this.style.height = this.scrollHeight + 'px';
        });

        var contentTextarea = document.getElementById('options-content');
        tnpPreviewIframe = document.getElementById('tnp-preview-iframe');
        tnpUpdatePreview(contentTextarea.value);
        tnpLastHtml = contentTextarea.value;
        if (contentTextarea.value) {
            tnpChatAddMessage('bot', 'Existing form HTML is loaded.');
        }

        document.getElementById('tnpc-form').addEventListener('submit', tnpSaveFormViaAjax);
    });
</script>

<div class="wrap tnp-forms tnp-forms-edit" id="tnp-wrap">

<?php include NEWSLETTER_ADMIN_HEADER; ?>

    <div id="tnp-heading">
<?php include __DIR__ . '/nav.php' ?>

    </div>

    <div id="tnp-body">
        <div class="tnp-ai-header">
            <h2><?= esc_html($form->name) ?></h2>
            <span class="warning"><i class="fas fa-exclamation-triangle"></i>
                The AI generates the html of a subscription form: it can get very creative and go wrong. This is a beta version,
                let's know what is not working for you, thank you. We tested in English, but other languages should work.
                The AI provider used is the one configured on your WP Connectors.
            </span>
        </div>

<?php $controls->show(); ?>

        <form method="post" action="" id="tnpc-form">

<?php $controls->init(); ?>

            <input type="hidden" value="<?= esc_attr($controls->data['content']) ?>" name="options[content]" id="options-content">


            <div id="tnp-ai-chat">
                <div class="chat">
                    <div id="tnp-chat-messages" class="messages"></div>
                    <div class="composer">
                        <textarea id="tnp-chat-input" rows="1" placeholder="Describe your form..." onkeydown="if (event.key === 'Enter' && !event.shiftKey) {
                                    event.preventDefault();
                                    tnpChatSend();
                                }"></textarea>
                        <div class="composer-actions">
                            <input type="file" id="tnp-chat-image" accept="image/*" hidden onchange="tnpChatHandleImage(this)">
                            <button type="button" id="tnp-chat-attach" onclick="document.getElementById('tnp-chat-image').click()" title="Attach image">
                                <i class="fas fa-paperclip"></i>
                            </button>
                            <button type="button" id="tnp-chat-send" onclick="tnpChatSend()" title="Send">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="preview">
                    <div class="preview-title">Preview</div>
                    <iframe id="tnp-preview-iframe"></iframe>
                </div>
            </div>

            <div>
<?php $controls->button_save(); ?>
                <?php $controls->btn('switch', 'Switch to code editor', ['confirm' => true, 'secondary' => true]); ?>
                <a href="#tnp-customforms-prompts-modal" rel="modal:open" class="button-primary tnpc-button tnp-prompts-button"><i class="far fa-lightbulb"></i> Prompts</a>
            </div>

        </form>


    </div>

</div>
<?php
include __DIR__ . '/modals/prompts.php';

