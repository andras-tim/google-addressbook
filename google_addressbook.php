<?php

/**
 * Google Addressbook
 *
 * Plugin to use google contacts in roundcube mail.
 *
 * @version 0.1
 * @author Stefan L. Wagner
 * @url http://roundcube.net/plugins/google_addressbook
 */

// php5-curl extension required!
require_once(dirname(__FILE__) . '/google-api-php-client/src/Google_Client.php');
require_once(dirname(__FILE__) . '/google_addressbook_backend.php');
require_once(dirname(__FILE__) . '/xml_utils.php');

class google_addressbook extends rcube_plugin
{
  public $task = 'mail|addressbook|settings';
  private $abook_id = 'google_addressbook';
  private $abook_name = 'Google Addressbook';
  private $token_settings_key = 'google_current_token';
  private $client;

  function init()
  {
    $rcmail = rcmail::get_instance();
    $this->add_texts('localization/', true);
    
    $this->load_config('config.inc.php.dist');
    $this->load_config('config.inc.php');

    $this->client = new Google_Client();
    $this->client->setApplicationName('rc-google-addressbook');
    $this->client->setScopes("http://www.google.com/m8/feeds/");
    $this->client->setClientId('983435418908-ck5ihok844ui6epea0la4akkga0g6v3o.apps.googleusercontent.com');
    $this->client->setClientSecret('YK0dxut9gqRayypORGHvDgVt');
    $this->client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
    $this->client->setAccessType('online');

    $this->add_hook('preferences_list', array($this, 'preferences_list'));
    $this->add_hook('preferences_save', array($this, 'preferences_save'));
    $this->add_hook('addressbooks_list', array($this, 'addressbooks_list'));
    $this->add_hook('addressbook_get', array($this, 'addressbook_get'));
    $this->add_hook('contact_create', array($this, 'contact_create'));
    $this->add_hook('contact_update', array($this, 'contact_update'));
    $this->add_hook('contact_delete', array($this, 'contact_delete'));
    $this->register_action('plugin.google_addressbook', array($this, 'handle_ajax_requests'));

    // add google addressbook to autocomplete addressbooks
    $sources = (array) $rcmail->config->get('autocomplete_addressbooks', 'sql');
    $sources[] = $this->abook_id;
    $rcmail->config->set('autocomplete_addressbooks', $sources);
    
    $this->include_script('google_addressbook.js');
  }

  function get_current_token($from_db = false)
  {
    $prefs = rcmail::get_instance()->user->get_prefs();
    return $prefs[$this->token_settings_key];
  }
  
  function save_current_token($token)
  {
    $prefs = array($this->token_settings_key => $token);
    if(!rcmail::get_instance()->user->save_prefs($prefs)) {
      // TODO: error handling
    }
  }

  function handle_ajax_requests()
  {
    $rcmail = rcmail::get_instance();
    $action = get_input_value('_act', RCUBE_INPUT_GPC);
    if($action == 'sync') {
      $this->google_sync_contacts();
    }
    $rcmail->output->command('plugin.finished', array('message' => $this->gettext('done')));
  }

  function preferences_list($params)
  {
    $rcmail = rcmail::get_instance();
    if($params['section'] == 'addressbook') {
      $params['blocks'][$this->id]['name'] = $this->abook_name;

      $field_id = 'rc_use_plugin';
      $checkbox = new html_checkbox(array('name' => $field_id, 'id' => $field_id, 'value' => 1));
      $params['blocks'][$this->id]['options'][$field_id] = array(
        'title' => html::label($field_id, $this->gettext('use').$this->abook_name),
        'content' => $checkbox->show($rcmail->config->get('use_google_abook'))
      );

      $field_id = 'rc_google_auth';
      $input_auth = new html_inputfield(array('name' => $field_id, 'id' => $field_id, 'size' => 35));
      $params['blocks'][$this->id]['options'][$field_id] = array(
        'title' => html::label($field_id, $this->gettext('authcode')),
        'content' => $input_auth->show($rcmail->config->get('google_auth_key'))
      );

      $params['blocks'][$this->id]['options']['link'] = array(
        'title' => html::span('', ''),
        'content' => html::a(array('href' => $this->client->createAuthUrl(), 'target' => '_blank'), $this->gettext('authcodelink'))
      );
    }
    return $params;
  }

  function preferences_save($params)
  {
    if($params['section'] == 'addressbook') {
      $params['prefs']['use_google_abook'] = isset($_POST['rc_use_plugin']) ? true : false;
      $params['prefs']['google_auth_key'] = get_input_value('rc_google_auth', RCUBE_INPUT_POST);
    }
    return $params;
  }

  // roundcube collects information about available addressbooks
  function addressbooks_list($params)
  {
    if(true) {
      // TODO: only if plugin enabled
      $params['sources'][$this->id] = array('id' => $this->abook_id, 
                                            'name' => $this->abook_name, 
                                            'groups' => false, 
                                            'readonly' => true, 
                                            'autocomplete' => true);
    }
    return $params;
  }

  // user opens addressbook
  function addressbook_get($params)
  {
    $rcmail = rcmail::get_instance();
    if($params['id'] == $this->abook_id) {
      //$rcmail->output->command('enable_command', 'add', false);
      //$rcmail->output->command('enable_command', 'import', false);
      $params['instance'] = new google_addressbook_backend($this->abook_name, $rcmail->db, $rcmail->user->ID);
      $params['writable'] = false;
    }

    if(isset($_GET['sync'])) {
      $this->google_sync_contacts();
    }

    return $params;
  }

  function google_authenticate($code)
  { //TODO: unauth on logout
    $rcmail = rcmail::get_instance();

    $token = $this->get_current_token();
    if($token != null) {
      $this->client->setAccessToken($token);
    }

    if($this->client->getAccessToken() == null) {
      try {
        $this->client->authenticate($code);
        $token = $this->client->getAccessToken();
        $this->save_current_token($token);
      } catch(Exception $e) {
        $rcmail->output->show_message($e->getMessage(), 'error');
        return false;
      }
    } else if($this->client->isAccessTokenExpired()) {
        // get the current tokens...
        $tokens = json_decode($this->client->getAccessToken());
        $this->client->refreshToken($token->refresh_token);
        // ... and now save the new tokens
        $this->save_current_token($this->client->getAccessToken());
        //$rcmail->output->show_message('Expired!', 'error');
        return true;
    }
    $this->save_current_token($token);

    return true;
  }

  function google_sync_contacts()
  {
    write_log('google_addressbook', 'google_sync_contacts');
    $rcmail = rcmail::get_instance();
    $code = $rcmail->config->get('google_auth_key');
    
    if(!$this->google_authenticate($code)) {
      return;
    }
    
    $feed = 'https://www.google.com/m8/feeds/contacts/default/full'.'?max-results=9999'.'&v=3.0';
    $val = $this->client->getIo()->authenticatedRequest(new Google_HttpRequest($feed));
    $xml = xml_utils::xmlstr_to_array($val->getResponseBody());
    $num_entries = count($xml['entry']);
    
    write_log('response', 'getting contact: '.print_r($val->getResponseBody(), true));
    $rcmail->output->show_message($num_entries.$this->gettext('contactsfound'), 'confirmation');

    $backend = new google_addressbook_backend($this->abook_name, $rcmail->db, $rcmail->user->ID);
    $backend->delete_all();
    
    foreach($xml['entry'] as $entry) {
      write_log('google_addressbook', 'getting contact: '.$entry['title'][0]['@text']);
      //write_log('google_addressbook', 'getting contact: '.print_r($entry,true));
      $record = array();
      $name = $entry['gd:name'][0];
      $record['name']= $name['gd:fullName'][0]['@text'];
      $record['firstname'] = $name['gd:givenName'][0]['@text'];
      $record['surname'] = $name['gd:familyName'][0]['@text'];
      $record['middlename'] = $name['gd:additionalName'][0]['@text'];
      $record['prefix'] = $name['gd:namePrefix'][0]['@text'];
      $record['suffix'] = $name['gd:nameSuffix'][0]['@text'];
      if(empty($record['name'])) {
        $record['name'] = $entry['title'][0]['@text'];
      }

      foreach($entry['gd:email'] as $email) {
        list($rel, $type) = explode('#', $email['@attributes']['rel'], 2);
        $type = empty($type) ? '' : ':'.$type;
        $record['email'.$type] = $email['@attributes']['address'];
      }

      foreach($entry['gd:phoneNumber'] as $phone) {
        list($rel, $type) = explode('#', $phone['@attributes']['rel'], 2);
        $type = empty($type) ? '' : ':'.$type;
        $record['phone'.$type] = $phone['@text'];
      }
      
      foreach($entry['link'] as $link) {
        $rel = $link['@attributes']['rel'];
        $href = $link['@attributes']['href'];
        if($rel == 'http://schemas.google.com/contacts/2008/rel#photo') {
          $resp = $this->client->getIo()->authenticatedRequest(new Google_HttpRequest($href));
          if($resp->getResponseHttpCode() == 200) {
            $record['photo'] = $resp->getResponseBody();
          }
          break;
        }
      }

      $backend->insert($record, false);
    }
  }

  function contact_create($params)
  {
    write_log('google_addressbook', 'contact_create: '.print_r($params, true));
    // TODO: not supported right now
  }

  function contact_update($params)
  {
    write_log('google_addressbook', 'contact_update: '.print_r($params, true));
    // TODO: not supported right now
  }

  function contact_delete($params)
  {
    write_log('google_addressbook', 'contact_delete: '.print_r($params, true));
    // TODO: not supported right now
  }
}

?>