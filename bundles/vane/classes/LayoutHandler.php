<?php namespace Vane;

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
  function response($slugs = null) {
    $controller = $this->fullID();
    Route::references($controller, $slugs);
    return Block::execResponse($controller, $this->argArray(), $this->options);
  }

  function fullID() {
    return $this->controller.'@'.$this->action;
  }

  function argArray() {
    if (!is_array($this->args)) {
      $this->args = "{$this->args}" === '' ? array() : explode(' ', $this->args);
    }

    return $this->args;
  }

  function isServed() {
    return $this->controller === '!';
  }
}