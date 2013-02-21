<?php namespace VaneMart;

//= str URL
function asset($url) {
  strpos($url, '::') or $url = "vanemart::$url";
  list($bundle, $url) = explode('::', $url, 2);
  $bundle === '' or $url = "bundles/$bundle/".ltrim($url, '/');
  return \asset($url);
}

//= str
function typography($str) {
  $str = S::capitalize(trim($str));
  if ($str === '') { return $str; }

  $lb = '(?<=|\.\.\.|[…\s"\'«“‘‛‹\/\\<[{(])';   // left boundary
  $rb = '($|[…\s"\'»„”’‛›\/\\>\]})?!.,:;])';    // right boundary

  $regexp = array(
    $lb.'"([^"]*)"'.$rb                 => '«\1»\2',
    $lb.'([+-]?\d+)[\s^](F|C)'.$rb      => '\1°\2\3',
    $lb.'\+-(\d+)'.$rb                  => '±\1\2',
    $lb.'([+-]?\d+)\.(\d+)'.$rb         => '\1,\2\3',
    '\.\.\.+'                           => '…',
    ' -+ '                              => ' — ',
  );

  $regexp = S::keys($regexp, '#"~?~u"');
  return preg_replace(array_keys($regexp), array_values($regexp), $str);
}

// Used for multiline/large texts.
//= str
function prettyText($str, $typography = true) {
  static $endPunct = array('.', ',', '!', '?');

  $str = $typography ? typography($str) : S::capitalize(trim($str));
  if ($str === '') { return $str; }

  in_array(S::last($str), $endPunct) or $str .= '.';

  return $str;
}
