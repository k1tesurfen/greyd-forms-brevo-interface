<?php

namespace Greyd\Forms\Interfaces;

use \Greyd\Forms\Helper;

if (! defined('ABSPATH')) {
  exit;
}

new Brevo();

class Brevo
{

  const INTERFACE = 'brevo';

  public function __construct()
  {
    // Settings fields
    $action = 'render_setting_' . self::INTERFACE . '_';
    add_action($action . 'api_key', array($this, 'render_api_key'), 10, 2);
    add_action($action . 'lists', array($this, 'render_lists'), 10, 2);

    // Admin AJAX for fetching lists
    add_action('admin_enqueue_scripts', array($this, 'load_backend'));
    add_action('tp_forms_interface_ajax_' . self::INTERFACE, array($this, 'ajax'));

    // Form submission handlers
    add_filter('handle_after_doi_' . self::INTERFACE, array($this, 'send'), 10, 4);
    add_action('formhandler_optout_' . self::INTERFACE, array($this, 'optout'), 10, 2);
  }

  /**
   * Render API Key field
   */
  public function render_api_key($pre = '', $value = null)
  {
    $option = 'api_key';
    $slug   = $pre . '[' . $option . ']';
    $value  = isset($value) ? strval($value) : '';
    echo "<input type='text' id='$slug' class='regular-text' name='$slug' value='$value'>";
    echo "<br><small><a href='" .
      __('https://developers.brevo.com/docs/getting-started', 'greyd_forms') .
      "' target='_blank'>" .
      __("How do I get an API v3 key?", 'greyd_forms') .
      '</a></small>';
  }

  /**
   * Render lists input
   */
  public function render_lists($pre = '', $value = null)
  {
    $option   = 'lists';
    $slug     = $pre . '[' . $option . ']';
    $value    = isset($value) ? strval($value) : '';
    $lists    = strpos($value, '{') !== false ? json_decode($value, true) : $value;
    $settings = Greyd_Forms_Interfaces::get_interface_settings(self::INTERFACE);
    $api_key  = isset($settings['api_key']) ? $settings['api_key'] : '';
    $not_ready_class = empty($api_key) ? '' : 'hidden';
    $ready_class     = empty($api_key) ? 'hidden' : '';
    $empty_class     = 'empty ' . (empty($value) ? '' : 'hidden');
    $set_class       = 'set ' . (empty($value) ? 'hidden' : '');
    $info_icon       = "<span class='dashicons dashicons-info'></span>&nbsp;";
    echo "<div id='" . self::INTERFACE . "'>";
    echo "<input type='hidden' id='$slug' name='$slug' value='$value'>";
    echo "<div class='not_ready $not_ready_class'><p style='opacity:.5;'>" . $info_icon . __("Please enter your Brevo API key first.", 'greyd_forms') . '</p></div>';
    echo "<div class='ready $ready_class'><span style='display:none'><span class='loader'></span></span><div class='$empty_class'><p style='opacity:.5;'>" . $info_icon . __("No lists currently available.", 'greyd_forms') . "</p><span class='button getLists' style='margin-top:10px;'>" . __("Get lists now", 'greyd_forms') . "</span></div><div class='$set_class'><ul class='input_list'>";
    if (is_array($lists) && count($lists) > 0) {
      foreach ((array) $lists as $id => $name) {
        echo "<li><strong>$name</strong> (ID: $id)</li>";
      }
    }
    echo "</ul><br><span class='button getLists $set_class' style='margin-top:10px;'>" . __("Update lists", 'greyd_forms') . "</span></div><div class='_error hidden'>";
    echo Helper::render_info_box(array('style' => 'red', 'text'  => '<span class=text>' . __("Lists could not be retrieved. Please check your API key and retry.", 'greyd_forms') . '</span>'));
    echo "<span class='button getLists' style='margin-top:10px;'>" . __("Retry", 'greyd_forms') . '</span></div></div></div>';
  }

  /**
   * Enqueue admin script
   */
  public function load_backend()
  {
    $page = isset($_GET['page']) ? $_GET['page'] : '';
    if ($page === 'greyd_settings_forms') {
      $dir = plugin_dir_url('greyd_tp_forms/init.php') . 'interfaces/' . self::INTERFACE . '/';
      wp_register_script(self::INTERFACE . '_backend_js', $dir . '/assets/backend.js', array('jquery'));
      wp_localize_script(self::INTERFACE . '_backend_js', 'local_' . self::INTERFACE, array('ajaxurl' => admin_url('admin-ajax.php'), 'nonce'   => wp_create_nonce(Greyd_Forms_Interfaces::AJAX_ACTION), 'action'  => Greyd_Forms_Interfaces::AJAX_ACTION));
      wp_enqueue_script(self::INTERFACE . '_backend_js');
    }
  }

  /**
   * Handle admin AJAX to fetch lists
   */
  public function ajax($data)
  {
    $api_key = isset($data['api_key']) ? $data['api_key'] : '';
    if (! empty($api_key)) {
      include_once __DIR__ . '/handle.php';
      new Brevo_Handler($api_key);
      $lists = Brevo_Handler::get_lists();
      if (is_array($lists) && count($lists) > 0) {
        wp_die('success::' . json_encode($lists));
      }
    }
    wp_die('error::' . __("Listenanfrage fehlgeschlagen. Check deinen API key und versuche es erneut.", 'greyd_forms'));
  }

  /**
   * Send form data to Brevo API
   */
  public function send($response, $entry_id, $formdata, $postmeta)
  {
    $options = Greyd_Forms_Interfaces::get_interface_settings(self::INTERFACE);
    $api_key = isset($options['api_key']) ? $options['api_key'] : '';
    $lists   = isset($options['lists']) && strpos($options['lists'], '{') !== false ? json_decode($options['lists'], true) : '';
    if (empty($api_key) || empty($lists)) {
      return false;
    }

    $interface_data = array();
    $attributes     = array();
    $name           = Greyd_Forms_Interfaces::get_interface_config(self::INTERFACE, 'name');
    $fields         = isset($postmeta['normal']) ? (array) $postmeta['normal'] : array();
    if (! isset($fields['email']) || empty($formdata[$fields['email']])) {
      return $name . ': ' . __("Email nicht definiert, Kontakt konnte nicht gesendet werden.", 'greyd_forms');
    }

    foreach ($fields as $key => $val) {
      if (! empty($formdata[$val])) {
        $value = html_entity_decode($formdata[$val]);
        if ($key === 'email') {
          $interface_data['email'] = $value;
        } else {
          $attributes[$key] = $value;
        }
      }
    }

    if (! empty($attributes)) {
      $interface_data['attributes'] = $attributes;
    }

    $list_id = isset($postmeta['meta']['list']) ? $postmeta['meta']['list'] : array_key_first($lists);
    $use_doi = isset($postmeta['meta']['doi']);

    // Filter data before sending
    $interface_data = apply_filters('greyd_forms_interface_data_' . self::INTERFACE, $interface_data, $entry_id, $formdata, $postmeta);
    if (empty($interface_data['email'])) {
      return false;
    }

    Greyd_Forms_Interfaces::update_entry_data($entry_id, self::INTERFACE, array('email' => $interface_data['email']));

    include_once __DIR__ . '/handle.php';
    new Brevo_Handler($api_key);

    if ($use_doi) {
      $template_id = isset($postmeta['meta']['doi_template_id']) ? intval($postmeta['meta']['doi_template_id']) : 0;
      $redirect_url = isset($postmeta['meta']['doi_redirect_url']) ? $postmeta['meta']['doi_redirect_url'] : '';

      if (empty($template_id) || empty($redirect_url)) {
        return $name . ': ' . __("Double Opt-In ist aktiv, jedoch fehlt Template ID oder Umleitungs-URL.", 'greyd_forms');
      }

      // Add required DOI fields to the payload
      $interface_data['templateId'] = $template_id;
      $interface_data['redirectionUrl'] = $redirect_url;
      $interface_data['includeListIds'] = array(intval($list_id));

      $response = Brevo_Handler::create_doi_contact($interface_data);
      $log_message = __("Double Opt-In Bestätigung wurde versandt.", 'greyd_forms');
    } else {
      $interface_data['listIds'] = array(intval($list_id));
      $interface_data['updateEnabled'] = isset($postmeta['meta']['update']);

      $response = Brevo_Handler::create_or_update_contact($interface_data);
      $log_message = __("Kontakt wurde erfolgreich erstellt oder aktualisiert.", 'greyd_forms');
    }

    if ($response === true) {
      Helper::log_entry_state($entry_id, $name . ': ' . $log_message, 'success');
    } else {
      Helper::log_entry_state($entry_id, $name . ': ' . self::get_error($response));
    }

    return $response;
  }

  /**
   * Format error from Brevo response
   */
  public static function get_error($response)
  {
    if (is_wp_error($response)) {
      return $response->get_error_message();
    }
    if (! is_array($response) || ! isset($response['message'])) {
      return __('An unexpected error occurred.', 'greyd_forms');
    }
    $message = $response['message'];
    if (isset($response['code'])) {
      $message .= ' (Code: ' . $response['code'] . ')';
    }
    return $message;
  }

  /**
   * Handle opt-out: delete contact from Brevo
   */
  public function optout($entry_id, $meta)
  {
    if (empty($entry_id) || empty($meta) || ! is_array($meta) || ! isset($meta['email'])) {
      return false;
    }

    $options = Greyd_Forms_Interfaces::get_interface_settings(self::INTERFACE);
    $api_key = isset($options['api_key']) ? $options['api_key'] : '';
    if (empty($api_key)) {
      return false;
    }

    include_once __DIR__ . '/handle.php';
    new Brevo_Handler($api_key);
    $name     = Greyd_Forms_Interfaces::get_interface_config(self::INTERFACE, 'name');
    $response = Brevo_Handler::delete_contact($meta['email']);

    if ($response === true) {
      Helper::log_entry_state($entry_id, $name . ': ' . __("Kontakt wurde gelöscht.", 'greyd_forms'), 'success', false);
    } else {
      Helper::log_entry_state($entry_id, $name . ': ' . self::get_error($response));
    }
  }
}
