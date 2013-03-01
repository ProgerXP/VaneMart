<?php namespace VaneMart;

class Post extends Eloquent {
  static $table = 'posts';
  static $typeModels = array('goods' => 'Product', 'orders' => 'Order');

  function object() {
    $class = array_get(static::$typeModels, $this->type);

    if ($class) {
      return $this->belongs_to(NS.$class, 'item');
    } else {
      Log::error_FileListItem("Unknown object type [{$this->type}].");
    }
  }

  function author() {
    return $this->has_one(NS.'User', 'author');
  }

  function parent() {
    return $this->belongs_to(__CLASS__, 'parent');
  }

  function files() {
    return FileListItem::relationTo($this);
  }

  // Ensures 'html' field is filled with formatted 'body' (file description).
  // If it isn't formats it and saves.
  function withHTML() {
    if (!$this->html) {
      $this->html = nl2br(HLEx::q($this->body));
      $this->save();
    }

    return $this;
  }
}
User::$table = \Config::get('vanemart::general.table_prefix').User::$table;