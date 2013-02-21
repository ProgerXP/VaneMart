<?php namespace Vane;

// Adds handy routines to standard Laravel's Log class.
class Log {
  static function __callStatic($method, $parameters) {
    @list($method, $object) = explode('_', $method, 2);
    $object and $object = " $object";
    return \Log::$method("Vane$object: ".reset($parameters));
  }
}