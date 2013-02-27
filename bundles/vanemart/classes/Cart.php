<?php namespace VaneMart;

use Session;

class Cart {
  static function idFrom($id) {
    if (is_numeric($id) and $id > 0) {
      return (int) $id;
    } elseif ($id instanceof Eloquent) {
      return $id->id;
    }
  }

  //= hash of float id => qty
  static function all() {
    return (array) Session::get('cart_goods');
  }

  //= hash of int product ID
  static function ids() {
    return array_keys(statoc::all());
  }

  //= bool
  static function has($product = null) {
    if ($product) {
      return Session::get('cart_goods.'.static::idFrom($product)) > 0;
    } else {
      return (bool) static::all();
    }
  }

  //= float
  static function qty($product) {
    return (float) Session::get('cart_goods.'.static::idFrom($product));
  }

  //* $product Product, mixed see idFrom()
  //* $qty mixed - string allows both ',' and '.' separators for decimal part.
  //= null error, array (Product $product, float $qty)
  static function put($product, $qty = 1) {
    $product instanceof Product or $product = Product::find(static::idFrom($product));

    if (!$product) {
      $product = func_get_arg(0);
      Log::warn_Cart("Unknown product [$product] to place in cart.");
    } elseif (!$product->available) {
      Log::warn_Cart("Attempting to place unavailable product [{$product->title}].");
    } else {
      $qty = max(0, round(S::toFloat($qty), 2));
      $key = 'cart_goods.'.$product->id;

      if ($qty == 0) {
        Session::forget($key);
      } else {
        $qty = max($product->min, $qty);
        $product->fractable or $qty = ceil($qty);
        Session::put($key, $qty);
      }

      return array($product, $qty);
    }
  }

  static function clear($product = null) {
    $product and $product = '.'.static::idFrom($product);
    Session::forget('cart_goods'.$product);
  }

  //= int
  static function count() {
    return count(static::all());
  }

  //= float
  static function subtotal() {
    $sum = 0;
    $qty = static::all();
    $goods = Product::all(array_keys($qty));

    foreach ($goods as $product) {
      $sum += $qty[$product->id] * $product->retail;
    }

    return $sum;
  }

  //= null if subtotal is large enough, float minimum required sum
  static function isTooSmall($subtotal = null) {
    isset($subtotal) or $subtotal = static::subtotal();
    $min = \Config::get('vanemart::general.min_subtotal');
    if ($subtotal < $min) { return $min; }
  }
}