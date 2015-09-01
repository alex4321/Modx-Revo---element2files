<?php
$update = $_GET["update"];
$path = $modx->getOption('elements_path');
$directories = array(
	"templates"          => "$path/templates",
	"chunks"             => "$path/chunks",
	"snippets"           => "$path/snippets",
	"plugins"            => "$path/plugins",
);
$convertor = new files2elements();
$convertor->setDirecories($directories);
if($modx->event->name!="OnCacheUpdate") {
	$cacheUpdate = TRUE;
}
else {
	$cacheUpdate = FALSE;
}
$convertor->updateElements($update, $cacheUpdate);


class files2elements {
	private $elements;

	public function __construct() {
		$this->elements = array();
	}

	/**
	 * Load informations about elements from directories
	 * @param array $directories array of elements with same structure : [
	 *  'templates' = > 'templatesDir',
	 *  'chunks' => 'chunksDir',
	 *  'snippets' => 'snippetsDir',
	 *  'plugins' => 'pluginsDir',
	 * ];
	 */
	public function setDirecories($directories) {
		foreach ($directories as $type=>$directory) {
			$elementsFile = $directory . '/elements.php';
			if (file_exists ($elementsFile) ) {
				$elements = array();
				require_once $elementsFile;
				foreach ($elements as $elementName => $properties) {
					$properties['base_path'] = $directory;
					$this->elements[$type][$elementName] = $properties;
				}
			}
		}
	}

	/**
	 * Update all file-based elements
	 * @param string $type one of next : "templates", "chunks", "snippets", "plugins", "all"
	 * @param bool $cacheUpdate reset chache?
	 */
	public function updateElements($type, $cacheUpdate) {
		if($type=="all") {
			foreach( array_keys($this->elements) as $elementType ) {
				$this->updateElements($elementType);
			}
		}
		else {
			$methods = array(
				"templates" => "update_templates",
				"chunks" => "update_chunks",
				"snippets" => "update_snippets",
				"plugins" => "update_plugins",
			);
			if (isset ($methods[$type])) {
				$methodName = $methods[$type];
				$this->$methodName();
			}
		}
		if($cacheUpdate) {
			global $modx;
			$modx->cacheManager->refresh();
		}
	}

	private function prepareElement($name, $fileKey, $nameKey, $properties) {
		$properties[$nameKey] = $name;
		if(isset($properties['file'])) {
			$properties[$fileKey] = file_get_contents($properties['base_path'] . '/' . $properties['file']);
			unset($properties['file']);
		}
		return $properties;
	}

	private function updateElement($class, $nameKey, $contentKey, $db_fields) {
		global $modx;
		$q = $modx->newQuery($class);
		$q->where(array($nameKey=>$db_fields[$nameKey]));
		$exists = $modx->getCollection($class, $q);
		if($exists) {
			foreach($exists as $object) {
				$object->fromArray($db_fields);
				break;
			}
		}
		else {
			$object = $modx->newObject($class);
			$object->fromArray($db_fields);
		}
		$object->setContent($db_fields[$contentKey]);
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

	private function update_templates() {
		$list = $this->elements['templates'];
		foreach($list as $template_name => $properties) {
			$db_fields = $this->prepareElement($template_name, 'content', 'templatename', $properties);
			$this->updateElement("modTemplate", "templatename", "content", $db_fields);
		}
	}

	private function update_chunks() {
		$list = $this->elements['chunks'];
		foreach($list as $chunk_name => $properties) {
			$db_fields = $this->prepareElement($chunk_name, 'snippet', 'name', $properties);
			$this->updateElement("modChunk", "name", "snippet", $db_fields);
		}
	}

	private function update_snippets() {
		$list = $this->elements['snippets'];
		foreach($list as $snippet_name => $properties) {
			$db_fields = $this->prepareElement($snippet_name, 'snippet', 'name', $properties);
			$this->updateElement("modSnippet", "name", "snippet", $db_fields);
		}
	}

	private function update_plugins() {
		$list = $this->elements['plugins'];
    	foreach($list as $plugin_name => $properties) {
			$db_fields = $this->prepareElement($plugin_name, 'plugincode', 'name', $properties);
			$plugin_events = $db_fields['events'];
			unset($db_fields['events']);
			$plugin= $this->updateElement("modPlugin", "name", "plugincode", $db_fields);
			$this->setPluginEvents($plugin->get('id'), $plugin_events);
		}
	}
}
