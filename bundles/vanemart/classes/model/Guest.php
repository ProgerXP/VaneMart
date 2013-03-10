<?php namespace VaneMart;

class Guest extends User implements \Vane\UserInterface {
  static $instance;

  static function singleton() {
    static::$instance or static::$instance = new static;
    return static::$instance;
  }

  function __construct() {
    $this->perms = \Config::get('vanemart::general.guest_perms');
  }
}