<?php namespace VaneMart;

function asset($url) {
  strpos($url, '::') or $url = "vanemart::$url";
  list($bundle, $url) = explode('::', $url, 2);
  $bundle === '' or $url = "bundles/$bundle/".ltrim($url, '/');
  return \asset($url);
}
