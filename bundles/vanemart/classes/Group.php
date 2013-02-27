<?php namespace VaneMart;

class Group extends Eloquent {
  static $table = 'groups';

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

  function url() {
    "{$this->slug}" === '' or $slug = '-'.$this->slug;
    return route('vanemart::group', $this->id.$slug);
  }

  function goods($deep = false) {
    return Product::where_in('group', prop('id', $this->subgroups()));
  }

  //= array of Group
  function subgroups($depth = -1) {
    $all = $next = array($this);

    while ($next and --$depth != 0) {
      $next = static::where_in('parent', prop('id', $next))->get();
      $all = array_merge($all, $next);
    }

    return $all;
  }
}
Group::$table = \Config::get('vanemart::general.table_prefix').Group::$table;