<?php namespace VaneMart;

class Product extends Eloquent {
  static $table = 'goods';

  static function prettyOther(array $attrs) {
    foreach ($attrs as $name => &$value) {
      switch ($name) {
      case 'title':
      case 'maker':   $value = typography($value); break;
      case 'country': $value = S::capitalize(trim($value)); break;
      case 'desc':    $value = prettyText($value); break;
      }
    }

    return $attrs;
  }

  function pretty() {
    return $this->fill_raw( static::prettyOther($this->to_array()) );
  }

  function url() {
    "{$this->slug}" === '' or $slug = '-'.$this->slug;
    return route('vanemart::product', $this->id.$slug);
  }

  function image($width = null) {
    if (!func_num_args()) {
      return $this->image ? File::find($this->image) : null;
    } elseif ($image = $this->image()) {
      $source = $image->file();
      return Block_Thumb::url(compact('width', 'source'));
    }
  }

  function group() {
    return $this->belongs_to(NS.'Group');
  }
}
Product::$table = \Config::get('vanemart::general.table_prefix').Product::$table;