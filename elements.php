<?php

/*
 *  example of plugin adding with this plugin
 */

$elements['Files2Elements'] = array(
  'file' => 'Files2Elements.php',
  'events' => array(
      array(
        'event' => 'OnCacheUpdate'
      )
    )
);
