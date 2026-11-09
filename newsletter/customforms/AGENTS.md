This subfolder is a part of a WordPress plugin.

Code in this subfolder implements the custom forms management with
an HTML code editor supported by an AI chatbot.

- index.php is the page containing the admin interface with the list of the configured forms
- edit.php the editing entry point which loads the correct editor looking at the form editor field (0 for code editor, 1 for AI editor)
- design-code.php is the editor with a direct code input (HTML and CSS)
- design-ai.php is the page with the AI assisted form designer
- forms-admin.php has a WP ajax action to prompt the LLM and get the answer
- forms.php is a class used by the frontend and can be ignored

## design-ai.php specifications

It's a chat interface with an LLM that use the "instructions.md"
to generate the form fields to create a subscription form.

The JavaScript has the variable llmUrl to be use
to post data to the LLM via AJAX. The AJAX request is managed by
forms-admin.php and uses the AJAX conventions of WordPress.

The answer from the LLM is always a json with this structure:

```json
{
  "html": "the generated HTML code",
  "explanation": "brief description of modifications or limitations"
}
```

## design-code.php specifications

This page uses the CodeMirror javascript library already incuded in
WordPress and shows an HTML editor to edit the for fields
and a preview iframe to see the result.
