<?php namespace Vane;

// Adds handy routines to standard Laravel's Log class.
class Log {
  //= str message that was written
  static function __callStatic($method, $parameters) {
    @list($method, $object) = explode('_', $method, 2);
    $object and $object = " $object";
    \Log::$method($msg = "Vane$object: ".reset($parameters));
    return $msg;
  }
}