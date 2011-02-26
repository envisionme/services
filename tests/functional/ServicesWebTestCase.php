<?php
/**
 * @file
 * Services base testing class.
 */

class ServicesWebTestCase extends DrupalWebTestCase {

  protected function servicesGet($url, $data = NULL, $headers = array()) {
    $options = array('query' => $data);
    $url = url($this->getAbsoluteUrl($url) . '.php', $options);
    $headers = array();
    $content = $this->curlExec(array(
      CURLOPT_HTTPGET => TRUE,
      CURLOPT_URL => $url,
      CURLOPT_NOBODY => FALSE,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_HEADER => TRUE,
      CURLOPT_HTTPHEADER => $headers
    ));

    // Parse response.
    list($info, $header, $status, $code, $body) = $this->parseHeader($content);

    $this->verbose('GET request to: ' . $url .
                   '<hr />Arguments: ' . highlight_string('<?php ' . var_export($data, TRUE), TRUE) .
                   '<hr />Response: ' . highlight_string('<?php ' . var_export($body, TRUE), TRUE) .
                   '<hr />Raw response: ' . $content);
    return array('header' => $header, 'status' => $status, 'code' => $code, 'body' => $body);
  }

  protected function servicesPost($url, $data = array(), $headers = array()) {
    $options = array();
    // Add .php to get serialized response.
    $url = $this->getAbsoluteUrl($url) . '.php';

    // Otherwise Services will reject arguments.
    $headers = array("Content-type: application/x-www-form-urlencoded");
    // Prepare arguments.
    $post = http_build_query($data, '', '&');

    $content = $this->curlExec(array(
      CURLOPT_URL => $url,
      CURLOPT_POST => TRUE,
      CURLOPT_POSTFIELDS => $post,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_HEADER => TRUE,
      CURLOPT_RETURNTRANSFER => TRUE
    ));

    // Parse response.
    list($info, $header, $status, $code, $body) = $this->parseHeader($content);

    $this->verbose('POST request to: ' . $url .
                   '<hr />Arguments: ' . highlight_string('<?php ' . var_export($data, TRUE), TRUE) .
                   '<hr />Response: ' . highlight_string('<?php ' . var_export($body, TRUE), TRUE) .
                   '<hr />Curl info: ' . highlight_string('<?php ' . var_export($info, TRUE), TRUE) .
                   '<hr />Raw response: ' . $content);
    return array('header' => $header, 'status' => $status, 'code' => $code, 'body' => $body);
  }

  protected function servicesPut($url, $data = NULL, $headers = array()) {
    $options = array();
    $url = $this->getAbsoluteUrl($url) . '.php';

    $serialize_args = serialize($data);

    // Set up headers so arguments will be unserialized.
    $headers = array("Content-type: application/vnd.php.serialized; charset=iso-8859-1");

    // Emulate file.
    $putData = fopen('php://memory', 'rw+');
    fwrite($putData, $serialize_args);
    fseek($putData, 0);

    $content = $this->curlExec(array(
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_PUT => TRUE,
      CURLOPT_HEADER => TRUE,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_INFILE => $putData,
      CURLOPT_INFILESIZE => drupal_strlen($serialize_args)
    ));
    fclose($putData);

    // Parse response.
    list($info, $header, $status, $code, $body) = $this->parseHeader($content);

    $this->verbose('PUT request to: ' . $url .
                   '<hr />Arguments: ' . highlight_string('<?php ' . var_export($data, TRUE), TRUE) .
                   '<hr />Response: ' . highlight_string('<?php ' . var_export($body, TRUE), TRUE) .
                   '<hr />Curl info: ' . highlight_string('<?php ' . var_export($info, TRUE), TRUE) .
                   '<hr />Raw response: ' . $content);
    return array('header' => $header, 'status' => $status, 'code' => $code, 'body' => $body);
  }

  protected function servicesDelete($url, $data = NULL, $headers = array()) {
    $options = array('query' => $data);
    $url = url($this->getAbsoluteUrl($url) . '.php', $options);

    $content = $this->curlExec(array(
      CURLOPT_URL => $url,
      CURLOPT_CUSTOMREQUEST => "DELETE",
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_RETURNTRANSFER => TRUE
    ));

    // Parse response.
    list($info, $header, $status, $code, $body) = $this->parseHeader($content);

    $this->verbose('DELETE request to: ' . $url .
                   '<hr />Arguments: ' . highlight_string('<?php ' . var_export($data, TRUE), TRUE) .
                   '<hr />Response: ' . highlight_string('<?php ' . var_export($body, TRUE), TRUE) .
                   '<hr />Curl info: ' . highlight_string('<?php ' . var_export($info, TRUE), TRUE) .
                   '<hr />Raw response: ' . $content);
    return array('header' => $header, 'status' => $status, 'code' => $code, 'body' => $body);
  }

  /*
  ------------------------------------
  HELPER METHODS
  ------------------------------------
  */

  /**
   * Parse header.
   *
   * @param type $content
   * @return type
   */
  function parseHeader($content) {
    $info = curl_getinfo($this->curlHandle);
    $header = drupal_substr($content, 0, $info['header_size']);
    $header = str_replace("HTTP/1.1 100 Continue\r\n\r\n", '', $header);
    $status = strtok($header, "\r\n");
    $code = $info['http_code'];
    $body = unserialize(drupal_substr($content, $info['header_size'], drupal_strlen($content) - $info['header_size']));
    return array($info, $header, $status, $code, $body);
  }

  /**
   * Creates a data array for populating an endpoint creation form.
   *
   * @return
   * An array of fields for fully populating an endpoint creation form.
   */
  public function populateEndpointFAPI() {
    return array(
      'name'   => 'machinename',
      'title'  => $this->randomName(20),
      'path'   => $this->randomName(10),
      'server' => 'rest_server',
      'services_use_content_permissions' => TRUE,
    );
  }

  public function saveNewEndpoint() {
    $edit = $this->populateEndpointFAPI() ;
    $endpoint = new stdClass;
    $endpoint->disabled = FALSE; /* Edit this to true to make a default endpoint disabled initially */
    $endpoint->api_version = 3;
    $endpoint->name = $edit['name'];
    $endpoint->title = $edit['title'];
    $endpoint->server = $edit['server'];
    $endpoint->path = $edit['path'];
    $endpoint->authentication = array(
      'services_sessauth' => array(),
    );
    $endpoint->resources = array(
      'node' => array(
        'alias' => '',
        'operations' => array(
          'create' => array(
            'enabled' => 1,
          ),
          'retrieve' => array(
            'enabled' => 1,
          ),
          'update' => array(
            'enabled' => 1,
          ),
          'delete' => array(
            'enabled' => 1,
          ),
          'index' => array(
            'enabled' => 1,
          ),
        ),
      ),
      'system' => array(
        'alias' => '',
        'actions' => array(
          'connect' => array(
            'enabled' => 1,
          ),
          'get_variable' => array(
            'enabled' => 1,
          ),
          'set_variable' => array(
            'enabled' => 1,
          ),
        ),
      ),
      'taxonomy_term' => array(
        'alias' => '',
        'operations' => array(
          'create' => array(
            'enabled' => 1,
          ),
          'retrieve' => array(
            'enabled' => 1,
          ),
          'update' => array(
            'enabled' => 1,
          ),
          'delete' => array(
            'enabled' => 1,
          ),
        ),
        'actions' => array(
          'selectNodes' => array(
            'enabled' => 1,
          ),
        ),
      ),
      'taxonomy_vocabulary' => array(
        'alias' => '',
        'operations' => array(
          'create' => array(
            'enabled' => 1,
          ),
          'retrieve' => array(
            'enabled' => 1,
          ),
          'update' => array(
            'enabled' => 1,
          ),
          'delete' => array(
            'enabled' => 1,
          ),
        ),
        'actions' => array(
          'getTree' => array(
            'enabled' => 1,
          ),
        ),
      ),
      'user' => array(
        'alias' => '',
        'operations' => array(
          'create' => array(
            'enabled' => 1,
          ),
          'retrieve' => array(
            'enabled' => 1,
          ),
          'update' => array(
            'enabled' => 1,
          ),
          'delete' => array(
            'enabled' => 1,
          ),
          'index' => array(
            'enabled' => 1,
          ),
        ),
        'actions' => array(
          'login' => array(
            'enabled' => 1,
          ),
          'logout' => array(
            'enabled' => 1,
          ),
        ),
      ),
      'comment' => array(
        'alias' => '',
        'operations' => array(
          'create' => array(
            'enabled' => 1,
          ),
          'retrieve' => array(
            'enabled' => 1,
          ),
          'update' => array(
            'enabled' => 1,
          ),
          'delete' => array(
            'enabled' => 1,
          ),
        ),
        'actions' => array(
          'loadNodeComments' => array(
            'enabled' => 1,
          ),
          'countAll' => array(
            'enabled' => 1,
          ),
          'countNew' => array(
            'enabled' => 1,
          ),
        ),
      ),
      'file' => array(
        'alias' => '',
        'operations' => array(
          'create' => array(
            'enabled' => 1,
          ),
          'retrieve' => array(
            'enabled' => 1,
          ),
          'delete' => array(
            'enabled' => 1,
          ),
        ),
        'actions' => array(
          'nodeFiles' => array(
            'enabled' => 1,
          ),
        ),
      ),
      'echo' => array(
        'alias' => '',
        'operations' => array(
          'index' => array(
            'enabled' => 1,
          ),
        ),
      ),
    );
    $endpoint->debug = 1;
    $endpoint->status = 1;
    services_endpoint_save($endpoint);
    $endpoint = services_endpoint_load($endpoint->name);
    $this->assertTrue($endpoint->name == $edit['name'], t('Endpoint successfully created'));
    return $endpoint;
  }

  /**
   * Performs a cURL exec with the specified options after calling curlConnect().
   *
   * @param $curl_options
   *   Custom cURL options.
   * @return
   *   Content returned from the exec.
   */
  protected function curlExec($curl_options) {
    $this->curlInitialize();
    $url = empty($curl_options[CURLOPT_URL]) ? curl_getinfo($this->curlHandle, CURLINFO_EFFECTIVE_URL) : $curl_options[CURLOPT_URL];
    if (!empty($curl_options[CURLOPT_POST])) {
      // This is a fix for the Curl library to prevent Expect: 100-continue
      // headers in POST requests, that may cause unexpected HTTP response
      // codes from some webservers (like lighttpd that returns a 417 error
      // code). It is done by setting an empty "Expect" header field that is
      // not overwritten by Curl.
      $curl_options[CURLOPT_HTTPHEADER][] = 'Expect:';
    }
    curl_setopt_array($this->curlHandle, $this->additionalCurlOptions + $curl_options);

    // Reset headers and the session ID.
    $this->session_id = NULL;
    $this->headers = array();

    $this->drupalSetContent(curl_exec($this->curlHandle), curl_getinfo($this->curlHandle, CURLINFO_EFFECTIVE_URL));

    // Analyze the method for log message.
    $method = '';
    if (!empty($curl_options[CURLOPT_NOBODY])) {
      $method = 'HEAD';
    }

    if (empty($method) && !empty($curl_options[CURLOPT_PUT])) {
      $method = 'PUT';
    }

    if (empty($method) && !empty($curl_options[CURLOPT_CUSTOMREQUEST])) {
      $method = $curl_options[CURLOPT_CUSTOMREQUEST];
    }

    if (empty($method)) {
      $method = empty($curl_options[CURLOPT_POSTFIELDS]) ? 'GET' : 'POST';
    }

    $message_vars = array(
      '!method' => $method,
      '@url'    => $url,
      '@status' => curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE),
      '!length' => format_size(drupal_strlen($this->content))
    );
    $message = t('!method @url returned @status (!length).', $message_vars);
    $this->assertTrue($this->content !== FALSE, $message, t('Browser'));
    return $this->drupalGetContent();
  }
}
