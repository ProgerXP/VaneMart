<?php namespace Vane;

// Represents a menu - a collection of MenuItems.
class Menu implements \IteratorAggregate, \Countable {
  //= null unassigned, str like 'main'
  protected $name;
  //= array of MenuItem
  protected $items = array();

  //= str URL, null if $url is empty
  static function expand($url = '/') {
    $url = trim($url);

    if ($url === '') {
      return null;
    } elseif (\URL::valid($url) or $url[0] === '/') {
      // absolute URL.
      return \url($url);
    } elseif (strrchr($url, '@') === false) {
      if (strpos($url, '::') === false) {
        // relative to current bundle's 'handles' or site root if none.
        return \url( Current::bundleURL().$url );
      } else {
        // named route.
        return \route(ltrim($url, ':'));
      }
    } elseif (strrchr(strrchr($url, '@'), '.') === false) {
      // controller[.sub]@[action]
      return \action($url);
    } else {
      // e-mail without 'mailto:' prefix: eml@example.com.
      return "mailto:$url";
    }
  }

  static function caption($name) {
    return Current::lang('menu.'.strtolower($name));
  }

  static function fromConfig($name) {
    return static::make( Current::config("menu.$name") )->name("$name");
  }

  static function make($items = array()) {
    return new static($items);
  }

  function __construct($items = array()) {
    $this->add($items);
  }

  function add($items, MenuItem $after = null) {
    foreach (arrize($items) as $key => $value) {
      if (!is_int($key)) {
        $this->addLink(is_scalar($value) ? trim($value) : $value, trim($key));
      } elseif (is_array($value)) {
        $this->addLink($value);
      } elseif ($value instanceof MenuItem) {
        $value->menu = $this;

        if ($after) {
          $i = array_search($after, $this->items, true);

          if ($i !== false) {
            array_splice($this->items, $i + 1, 0, array($value));
            $after = $value;
            continue;
          }
        }

        $this->items[] = $value;
      } elseif (is_scalar($value)) {
        @list($custom, $args) = explode(' ', trim($value), 2);
        $this->addCustom($custom, $args);
      } else {
        Log::warn_Menu('Invalid menu item type: '.gettype($value).'.');
      }
    }

    return $this;
  }

  function addCustom($custom, $args = '') {
    return $this->add( new MenuItem(compact('custom', 'args')) );
  }

  function addLink($item, $name = null) {
    $item = arrize($item, 'url') + compact('name');
    $item['url'] = static::expand($item['url']);

    if (!isset($item['caption'])) {
      $item['caption'] = "$name" === '' ? $url : static::caption($name);
    }

    return $this->add(new MenuItem($item));
  }

  function name($name = null) {
    isset($name) and $this->name = $name;
    return isset($name) ? $this : $this->name;
  }

  function filled() {
    do {
      $count = count($this->items);
      func('fill', $this->items);
    } while ($count !== count($this->items));

    return $this->items;
  }

  function getIterator() {
    return new \ArrayIterator($this->filled());
  }

  function count() {
    return count($this->items);
  }
}