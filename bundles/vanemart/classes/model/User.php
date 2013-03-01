<?php namespace VaneMart;

class User extends Eloquent {
  static $table = 'users';

  function addresses() {
    return $this->has_many(NS.'Addresses', 'user');
  }

  function orders() {
    return $this->has_many(NS.'Order', 'user');
  }

  function files() {
    return $this->has_many(NS.'File', 'uploader');
  }

  function posts() {
    return $this->has_many(NS.'Post', 'user');
  }

  function referee() {
    return $this->has_one(__CLASS__, 'referee');
  }

  function avatar($width) {
    if ($source = $this->avatar) {
      return Block_Thumb::url(compact('width', 'source'));
    }
  }

  function isPassword($str) {
    return \Hash::check($str, $this->password);
  }

  function set_password($str) {
    $this->set_attribute('password', \Hash::make($str));
  }
}
User::$table = \Config::get('vanemart::general.table_prefix').User::$table;