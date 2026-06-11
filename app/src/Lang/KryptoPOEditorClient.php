<?php

/**
 * Small POEditor API client for the language features used by Krypto.
 *
 * @package Krypto
 */
class KryptoPOEditorClient {

  private $apiKey = null;
  private $baseUrl = null;

  public function __construct($apiKey, $baseUrl = 'https://api.poeditor.com/v2'){
    $this->apiKey = $apiKey;
    $this->baseUrl = rtrim($baseUrl, '/');
  }

  public function getProjects(){
    $response = $this->_post('/projects/list');
    $projects = isset($response['result']['projects']) && is_array($response['result']['projects'])
      ? $response['result']['projects']
      : [];

    return array_map(function($project){
      return new KryptoPOEditorProject($this, $project);
    }, $projects);
  }

  public function getProjectLanguages($projectId){
    $response = $this->_post('/languages/list', [
      'id' => $projectId
    ]);

    return isset($response['result']['languages']) && is_array($response['result']['languages'])
      ? $response['result']['languages']
      : [];
  }

  public function getDefinitions($projectId, $language){
    $response = $this->_post('/terms/list', [
      'id' => $projectId,
      'language' => $language
    ]);
    $terms = isset($response['result']['terms']) && is_array($response['result']['terms'])
      ? $response['result']['terms']
      : [];

    return array_map(function($term){
      return new KryptoPOEditorDefinition($term);
    }, $terms);
  }

  private function _post($path, array $payload = []){
    $payload = array_merge([
      'api_token' => $this->apiKey
    ], $payload);

    $context = stream_context_create([
      'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($payload, '', '&'),
        'timeout' => 10
      ]
    ]);

    $raw = @file_get_contents($this->baseUrl.$path, false, $context);
    if($raw === false) throw new Exception('Error : POEditor API request failed', 1);

    $decoded = json_decode($raw, true);
    if(!is_array($decoded)) throw new Exception('Error : POEditor API response is invalid', 1);

    if(isset($decoded['response']['status']) && $decoded['response']['status'] !== 'success'){
      $message = isset($decoded['response']['message']) ? $decoded['response']['message'] : 'POEditor API request failed';
      throw new Exception('Error : '.$message, 1);
    }

    return $decoded;
  }

}

class KryptoPOEditorProject {

  private $client = null;
  private $data = [];

  public function __construct(KryptoPOEditorClient $client, array $data){
    $this->client = $client;
    $this->data = $data;
  }

  public function getId(){
    return isset($this->data['id']) ? $this->data['id'] : null;
  }

  public function getName(){
    return isset($this->data['name']) ? $this->data['name'] : '';
  }

  public function getDefinitions($language){
    return $this->client->getDefinitions($this->getId(), $language);
  }

}

class KryptoPOEditorDefinition {

  private $data = [];

  public function __construct(array $data){
    $this->data = $data;
  }

  public function getTerm(){
    return new KryptoPOEditorTerm(isset($this->data['term']) ? $this->data['term'] : '');
  }

  public function getForm(){
    if(isset($this->data['translation']['content'])) return $this->data['translation']['content'];
    if(isset($this->data['definition'])) return $this->data['definition'];
    return '';
  }

}

class KryptoPOEditorTerm {

  private $term = '';

  public function __construct($term){
    $this->term = $term;
  }

  public function getTerm(){
    return [
      'term' => $this->term
    ];
  }

}

?>
