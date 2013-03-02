<?php namespace VaneMart;

class Product extends BaseModel {
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

  function variation() {
    return $this->has_one(__CLASS__, 'variation');
  }

  function group() {
    return $this->belongs_to(NS.'Group', 'group');
  }

  function files() {
    return FileListItem::relationTo($this);
  }

  // Ensures 'desc_html' field is filled with formatted 'desc' (product description).
  // If it isn't formats it and saves.
  function withHTML() {
    if (!$this->desc_html) {
      $this->desc_html = nl2br(HLEx::q($this->desc));
      $this->save();
    }

    return $this;
  }

  function to_array() {
    return parent::to_array() + array(
      'url'     => $this->url(),
    );
  }
}
Product::$table = \Config::get('vanemart::general.table_prefix').Product::$table;