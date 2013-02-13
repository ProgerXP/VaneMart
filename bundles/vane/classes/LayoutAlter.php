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

  //= null if nothing found, LayoutItem
  static function findBy($path, $layout) {
    $path = \Px\arrize($path);

    if (!$layout) {
      return null;
    } elseif (!$path) {
      return $layout;
    } elseif (!property_exists($layout, 'blocks')) {
      $layout = get_class($layout);
      throw new Error("Invalid object [$layout] given to LayoutAlter->find().");
    }

    $matched = null;
    $classes = array_shift($path);

    if ($classes === array('*')) {
      $matched = head($layout->children());
    } else {
      $matched = array_first($layout, function ($i, $block) use (&$classes) {
        return ($block instanceof LayoutView) or
               ($classes and
                ($block instanceof LayoutBlocks) and
                LayoutItem::classesMatch($block->classes, $classes));
      });
    }

    if ($path and $matched instanceof LayoutView) {
      $matched = array_get($matched->blocks, join(' ', array_shift($path)));
    }

    return static::findBy($path, $matched);
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

  function alter(Layout $layout) {
    $matched = $this->findIn($layout);

    if (!$matched) {
      Log::info_Alter("No matching block [{$this->pathStr()}] to {$this->type}.");
    } else {
      switch ($block->type) {
      case 'append':
        $matched and $matched->blocks = array_merge($matched->blocks, $this->blocks);
        break;
      case 'prepend':
        $matched and $matched->blocks = array_merge($this->blocks, $matched->blocks);
        break;
      default:
        $matched->blocks = $this->blocks;
      }
    }

    return $matched;
  }

  function findIn($layout) {
    return static::findBy($this->path, $layout);
  }
}