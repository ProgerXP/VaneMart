<?php namespace Vane;

// Represents handler producing contents for filling a layout block.
class LayoutHandler extends LayoutItem {
  public $controller;       //= str like 'bundle::ctl.subctl'
  public $action;           //= str can be empty
  public $args;             //= array, str to pass to the controller
  public $options;          //= null use global input, hash
  public $layout;           //= null, Layout main layout that's being rendered

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

    $this->options = $options === '!' ? null : arrize($options);
  }

  //= Laravel\Response
  function response($slugs = null) {
    $controller = $this->fullID();
    Route::references($controller, $slugs);

    return Block::execCustom($controller, array(
      'args'              => $this->argArray($slugs),
      'input'             => $this->options,
      'layout'            => $this->layout,
      'response'          => true,
      'return'            => 'response',
    ));
  }

  function fullID() {
    return $this->controller.'@'.$this->action;
  }

  function argArray($slugs = null) {
    $args = $this->args;

    if (!is_array($args)) {
      $args = $this->args = "$args" === '' ? array() : explode(' ', $args);
    }

    if ($slugs) {
      foreach ($args as &$arg) { Route::references($arg, $slugs); }
    }

    return $args;
  }

  function isServed() {
    return $this->controller === '!';
  }
}