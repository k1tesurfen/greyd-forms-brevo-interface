<?php

return array(
  'name'     => __('Brevo', 'greyd_forms'),

  // register settings
  'settings' => array(
    'description' => __("Connect you re Brevo account to automatically create Brevo contacts through forms.", 'greyd_forms'),
    'options'     => array(
      'api_key' => __("API v3 key:", 'greyd_forms'),
      'lists'   => __("Contact lists:", 'greyd_forms'),
    ),
  ),

  // register fields for the form editor
  'metabox'  => array(
    'enable' => array(
      'title'       => __("Create Brevo contact?", 'greyd_forms'),
      'description' => __("Do you want to create a new Brevo contact on successful form submission?", 'greyd_forms'),
    ),
    'normal' => array(
      'fields' => array(
        // Standard Brevo contact attributes.
        'email'      => _x("Email", 'small', 'greyd_forms'),
        'FIRSTNAME'  => _x("First Name", 'small', 'greyd_forms'),
        'LASTNAME'   => _x("Last Name", 'small', 'greyd_forms'),
      ),
    ),
    'meta'   => array(
      'title'  => __("Actions", 'greyd_forms'),
      'fields' => array(
        'list'   => array(
          'type'  => 'select',
          'label' => __("Contact list", 'greyd_forms'),
          'value' => self::get_interface_settings('brevo', 'lists'),
        ),
        'update' => array(
          'type'  => 'checkbox',
          'label' => __("Update contact if it already exists?", 'greyd_forms'),
        ),
        'doi' => array(
          'type'  => 'checkbox',
          'label' => __("Enable double opt-in?", 'greyd_forms'),
          'class' => 'greyd-forms-toggle-trigger',
        ),
        'doi_template_id' => array(
          'type'  => 'number',
          'label' => __("DOI Template ID", 'greyd_forms'),
          'description' => __('The ID of an active double opt-in email templates in Brevo.', 'greyd_forms'),
          'class' => 'greyd-forms-toggle-target',
          'toggle_id' => 'doi',
        ),
        'doi_redirect_url' => array(
          'type'  => 'text',
          'label' => __("DOI redirection URL", 'greyd_forms'),
          'description' => __('The URL the user gets redirected to after successfully completing the double opt-in.', 'greyd_forms'),
          'class' => 'greyd-forms-toggle-target',
          'toggle_id' => 'doi',
        ),
      ),
    ),
  ),
  // Enable the opt-out feature to delete contacts from Brevo.
  'optout'   => true,
);
