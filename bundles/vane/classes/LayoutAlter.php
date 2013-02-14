<?php namespace Vane;

// Holds transformation rules for altering a layout.
class LayoutAlter {
  static $typeChars = array('=' => 'replace', '+' => 'append', '^' => 'prepend');

  public $type = 'replace'; //= str alteration type ('replace', 'append', 'prepend')
  public $path = array();   //= array of array class names of target block to alter
  public $blocks;           //= array of mixed blocks to update with

  //= str character prefixes representing alteration $type's
  static function chars() {
    return join(array_keys(static::$typeChars));
  }

  // Creates an altering object handling leading type prefixes, if present.
  //* $position str - path of block to alter: 'class[.class[....]][ class[....]][...]'.
  //  with optional leading type prefixes ('=', '+', '^').
  //= LayoutAlter
  static function from($position, $blocks) {
    $obj = new static(ltrim($position, static::chars()), $blocks);
    $obj->type = array_get(static::$typeChars, $position[0], $obj->type);
    return $obj;
  }

  function __construct($position, $blocks) {
    foreach (LayoutItem::splitClasses($position, ' ') as $classes) {
      $this->path[] = LayoutItem::splitClasses($classes);
    }

    $this->blocks = (array) $blocks;
  }

  //= str 'class.sub cl-2 cl-3.sub ...'
  function pathStr() {
    $joiner = function ($classes) { return join('.', $classes); };
    return join(' ', array_map($joiner, $this->path));
  }

  // Alters given layout's block using rules defined in this object.
  function alter(Layout $layout) {
    $matched = $layout->find($this->path);

    if ($matched) {
      $this->mergeInto($matched->blocks);
    } elseif (count($this->path) == 2 and !reset($this->path) and
              $view = $layout->find('')) {
      $view->add(array( join('.', end($this->path)) => $this->blocks ));
    } else {
      Log::info_Alter("No matching block [{$this->pathStr()}] to {$this->type}.");
    }

    return $this;
  }

  // Puts new blocks into $dest according to alteration type (replace, append, prepend).
  //* $dest array, mixed replaced with $this->blocks regardless of $this->type.
  function mergeInto(&$dest) {
    if (is_array($dest)) {
      switch ($this->type) {
      case 'append':    $dest = array_merge($dest, $this->blocks); break;
      case 'prepend':   $dest = array_merge($this->blocks, $dest); break;
      default:          $dest = $this->blocks; break;
      }
    } else {
      $dest = $this->blocks;
    }

    return $this;
  }
}