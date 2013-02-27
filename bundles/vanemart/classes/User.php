<?php namespace VaneMart;

class User extends Eloquent {
  static $table = 'users';
}
User::$table = \Config::get('vanemart::general.table_prefix').User::$table;