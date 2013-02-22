<?php namespace VaneMart;

class Product extends Eloquent {
  static $table = 'goods';

  //= str absolute path, null if located outside of local file system
  static function pathOfImage($str) {
    if (strrchr($str, ':') !== false) {
      return;
    } elseif ($str[0] === '/') {
      return path('public').ltrim($str, '\\/');
    } else {
      return \Bundle::path('vanemart').'public/'.$str;
    }
  }

  //= str absolute URL
  static function urlOfImage($str) {
    if (strrchr($str, ':') !== false) {
      return $str;
    } elseif ($str[0] === '/') {
      return url($str);
    } else {
      return asset($str);
    }
  }

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
}
Product::$table = \Bundle::option('vanemart', 'table_prefix', 'vm_').Product::$table;