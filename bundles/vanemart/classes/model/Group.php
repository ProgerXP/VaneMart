<?php namespace VaneMart;

class Group extends BaseModel {
  static $table = 'groups';
  static $hasURL = true;

  static function prettyOther(array $attrs) {
    foreach ($attrs as $name => &$value) {
      switch ($name) {
      case 'title':   $value = typography($value); break;
      }
    }

    return $attrs;
  }

  function pretty() {
    return $this->fill_raw( static::prettyOther($this->to_array()) );
  }

  function parent() {
    return $this->belongs_to(__CLASS__, 'parent');
  }

  function goods($deep = false) {
    if ($deep) {
      return Product::where_in('group', prop('id', $this->subgroups()));
    } else {
      return $this->has_many(NS.'Product', 'group');
    }
  }

  //* $depth int - if < 0 runs recursively, if 0 returns $this, if 1 - this'
  //  children, if 2 - them and their children, etc.
  //* $withSelf bool - if false omits $this from result.
  //= array of Group
  function subgroups($depth = -1, $withSelf = true) {
    $all = $next = array($this);

    while ($next and --$depth != 0) {
      $next = static::where_in('parent', prop('id', $next))->get();
      $all = array_merge($all, $next);
    }

    return $withSelf ? $all : S::slice($all);
  }

  //= Group with null parent
  function root() {
    if (!$this->parent) {
      return $this;
    } elseif ($parent = static::find($this->parent)) {
      return $parent->root();
    }
  }
}
Group::$table = \Config::get('vanemart::general.table_prefix').Group::$table;