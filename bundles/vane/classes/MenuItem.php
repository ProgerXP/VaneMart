<?php namespace Vane;

// Represents a Menu item with its attributes - icon, caption, custom handler name, etc.
class MenuItem {
  public $menu;               //= null, Menu set by Menu
  public $index;              //= null, int set by Menu
  public $current = false;

  public $custom;             //= null standard item, str handler name
  public $args;               //= null, array arguments for custom handler
  public $filled = false;

  // If $custom is set properties below are ignored.
  public $html;               //= null, str

  // If $html is set properties below are ignored.
  public $caption;
  public $hint;
  public $icon;               //= null, str icon URL
  public $classes = array();  //= array of CSS classes
  public $url;                //= str target URL
  public $popup = false;      //= bool if it opens in new window

  function __construct(array $props = array()) {
    foreach ($props as $prop => $value) { $this->$prop = $value; }
  }

  function visible() {
    return isset($this->html) or isset($this->caption);
  }

  function fill() {
    if (!$this->filled) {
      $this->filled = true;

      if ($custom = $this->fullCustomID()) {
        $handler = Block::factory($custom);
        $func = 'menu_'.strtolower($action = Block::actionFrom($custom));

        if (method_exists($handler, $func)) {
          $handler->func($this);
        } else {
          $this->html = $handler->execute($action, explode(' ', $this->args));
        }
      }
    }

    return $this;
  }

  // Expands controller-less notations like vane::user into vane::menuHandlers@user.
  //= str
  function fullCustomID($custom = null) {
    isset($custom) or $custom = $this->custom;

    if ($custom and strrchr($custom, '@') === false) {
      @list($bundle, $action) = explode('::', $custom, 2);

      if ($action === null) {
        $action = $bundle;
        $bundle = 'vane';
      }

      $custom = "$bundle::menuHandlers@$action";
    }

    return $custom;
  }
}