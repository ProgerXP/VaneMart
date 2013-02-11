<?php namespace Vane;

// Holds transformation rules for altering a layout.
class LayoutAlter {
  static $typeChars = array('=' => 'replace', '+' => 'append', '^' => 'prepend');

  public $type = 'replace'; //= str 'replace', 'append', 'prepend'
  public $path = array();   //= array of array class names
  public $blocks;           //= array of mixed blocks to update with (Layout'able)

  static function chars() {
    return join(array_keys(static::$typeChars));
  }

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

  function pathStr() {
    $joiner = function ($classes) { return join('.', $classes); };
    return join(' ', array_map($joiner, $this->path));
  }

  function findIn($layout, array $path = null) {
    if (!$layout) {
      return null;
    } elseif (empty($path) and $path !== null) {
      return $layout;
    } elseif (!property_exists($layout, 'blocks')) {
      $layout = get_class($layout);
      throw new Error("Invalid object [$layout] given to LayoutAlter->find().");
    }

    $matched = null;
    $path === null and $path = $this->path;

    if ($classes = head($path)) {
      foreach ($layout as $block) {
        if ($block instanceof LayoutBlocks and
            LayoutItem::classesMatch($block->classes, $classes)) {
          $matched = $block;
          break;
        }
      }
    } else {
      $matched = head($layout->children());
    }

    return $this->findIn($matched, array_slice($path, 1));
  }
}