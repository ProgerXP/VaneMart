<?php namespace Vane;

// Container for multiple layouts, such as those defined in config/layouts.php.
// Allows convenient alteration of one or all layouts at once using regular syntax.
class Layouts implements \IteratorAggregate, \Countable, \ArrayAccess {
  //= hash of array layout definitions
  public $layouts = array();

  static function at($envFile) {
    return static::in(dirname($envFile).'/../layouts.php');
  }

  static function in($file) {
    if (!is_file($file)) {
      throw new \InvalidArgumentException("File [$file] to load Layouts from doesn't exist.");
    }

    return new static(include $file);
  }

  function __construct(array $layouts) {
    $this->layouts = $layouts;
  }

  // function ( [$name,] $rules )
  //* $name null 'view' layout, str - if omitted all contained views are altered.
  //* $rules array - alteration rules like array('+block sub' => 'my@actn').
  function alter($name, $rules = null) {
    if (func_num_args() == 0) {
      foreach ($this->layouts as $name => $value) { $this->alter($name, $rules); }
    } elseif ($this->has($name)) {
      $this->parse($name);
      $this->layouts[$name]->alter($rules);
    }

    return $this;
  }

  function parse($name = null) {
    if (func_num_args() == 0) {
      foreach ($this->layouts as $name => &$value) { $this->parse($name); }
    } elseif (!($this->layouts[$name] instanceof Layout)) {
      $this->layouts[$name] = Layout::make($this->layouts[$name]);
    }

    return $this;
  }

  function has($name) {
    return isset($this->layouts[$name]);
  }

  // function ()
  // Return all layouts. Note that some might be parsed (Layout objects) and some
  // might be raw arrays. If you want to parse them all call parse() first.
  //= hash of mixed
  //
  // function ($name)
  // Get single layout, either parsed or raw.
  //= Layout, array
  function get($name = null) {
    return func_num_args() ? $this->layouts[$name] : $this->layouts;
  }

  //= array of Layout if $name omitted, Layout parsed layout
  function parsed($name = null) {
    if (func_num_args()) {
      return $this->parsed($name)->get($name);
    } else {
      return $this->parsed()->get();
    }
  }

  function getIterator() {
    return new \ArrayIterator($this->parsed());
  }

  function count() {
    return count($this->layouts);
  }

  function offsetGet($name) {
    return S::pickFlat($this->layouts, $name);
  }

  function offsetExists($name) {
    return $this->has($name);
  }

  function offsetSet($name, $layout) {
    $this->value[$name] = $layout;
  }

  function offsetUnset($name) {
    unset( $this->value[$name] );
  }
}