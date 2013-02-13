<?php namespace Vane;

// Base class representing layout building block, such as handler or nested group.
abstract class LayoutItem {
  public $classes = array();
  public $size;
  public $tag;
  public $style;
  public $attributes = array();

  static function splitClasses($classes, $separator = '.') {
    if (is_array($classes)) {
      return $classes;
    } else {
      return array_filter( explode($separator, "$classes") );
    }
  }

  static function classesMatch($subject, $mustHave) {
    return !array_diff(static::splitClasses($mustHave), static::splitClasses($subject));
  }

  function matches($classes) {
    return static::classesMatch($this->classes, $classes);
  }

  function extractTag(&$str) {
    if (strrchr($str, '/') !== false) {
      list($tag, $str) = explode('/', $str, 2);
      return $tag;
    }
  }

  function extractTagTo(&$tag, &$str) {
    $tag = $this->extractTag($str) ?: $tag;
  }

  function openTag() {
    if ($this->tag) {
      $classes = array_flip($this->classes);
      $size = $this->size;
      $width = '';

      if (!$size) {
        // do nothing.
      } elseif (ltrim($size, '0..9') === '') {
        $classes["span-$size"] = true;
      } elseif (ltrim($size[0], '0..9') === '') {
        $width = "width: $size";
      } else {
        $classes["size-$size"] = true;
      }

      $classes = array_keys($classes);

      if (($this instanceof LayoutBlocks) and $classes) {
        $classes[0] .= '-block';
      }

      return "\n".HLEx::tag($this->tag, $this->attributes + array(
        'class'           => join(' ', $classes),
        'style'           => trim("$width; ".$this->style, ' ;'),
      ));
    }
  }

  function closeTag() {
    if ($this->tag) { return '</'.$this->tag.">\n\n"; }
  }
}

// Represents handler producing contents for filling a layout block.
class LayoutHandler extends LayoutItem {
  public $controller;       //= str like 'bundle::ctl.subctl'
  public $action;           //= str can be empty
  public $args;             //= array, str to pass to the controller
  public $options;          //= hash

  function __construct($handler, $options = array()) {
    $this->extractTagTo($this->tag, $handler);

    list($handler, $args) = explode(' ', ltrim("$handler "), 2);
    $this->args = trim($args);

    if (strrchr($handler, '@') === false) {
      // ctl[.sub[....]] - dots separating not classes but controller's name.
      $this->controller = $handler;
    } else {
      // ctl[.sub[....]]@[actn][.class[.....]]
      $this->controller = strtok($handler, '@');
      $this->action = ''.strtok('.');
      $this->classes = static::splitClasses(strtok(null));
    }

    $this->options = \Px\arrize($options);
  }

  //= Laravel\Response
  function response(array $input = null) {
    return Block::execResponse($this->fullID(), $this->argArray(), $this->options);
  }

  function fullID() {
    return $this->controller.'@'.$this->action;
  }

  function argArray() {
    if (!is_array($this->args)) {
      $this->args = $this->args === '' ? array() : explode(' ', $this->args);
    }

    return $this->args;
  }

  function isServed() {
    return $this->controller === '!';
  }
}

// Represents group of layout blocks, both appending (|, -) and altering (=, etc.).
class LayoutBlocks extends LayoutItem implements \IteratorAggregate {
  public $tag = 'div';

  public $column = false;   //= true for '|block'
  public $row = false;      //= true for '-block'
  public $blocks;           //= array of mixed nested blocks (Layout'able)

  static function from($position, $blocks) {
    if ($position[0] === '|' or $position[0] === '-') {
      $type = $position[0] === '|' ? 'column' : 'row';
      $position = substr($position, 1);
    } else {
      $type = null;
    }

    $obj = new static($position, $blocks);
    $type and $obj->$type = true;
    return $obj;
  }

  function __construct($position, $blocks) {
    $this->extractTagTo($this->tag, $position);

    $this->classes = static::splitClasses( strtok($position, ' ') );
    $this->size = ''.strtok(null);

    $this->blocks = \Px\arrize($blocks);
  }

  //= array of LayoutItem
  function children() {
    return $this->blocks = Layout::parse($this->blocks, 'append');
  }

  function getIterator() {
    return new \ArrayIterator($this->children());
  }
}

class LayoutView extends LayoutBlocks {
  static function from($vars, $compat = null) {
    isset($compat) and $vars = $compat;
    return new static($vars);
  }

  function __construct($vars) {
    if ($vars instanceof \Laravel\View) {
      $vars = array('' => $vars) + $vars->data();
    }

    parent::__construct('', \Px\arrize($vars, ''));
  }

  //= View, null if no wrapping view is set
  function view(array $data = null) {
    $view = array_get($this->blocks, '');

    if (isset($view) and is_scalar($view)) {
      $view = \View::make($view);
    } elseif (! $view instanceof \Laravel\View) {
      return null;
    }

    foreach ($this as $name => $block) {
      if ($name !== '') {
        $view->with($name, LayoutRendering::make($block)->render($this)->join());
      }
    }

    return $view->with((array) $data);
  }
}