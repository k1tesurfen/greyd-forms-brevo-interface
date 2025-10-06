<?php

namespace Greyd\Forms\Interfaces;

if (! defined('ABSPATH')) {
  exit;
}

/**
 * Brevo_Handler Class
 *
 * Handles all communication with the Brevo API v3.
 */
class Brevo_Handler
{

  private static $api_key;
  private static $api_base_url = 'https://api.brevo.com/v3/';

  public function __construct($api_key)
  {
    if (empty($api_key)) {
      return false;
    }
    self::$api_key = $api_key;
  }

  /**
   * Makes a request to the Brevo API.
   */
  private static function request($endpoint, $args = array(), $method = 'GET')
  {
    $url = self::$api_base_url . $endpoint;

    $default_args = array(
      'method'  => $method,
      'headers' => array(
        'accept'       => 'application/json',
        'content-type' => 'application/json',
        'api-key'      => self::$api_key,
      ),
      'timeout' => 15,
    );

    $request_args = wp_parse_args($args, $default_args);
    $response     = wp_remote_request($url, $request_args);

    if (is_wp_error($response)) {
      return $response;
    }

    $body         = wp_remote_retrieve_body($response);
    $decoded_body = json_decode($body, true);
    $http_code    = wp_remote_retrieve_response_code($response);

    if ($http_code >= 200 && $http_code < 300) {
      return $decoded_body;
    } else {
      return ! empty($decoded_body) ? $decoded_body : array('code' => 'error', 'message' => 'An unknown Error occurred.');
    }
  }

  /**
   * Get all contact lists from Brevo.
   */
  public static function get_lists()
  {
    $return   = array();
    $response = self::request('contacts/lists?limit=50');

    if (isset($response['lists']) && is_array($response['lists'])) {
      foreach ($response['lists'] as $list) {
        $return[$list['id']] = $list['name'];
      }
    }
    return $return;
  }

  /**
   * Create or update a contact in Brevo.
   */
  public static function create_or_update_contact($args = array())
  {
    if (! isset($args['email'])) {
      return false;
    }

    $response = self::request('contacts', array('body' => json_encode($args)), 'POST');

    if (is_array($response) && isset($response['message'])) {
      return $response; // Return error from Brevo
    } else {
      return true; // Success
    }
  }

  /**
   * Create a contact with Double Opt-In confirmation.
   */
  public static function create_doi_contact($args = array())
  {
    if (! isset($args['email']) || ! isset($args['templateId']) || ! isset($args['redirectionUrl'])) {
      return false;
    }

    $response = self::request('contacts/doubleOptinConfirmation', array('body' => json_encode($args)), 'POST');

    if (is_array($response) && isset($response['message'])) {
      return $response; // Return error from Brevo
    } else {
      return true; // Success
    }
  }


  /**
   * Deletes a contact from Brevo.
   */
  public static function delete_contact($email = '')
  {
    if (empty($email)) {
      return false;
    }

    $endpoint = 'contacts/' . urlencode($email);
    $response = self::request($endpoint, array(), 'DELETE');

    if (is_array($response) && isset($response['message'])) {
      return $response;
    } else {
      return true;
    }
  }
}
