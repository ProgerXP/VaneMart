<?php namespace VaneMart;

class ProductListItem extends BaseModel {
  static $table = 'goods_lists';

  function product() {
    return $this->belongs_to(NS.'Product', 'product');
  }
}
ProductListItem::$table = \Config::get('vanemart::general.table_prefix').ProductListItem::$table;