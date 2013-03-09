<?php namespace Vane;

interface UserInterface {
  // can() should always return true if $feature is '' and false if $feature is
  // a scalar/null but not a string.
  //
  //* $feature str - case-insensitive. Can be grouped with '.'. Can be '*' to test
  //  for special "superuser" case (user that can do anythign with no exceptions).
  //= bool
  //
  //? can('post')
  //? can('order.edit')
  //? can('admin.*')
  //? can('')               //=> true
  //? can(null)             //=> false
  function can($feature);
}