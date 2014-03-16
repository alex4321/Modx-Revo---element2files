<?php
$path = $modx->getOption('elements_path');

$directories = array(
  "templates"          => "$path/templates",
  "chunks"             => "$path/chunks",
  "snippets"           => "$path/snippets",
  "plugins"            => "$path/plugins",
);

$convertor = new files2elements();
$convertor->setDirecories($directories);
if(!isset($_GET['update'])) {
  $update = "all";
}
else {
  $update = $_GET["update"];
}
$convertor->updateElements($update);

class files2elements {
  private $elements;

  public function __construct() {
    $this->elements = array();
  }

  public function setDirecories($directories) {
    foreach($directories as $type=>$directory) {
      $elements_file = $directory . '/elements.php';
      if( file_exists($elements_file) ) {
        $elements = array();
        require_once $elements_file;
        foreach($elements as $element_name => $properties) {
          $properties['base_path'] = $directory;
          $this->elements[$type][$element_name] = $properties;
        }
      }
    }
  }

  public function updateElements($type) {
    if($type=="all") {
      foreach( array_keys($this->elements) as $element_type ) {
        $this->updateElements($element_type);
      }
    }
    else {
      $method_name = "update_" . $type;
      $this->$method_name();
    }
  }

  private function prepareElement($name, $fileKey, $name_key, $properties) {
    $properties[$name_key] = $name;
    if(isset($properties['file'])) {
      $properties[$fileKey] = file_get_contents($properties['base_path'] . '/' . $properties['file']);
      unset($properties['file']);
    }
    return $properties;
  }

  private function updateElement($class, $key, $fields) {
    global $modx;

    $q = $modx->newQuery($class);
    $q->where(array($key=>$fields[$key]));
    
    $exists = $modx->getCollection($class, $q);
    if($exists) {
      foreach($exists as $object) {
          $object->fromArray($fields);
      break;
      }
    }
    else {
      $object = $modx->newObject($class);
      $object->fromArray($fields);
    }
    $object->save();

    return $object;
  }

  private function setPluginEvents($pluginId, $eventsinfo) {
    global $modx;

    $modx->removeObject("modPluginEvent", array("id"=>$pluginId));
    foreach($eventsinfo as $event) {
      $row = array_merge(array("pluginid"=>$pluginId), $event);
      $event_obj = $modx->newObject("modPluginEvent");
      $event_obj->fromArray($row);
      $event_obj->save();
    }
  }

  private function update_chunks() {
    $list = $this->elements['chunks'];
    foreach($list as $chunk_name => $properties) {
      $db_fields = $this->prepareElement($chunk_name, 'snippet', 'name', $properties);
      $this->updateElement("modChunk", "name", $db_fields);
    }
  }

  private function update_snippets() {
    $list = $this->elements['snippets'];
    foreach($list as $snippet_name => $properties) {
      $db_fields = $this->prepareElement($snippet_name, 'snippet', 'name', $properties);
      $this->updateElement("modSnippet", "name", $db_fields);
    }
  }

  private function update_plugins() {
    $list = $this->elements['plugins'];
    foreach($list as $plugin_name => $properties) {
      $db_fields = $this->prepareElement($plugin_name, 'plugincode', 'name', $properties);
      $plugin_events = $db_fields['events'];
      unset($db_fields['events']);
      $plugin= $this->updateElement("modPlugin", "name", $db_fields);
      $this->setPluginEvents($plugin->get('id'), $plugin_events);
    }
  }
}
