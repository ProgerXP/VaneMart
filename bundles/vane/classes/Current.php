<?php namespace Vane;

// Creates namespaces for Vane configuration profiles.
class Current {
  static $ns;
  static $expanded = array();

  //= str with leading and trailing slashes
  static function bundleURL() {
    $handles = \Bundle::option(Request::$route->bundle, 'handles');
    $handles = trim($handles, '/');
    return $handles === '' ? '/' : "/$handles/";
  }

  static function set($ns) {
    return static::$ns = $ns;
  }

  static function expand($name) {
    $ns = &static::$expanded[static::$ns];

    if (!isset($ns)) {
      @list($bundle, $prefix) = explode('::', static::$ns, 2);

      $prefix === '' or $ns = "$prefix-$ns";
      $bundle === '' or $ns = "$bundle::$ns";
    }

    return $ns.$name;
  }

  static function config($name) {
    return \Config::get(static::expand($name));
  }

  static function lang($name, $default = null) {
    if ($name instanceof \Lang) {
      return $name;
    } else {
      return \Lang::line(static::expand($name))->get(null, $default);
    }
  }
}