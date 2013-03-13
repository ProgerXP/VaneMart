<?php namespace VaneMart;

use Session;

class Cart {
  //* $id str, int, Eloquent model like Product
  //= int, null
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

  //= array of Product with set 'qty' and 'price' attributes
  static function models() {
    $all = static::all();

    return S(Product::all(array_keys($all)), function ($product) use (&$all) {
      return $product->fill_raw(array(
        'qty'             => $all[$product->id],
        'sum'             => $all[$product->id] * $product->retail,
      ));
    });
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

  // Places new product or removes it from the cart. Normalizes $qty and does other
  // checks (for product availability, fractability and minimum quantity).
  //
  //* $product Product, mixed see idFrom()
  //* $qty mixed - string allows both ',' and '.' separators for decimal part.
  //= null error, Product
  //
  //? put(5, 11.2);       // places 11.2 portions of product with ID 5
  //? put($model, 0);     // removes $model->id from cart
  //? put($model, -2);    // the same
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

      return $product;
    }
  }

  // If $product is not falsy removes it from cart (if it exists), if it's == false
  // (null, 0, '', etc.) clears cart of all goods.
  static function clear($product = null) {
    $product and $product = '.'.static::idFrom($product);
    Session::forget('cart_goods'.$product);
  }

  //= int number of goods in cart (not their quantities)
  static function count() {
    return count(static::all());
  }

  //= float sum of all cart goods multiplied by their quantities
  static function subtotal() {
    $sum = 0;
    $qty = static::all();
    $goods = Product::all(array_keys($qty));

    foreach ($goods as $product) {
      $sum += $qty[$product->id] * $product->retail;
    }

    return $sum;
  }

  //= null if subtotal is large enough
  //= float no - minimum required sum according to site config
  static function isTooSmall($subtotal = null) {
    isset($subtotal) or $subtotal = static::subtotal();
    $min = \Config::get('vanemart::general.min_subtotal');
    if ($subtotal < $min) { return $min; }
  }

  static function summary($html = false) {
    $items = Str::langNum('general.goods', static::count(), $html);
    $sum = Str::langNum('general.price', static::subtotal(), $html);

    if ($html) {
      return HLEx::lang('cart.summary', compact('items', 'sum'), false);
    } else {
      return __('vanemart::cart.summary', compact('items', 'sum'))->get();
    }
  }
}