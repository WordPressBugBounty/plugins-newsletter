// add delete buttons
jQuery.fn.add_delete = function () {
    this.append('<div class="tnpc-row-delete" title="Delete"><img src="' + TNP_PLUGIN_URL + '/emails/tnp-composer/_assets/delete.png" width="32"></div>');
    this.find('.tnpc-row-delete').perform_delete();
};

// delete row
jQuery.fn.perform_delete = function () {
    this.click(function (e) {
        e.preventDefault();
        e.stopPropagation();
        tnpc_hide_block_options();
        // remove block
        jQuery(this).parent().remove();
    });
}

// add edit button
jQuery.fn.add_block_edit = function () {
    this.append('<div class="tnpc-row-edit-block" title="Edit"><img src="' + TNP_PLUGIN_URL + '/emails/tnp-composer/_assets/edit.png" width="32"></div>');
    this.find('.tnpc-row-edit-block').perform_block_edit();
}

// edit block
jQuery.fn.perform_block_edit = function () {

    this.click(function (e) {

        e.preventDefault();
        e.stopPropagation();

        target = jQuery(this).parent().find('.edit-block');

        // The row container which is a global variable and used later after the options save
        tnp_container = jQuery(this).closest("table");

        if (tnp_container.hasClass('tnpc-row-block')) {

            tnpc_show_block_options();

            var options = tnp_container.find(".tnpc-block-content").attr("data-json");

            // Compatibility
            if (!options) {
                options = target.attr("data-options");
            }

            var data = {
                action: "tnpc_block_form",
                id: tnp_container.data("id"),
                context_type: tnp_context_type,
                options: options
            };

            tnpc_add_global_options(data);

            builderAreaHelper.lock();
            jQuery.post(ajaxurl, data, function (response) {
                // Store the original values for the "cancel" action
                start_options = jQuery("#tnpc-block-options-form :input").serializeArray();
                tnpc_add_global_options(start_options); // ???
                builderAreaHelper.unlock();
                jQuery("#tnpc-block-options-form").html(response.form);
                jQuery("#tnpc-block-options-title").html(response.title);
            });

        } else {
            alert("This is deprecated block version and cannot be edited. Please replace it with a new one.");
        }

    });

};

// add clone button
jQuery.fn.add_block_clone = function () {
    this.append('<div class="tnpc-row-clone" title="Clone"><img src="' + TNP_PLUGIN_URL + '/emails/tnp-composer/_assets/copy.png" width="32"></div>');
    this.find('.tnpc-row-clone').perform_clone();
}

// clone block
jQuery.fn.perform_clone = function () {

    this.click(function (e) {

        e.preventDefault();
        e.stopPropagation();

        // hide block edit form
        tnpc_hide_block_options();

        // find the row
        let row = jQuery(this).closest('.tnpc-row');

        // clone the block
        let new_row = row.clone();
        new_row.find(".tnpc-row-delete").remove();
        new_row.find(".tnpc-row-edit-block").remove();
        new_row.find(".tnpc-row-clone").remove();

        new_row.add_delete();
        new_row.add_block_edit();
        new_row.add_block_clone();
        // if (new_row.hasClass('tnpc-row-block')) {
        //     new_row.find(".tnpc-row-edit-block i").click();
        // }
        new_row.insertAfter(row);
    });
};

let start_options = null;
let tnp_container = null;

jQuery(function () {

    NewsletterComposer.init();

    // open blocks tab
    document.getElementById("defaultOpen").click();

    // preload content from a body named input
    var preloadedContent = jQuery('#options-message').val();

    if (!preloadedContent && tnp_preset_show) {
        tnpc_show_presets_modal();
    } else {
        jQuery('#tnpb-content').html(preloadedContent);
        start_composer();
    }

});

const NewsletterComposer = {
    init: function () {

    },

    // The appearance of the builder when the global settings are changed (new template, ...)
    refresh_style: function () {
        jQuery('#tnpb-content').css('background-color', document.getElementById('options-options_composer_background').value);
        let padding = document.getElementById('options-options_composer_padding').value;
        if (padding !== '') {
            padding = parseInt(padding);
        } else {
            padding = 0;
        }
        let style = document.getElementById('tnp-backend-css');
        style.innerText = '#tnpb-content.tnp-view-mobile { padding-left: ' + padding + 'px; padding-right: ' + padding + 'px; }';
    },

    load_template: function (id, ev) {
        ev.stopPropagation();
        jQuery.ajax({
            type: "POST",
            url: ajaxurl,
            data: {
                action: "tnpc_get_preset",
                id: id
            },
            success: function (res) {
                NewsletterComposer.set_content(res.data.content);
                NewsletterComposer.set_options(res.data.globalOptions);
                NewsletterComposer.set_subject(res.data.subject);

                start_composer();

                jQuery.modal.close();
            },
        });
    },

    set_content: function (content) {
        jQuery('#tnpb-content').html(content ?? '');
    },

    get_content: function () {
        var el = jQuery('#tnpb-content').clone();

        el.find('.tnpc-row-delete').remove();
        el.find('.tnpc-row-edit-block').remove();
        el.find('.tnpc-row-clone').remove();
        el.find('.tnpc-row').removeClass('ui-draggable');
        el.find('#tnpb-sortable-helper').remove();

        return btoa(encodeURIComponent(el.html()));
    },

    set_subject: function (subject) {
        jQuery('#options-subject').val(subject ?? '');
    },

    // Update the Composer options with the new ones, for example when the template is changed
    set_options: function (options) {
        // It's an object
        for (const [key, value] of Object.entries(options)) {
                let el = document.getElementById('options-options_composer_' + key);
                if (el) {
                    el.value = value;
                } else {
                    //console.log('options-options_' + key + ' not found');
                }
        }
        //tnp_controls_init();
    },

    // Add the Composer options to the provided data (object or array) to be sent back, for example, to render a block
    add_options: function (data) {
        let options = jQuery("#tnpb-settings :input").serializeArray();
        for (let i = 0; i < options.length; i++) {
            // options[options_composer_title_font_family] weird, isn't it?
            options[i].name = options[i].name.replace("options[options_composer_", "composer[");
            if (Array.isArray(data)) {
                data.push(globalOptions[i]);
            } else {
                //Inline edit data format is object not array
                data[globalOptions[i].name] = globalOptions[i].value;
            }
        }
    }
}

function BuilderAreaHelper() {

    var _builderAreaEl = document.querySelector('#tnpb-main');
    var _overlayEl = document.createElement('div');
    _overlayEl.style.zIndex = 99999;
    _overlayEl.style.position = 'absolute';
    _overlayEl.style.top = 0;
    _overlayEl.style.left = 0;
    _overlayEl.style.width = '100%';
    _overlayEl.style.height = '100%';

    this.lock = function () {
        _builderAreaEl.appendChild(_overlayEl);
    }

    this.unlock = function () {
        _builderAreaEl.removeChild(_overlayEl);
    }

}

let builderAreaHelper = new BuilderAreaHelper();

function init_builder_area() {

    //Drag & Drop
    jQuery("#tnpb-content").sortable({
        revert: false,
        placeholder: "tnpb-placeholder",
        forcePlaceholderSize: true,
        opacity: 0.6,
        tolerance: "pointer",
        helper: function (e) {
            var helper = jQuery(document.getElementById("tnpb-sortable-helper")).clone();
            return helper;
        },
        update: function (event, ui) {
            if (ui.item.attr("id") === "tnpb-draggable-helper") {
                loading_row = jQuery('<div style="text-align: center; padding: 20px; background-color: #d4d5d6; color: #52BE7F;"><i class="fa fa-cog fa-2x fa-spin" /></div>');
                ui.item.before(loading_row);
                ui.item.remove();
                var data = new Array(
                        {"name": 'action', "value": 'tnpc_render'},
                        {"name": 'id', "value": ui.item.data("id")},
                        {"name": 'b', "value": ui.item.data("id")},
                        {"name": 'full', "value": 1},
                        {"name": "context_type", "value": tnp_context_type},
                        {"name": '_wpnonce', "value": tnp_nonce}
                );

                tnpc_add_global_options(data);

                jQuery.post(ajaxurl, data, function (response) {
                    var new_row = jQuery(response);
//                    ui.item.before(new_row);
//                    ui.item.remove();
                    loading_row.before(new_row);
                    loading_row.remove();
                    new_row.add_delete();
                    new_row.add_block_edit();
                    new_row.add_block_clone();
                    // new_row.find(".tnpc-row-edit").hover_edit();
                    if (new_row.hasClass('tnpc-row-block')) {
                        new_row.find(".tnpc-row-edit-block").click();
                    }
                }).fail(function () {
                    alert("Block rendering failed.");
                    loading_row.remove();
                });
            }
        }
    });

    jQuery(".tnpb-block-icon").draggable({
        connectToSortable: "#tnpb-content",

        // Build the helper for dragging
        helper: function (e) {
            var helper = jQuery(document.getElementById("tnpb-draggable-helper")).clone();
            // Do not uset .data() with jQuery
            helper.attr("data-id", e.currentTarget.dataset.id);
            helper.html(e.currentTarget.dataset.name);
            return helper;
        },
        revert: false,
        start: function () {
            if (jQuery('.tnpc-row').length) {
            } else {
                jQuery('#tnpb-content').append('<div class="tnpc-drop-here">Drag&Drop blocks here!</div>');
            }
        },
        stop: function (event, ui) {
            jQuery('.tnpc-drop-here').remove();
        }
    });

    jQuery(".tnpc-row").add_delete();
    jQuery(".tnpc-row").add_block_edit();
    jQuery(".tnpc-row").add_block_clone();

}

function start_composer() {

    init_builder_area();

    // Closes the block options layer (without saving)
    jQuery("#tnpc-block-options-cancel").click(function () {

        tnpc_hide_block_options();

        var _target = target;

        jQuery.post(ajaxurl, start_options, function (response) {
            _target.html(response);
            jQuery("#tnpc-block-options-form").html("");
        });
    });

    // Fires the save event for block options
    jQuery("#tnpc-block-options-save").click(function (e) {
        e.preventDefault();

        var _target = target;

        // fix for Codemirror
        if (typeof templateEditor !== 'undefined') {
            templateEditor.save();
        }

        if (window.tinymce)
            window.tinymce.triggerSave();

        var data = jQuery("#tnpc-block-options-form :input").serializeArray();

        tnpc_add_global_options(data);

        tnpc_hide_block_options();

        jQuery.post(ajaxurl, data, function (response) {
            _target.html(response);

            jQuery("#tnpc-block-options-form").html("");
        });
    });

    jQuery('#tnpc-block-options-form').change(function (event) {
        var data = jQuery("#tnpc-block-options-form :input").serializeArray();

        var _container = tnp_container;
        var _target = target;

        tnpc_add_global_options(data);

        data.push({
            name: '_wpnonce',
            value: tnp_nonce
        });

        jQuery.post(ajaxurl, data, function (response) {
            _target.html(response);
            if (event.target.dataset.afterRendering === 'reload') {
                _container.find(".tnpc-row-edit-block").click();
            }
        }).fail(function () {
            alert("Block rendering failed");
        });

    });

    NewsletterComposer.refresh_style();

}

function tnpc_show_block_options() {
    jQuery("#tnpc-block-options").fadeIn(500);
    jQuery("#tnpc-block-options").css('display', 'flex');
}

function tnpc_hide_block_options() {
    jQuery("#tnpc-block-options").fadeOut(500);
    jQuery("#tnpc-block-options-form").html('');
}

function tnpc_save() {

    if (window.tinymce)
        window.tinymce.triggerSave();

    document.getElementById('options-message').value = tnpc_get_email_content_from_builder_area();

}

function tnpc_get_email_content_from_builder_area() {

    var $elMessage = jQuery("#tnpb-content").clone();

    $elMessage.find('.tnpc-row-delete').remove();
    $elMessage.find('.tnpc-row-edit-block').remove();
    $elMessage.find('.tnpc-row-clone').remove();
    $elMessage.find('.tnpc-row').removeClass('ui-draggable');
    $elMessage.find('#tnpb-sortable-helper').remove();

    return btoa(encodeURIComponent($elMessage.html()));

}

function tnpc_view(type) {
    if (type === 'mobile') {
        jQuery('#tnpb-content').addClass('tnp-view-mobile');
    } else {
        jQuery('#tnpb-content').removeClass('tnp-view-mobile');
    }
}

function tnpc_test(to_email) {
    tnpc_save();
    tnpc_hide_block_options();
    data = jQuery('#tnp-builder').closest('form').serializeArray();
    if (to_email) {
        data.push({
            name: 'to_email',
            value: '1'
        });
        // The modal library moves the div out of the form
        data.push({
            name: 'options[test_email]',
            value: document.getElementById('options-test_email').value
        });
    }
    data.push({
        name: 'action',
        value: 'tnpc_test'
    });
    jQuery.post(ajaxurl, data, function (response) {
        jQuery('#test-newsletter-message').html(response);
        jQuery('#test-newsletter-message').show();
    });

    return false;
}

function tnpb_open_tab(evt, tabName) {
    evt.preventDefault();
    let items = document.getElementsByClassName("tnpb-tab");
    for (let i = 0; i < items.length; i++) {
        items[i].style.display = "none";
    }

    items = document.getElementsByClassName("tnpb-tab-button");
    for (let i = 0; i < items.length; i++) {
        items[i].className = items[i].className.replace(" active", "");
    }

    //document.getElementsByClassName("tnpb-tab").forEach(e => e.style.display = "none");
    //document.getElementsByClassName("tnpb-tab-button").forEach(e => e.className = e.className.replace(" active", ""));

    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.className += " active";
}

function tnpc_reload_options(e) {
    e.preventDefault();
    let options = jQuery("#tnpc-block-options-form :input").serializeArray();
    for (let i = 0; i < options.length; i++) {
        if (options[i].name === 'action') {
            options[i].value = 'tnpc_options';
        }
    }

    jQuery("#tnpc-block-options-form").load(ajaxurl, options);
}

function tnpc_add_global_options(data) {
    let globalOptions = jQuery("#tnpb-settings :input").serializeArray();
    for (let i = 0; i < globalOptions.length; i++) {
        globalOptions[i].name = globalOptions[i].name.replace("[options_", "[").replace("options[", "composer[").replace("composer_", "");
        if (Array.isArray(data)) {
            data.push(globalOptions[i]);
        } else {
            //Inline edit data format is object not array
            data[globalOptions[i].name] = globalOptions[i].value;
        }
    }
}

function _restore_global_options(options) {
    jQuery.each(options, function (name, value) {
        var el = jQuery(`#tnpb-settings-form #options-options_composer_${name}`);
        if (el.length) {
            el.val(value);
        }
    });

    tnp_controls_init();
    NewsletterComposer.refresh_style();

}

function tnpc_remove_double_quotes_escape_from(str) {
    return str.replace(/\\"/g, '"');
}

function tnpc_remove_double_quotes_from(str) {
    return str.replace(/['"]+/g, '');
}

jQuery(document).ready(function () {
    'use strict'

    var TNPInlineEditor = (function () {

        var className = 'tnpc-inline-editable';
        var newInputName = 'new_name';
        var activeInlineElements = [];

        function init() {
            // find all inline editable elements
            jQuery('#tnpb-content').on('click', '.' + className, function (e) {
                e.preventDefault();
                removeAllActiveElements();

                var originalEl = jQuery(this).hide();
                var newEl = jQuery(getEditableComponent(this.innerText.trim(), this.dataset.id, this.dataset.type, originalEl)).insertAfter(this);

                activeInlineElements.push({'originalEl': originalEl, 'newEl': newEl});

                //Add submit event listener for newly created block
                jQuery('.tnpc-inline-editable-form-' + this.dataset.type + this.dataset.id).on('submit', function (e) {
                    submit(e, newEl, jQuery(originalEl));
                });

                //Add close event listener for newly created block
                jQuery('.tnpc-inline-editable-form-actions .tnpc-dismiss-' + this.dataset.type + this.dataset.id).on('click', function (e) {
                    removeAllActiveElements();
                });

            });

            // Close all created elements if clicked outside
            jQuery('#tnpb-content').on('click', function (e) {
                if (activeInlineElements.length > 0
                        && !jQuery(e.target).hasClass(className)
                        && jQuery(e.target).closest('.tnpc-inline-editable-container').length === 0) {
                    removeAllActiveElements();
                }
            });

        }

        function removeAllActiveElements() {
            activeInlineElements.forEach(function (obj) {
                obj.originalEl.show();

                obj.newEl.off();
                obj.newEl.remove();
            });

            activeInlineElements = []
        }

        function getEditableComponent(value, id, type, originalEl) {

            var element = '';

            //COPY FONT STYLE FROM ORIGINAL ELEMENT
            var fontFamily = originalEl.css('font-family');
            var fontSize = originalEl.css('font-size');
            var styleAttr = "style='font-family:" + fontFamily + ";font-size:" + fontSize + ";'";

            switch (type) {
                case 'text':
                {
                    element = "<textarea name='" + newInputName + "' class='" + className + "-textarea' rows='5' " + styleAttr + ">" + value + "</textarea>";
                    break;
                }
                case 'title':
                {
                    element = "<textarea name='" + newInputName + "' class='" + className + "-textarea' rows='2'" + styleAttr + ">" + value + "</textarea>";
                    break;
                }
            }

            var component = "<td>";
            component += "<form class='tnpc-inline-editable-form tnpc-inline-editable-form-" + type + id + "'>";
            component += "<input type='hidden' name='id' value='" + id + "'>";
            component += "<input type='hidden' name='type' value='" + type + "'>";
            component += "<input type='hidden' name='old_value' value='" + value + "'>";
            component += "<div class='tnpc-inline-editable-container'>";
            component += element;
            component += "<div class='tnpc-inline-editable-form-actions'>";
            component += "<button type='submit'><span class='dashicons dashicons-yes-alt' title='save'></span></button>";
            component += "<span class='dashicons dashicons-dismiss tnpc-dismiss-" + type + id + "' title='close'></span>";
            component += "</div>";
            component += "</div>";
            component += "</form>";
            component += "</td>";
            return component;
        }

        function submit(e, elementToDeleteAfterSubmit, elementToShow) {
            e.preventDefault();

            var id = elementToDeleteAfterSubmit.find('form input[name=id]').val();
            var type = elementToDeleteAfterSubmit.find('form input[name=type]').val();
            var newValue = elementToDeleteAfterSubmit.find('form [name="' + newInputName + '"]').val();

            ajax_render_block(elementToShow, type, id, newValue);

            elementToDeleteAfterSubmit.remove();
            elementToShow.show();

        }

        function ajax_render_block(inlineElement, type, postId, newContent) {

            var target = inlineElement.closest('.edit-block');
            var container = target.closest('table');
            var blockContent = target.children('.tnpc-block-content');

            if (container.hasClass('tnpc-row-block')) {
                var data = {
                    'action': 'tnpc_render',
                    'id': container.data('id'),
                    'b': container.data('id'),
                    'full': 1,
                    '_wpnonce': tnp_nonce,
                    'context_type': tnp_context_type,
                    'options': {
                        'inline_edits': [{
                                'type': type,
                                'post_id': postId,
                                'content': newContent
                            }]
                    },
                    'encoded_options': blockContent.data('json')
                };

                tnpc_add_global_options(data);

                jQuery.post(ajaxurl, data, function (response) {
                    var new_row = jQuery(response);

                    container.before(new_row);
                    container.remove();

                    new_row.add_delete();
                    new_row.add_block_edit();
                    new_row.add_block_clone();

                    //Force reload options
                    if (new_row.hasClass('tnpc-row-block')) {
                        new_row.find(".tnpc-row-edit-block").click();
                    }

                }).fail(function () {
                    alert("Block rendering failed.");
                });

            }

        }

        return {init};
    })();

    TNPInlineEditor.init();

});

// =================================================== //
// ===============   GLOBAL STYLE   ================== //
// =================================================== //
var tnpc_view_status = 'desktop';

jQuery(function () {

    // Update the encoded message field on container form submit
    jQuery('#tnpb-main').closest('form').on('submit', function () {
        jQuery("#tnpc-block-options-form").html(''); // To avoid the submission of the current block options
        tnpc_save();
    });

    // Remove the last test results from the test modal
    jQuery('#test-newsletter-modal').on('modal:close', function (ev, modal) {
        jQuery('#test-newsletter-message').html('');
    });

    jQuery('#test-newsletter-modal').on('modal:open', (ev, modal) => {
        jQuery('#test-newsletter-message').html('');
    });

    jQuery('#tnpc-view-mode').on('click', function () {
        if (tnpc_view_status === 'desktop') {
            tnpc_view_status = 'mobile';
            document.getElementById('tnpc-view-mode-icon').className = 'fas fa-mobile';
        } else {
            tnpc_view_status = 'desktop';
            document.getElementById('tnpc-view-mode-icon').className = 'fas fa-desktop';
        }
        tnpc_view(tnpc_view_status);
    });

    // "Apply" button for the global settings the content needs to be regenerated
    jQuery('#tnpb-settings-apply').on('click', ev => {
        ev.preventDefault();
        ev.stopPropagation();

        var data = {
            'action': 'tnpc_regenerate_email',
            'content': tnpc_get_email_content_from_builder_area(),
            '_wpnonce': tnp_nonce,
        };

        tnpc_add_global_options(data);

        jQuery.post(ajaxurl, data, response => {
            if (response && response.success) {
                jQuery('#tnpb-content').html(response.data.content);
                NewsletterComposer.refresh_style();
                init_builder_area();
            }
            TNP.toast(response.data.message);
        });

    });



// ================================================================== //
// =================    SUBJECT LENGTH ICONS    ===================== //
// ================================================================== //

    (function subjectLengthIconsIIFE($) {
        var $subjectContainer = $('#tnpc-subject');
        var $subjectInput = $('#tnpc-subject input');
        var subjectCharCounterEl = null;

        $subjectInput.on('focusin', function (e) {
            $subjectContainer.find('img').fadeTo(400, 1);
        });

        $subjectInput.on('keyup', function (e) {
            setSubjectCharactersLenght(this.value.length);
        });

        $subjectInput.on('focusout', function (e) {
            $subjectContainer.find('img').fadeTo(300, 0);
        });

        function setSubjectCharactersLenght(length = 0) {

            if (length === 0 && subjectCharCounterEl !== null) {
                subjectCharCounterEl.remove();
                subjectCharCounterEl = null;
                return;
            }

            if (!subjectCharCounterEl) {
                subjectCharCounterEl = document.createElement("span");
                subjectCharCounterEl.style.position = 'absolute';
                subjectCharCounterEl.style.top = '-18px';
                subjectCharCounterEl.style.right = $subjectContainer[0].getBoundingClientRect().width - $subjectInput[0].getBoundingClientRect().width + 'px';
                subjectCharCounterEl.style.color = '#999';
                subjectCharCounterEl.style.fontSize = '0.8rem';
                $subjectContainer.find('div')[0].appendChild(subjectCharCounterEl);
            }

            const word = length === 1 ? 'character' : 'characters';
            subjectCharCounterEl.innerHTML = `${length} ${word}`;
        }

    })(jQuery);


});
