<?php namespace Vane;

// Represents a Menu item with its attributes - icon, caption, custom handler name, etc.
class MenuItem {
  public $menu;               //= null, Menu set by Menu
  public $name;               //= str item name like 'contacts'
  public $current = false;

  public $custom;             //= null standard item, str handler name
  public $args;               //= null, array arguments for custom handler
  public $filled = false;

  //= bool, null autodetect if $html or $caption is set
  public $visible;

  // If $custom is set properties below are ignored (filled by the handler).
  // $html is put after the $caption block of properties.
  public $html;               //= null, str

  public $caption;
  // If $caption is unset properties below are ignored.
  public $hint;
  public $icon;               //= null, str icon URL
  public $classes = array();  //= array of CSS classes
  public $url;                //= str target URL
  //= bool if it opens in new window, null autodetect for external $url
  public $popup;

  function __construct(array $props = array()) {
    foreach ($props as $prop => $value) { $this->$prop = $value; }
  }

  //= str, null if none
  function classes() {
    $classes = array_unique((array) $this->classes);
    $this->name and $classes[] = $this->name;
    $this->current and $classes[] = 'current';
    return $classes ? join(' ', $classes) : null;
  }

  function visible() {
    return isset($this->visible)
      ? $this->visible
      : (isset($this->html) or isset($this->caption));
  }

  function popup() {
    return isset($this->popup) ? $this->popup : HLEx::isExternal($this->url);
  }

  function toArray() {
    $props = array(
      'classes'           => $this->classes(),
      'visible'           => $this->visible(),
      'popup'             => $this->popup(),
    );

    return $props + array_except((array) $this, array('menu', 'filled'));
  }

  function fill() {
    if (!$this->filled) {
      $this->filled = true;

      if ($custom = $this->fullCustomID()) {
        $handler = Block::factory($custom);
        $func = 'menu_'.strtolower($action = Block::actionFrom($custom));

        if (method_exists($handler, $func)) {
          $handler->$func($this);
        } else {
          $this->html = $handler->execute($action, $this->argArray())->render();
        }
      }
    }

    return $this;
  }

  function argArray() {
    return  "{$this->args}" === '' ? array() : explode(' ', $this->args);
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