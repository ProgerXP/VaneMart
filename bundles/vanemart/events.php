<?php namespace VaneMart;

use Px\Query;
use Vane\Mail;
use Vane\Current;

/*-----------------------------------------------------------------------
| GENERAL LISTENERS
|----------------------------------------------------------------------*/

// Fires to get HTML representation of user message $text (can be used to attach
// BB-code processor, Markdown, wiki, etc.).
//
//= str HTML
Event::listen(VANE_NS.'format.post', function (&$text) {
  return nl2br(HLEx::q($text));
});

/*-----------------------------------------------------------------------
| FILE MODEL
|----------------------------------------------------------------------*/

// Fired to determine local path for storing File data.
//
//* $path str - can be '' to get root folder's path; guaranteed to be safe (no '..').
//
//= str absolute path
Event::listen(VANE_NS.'file.path', function ($path) {
  if ("$path" === '' or strpbrk($path[0], '\\/') === false) {
    return \Bundle::path('vanemart').'storage/files/'.$path;
  } else {
    return $path;
  }
});

// Fired when a local path for storing new File's data needs to be generated.
//
//* $name str - user-supplied custom name, e.g. used on upload.
//
//= str non-existing file system path
Event::listen(VANE_NS.'file.new.path', function ($name) {
  list($name, $ext) = S::chopTo('.', $name);

  $name = substr(Str::slug($name), 0, 50);
  "$ext" === '' or $ext = '.'.Str::slug($ext);
  File::safeName($name.$ext);

  $base = File::storage();
  $result = "$base$name[0]/$name$ext";

  if (file_exists($result)) {
    $i = 1;
    do {
      $result = "$base$name[0]/$name-".++$i.$ext;
    } while (file_exists($result));
  }

  return $result;
});

// Fired when a new unique File ID (UNSIGNED INT) needs to be generated.
// Make it as random as possible.
//
//= int non-zero
Event::listen(VANE_NS.'file.new.id', function () {
  do {
    if (function_exists('openssl_random_pseudo_bytes')) {
      $bytes = S( str_split(openssl_random_pseudo_bytes(4)), 'ord' );
    } else {
      $bytes = array(mt_rand(), mt_rand(), mt_rand(), mt_rand());
    }

    $bytes[0] %= 4;
    $id = join($bytes);
  } while (File::find($id));

  return $id;
});

// Fired when a new (prepared) File model needs to be inserted into the database.
//
//= bool
Event::listen(VANE_NS.'file.insert', function (File &$file) {
  return $file->save();
});

// Fired once a new File has been successfully placed.
Event::listen(VANE_NS.'file.inserted', function (File $file) {
});

// Fired when a File instance has been used in another context thus its reference
// counter should be updated.
//
//= bool
Event::listen(VANE_NS.'file.used', function (File $file) {
  $file->uses += 1;
  return $file->save();
});

// Fired once a File has been deleted. Note that it might not have existed entirely
// (e.g. disk data can be missing if it has been cleaned up) so don't expect it.
Event::listen(VANE_NS.'file.deleted', function (File $file) {
});

/*-----------------------------------------------------------------------
| ORDER MODEL
|----------------------------------------------------------------------*/

// Fired when a new Order code (for anonymous read-only review) needs to be generated.
//
//= str
Event::listen(VANE_NS.'order.new.password', function () {
  return Str::password(array(
    'length'              => 10,
    'symbols'             => 0,
    'capitals'            => 3,
    'digits'              => 3,
  ));
});

// Fired to populate $lines with a summary of changes made to Order's fields ("dirty").
Event::listen(VANE_NS.'order.change_lines', function (array &$lines, Order $order) {
  foreach ($order->get_dirty() as $field => $value) {
    if (!in_array($field, Order::$ignoreLogFields)) {
      $vars = array(
        'field'       => __("vanemart::field.$field")->get(),
        'old'         => trim($order->original[$field]),
        'new'         => $value,
      );

      if ($field === 'status') {
        $vars['old'] = __("vanemart::order.status.$vars[old]")->get();
        $vars['new'] = __("vanemart::order.status.$vars[new]")->get();
      }

      $key = $field;
      while (isset($lines[$key])) { $key .= '$'; }

      $type = $vars['old'] === '' ? 'add' : ($value === '' ? 'delete' : 'set');
      $lines[$key] = __("vanemart::order.set.line.$type", $vars);
    }
  }
});

// Fired to determine if Order "belongs" to given User - i.e. should be visible
// in his order list with default filter settings. For managers this includes all
// orders they manage, for regular users - orders that they have placed earlier.
//
// If it returns exactly false (but not null or other) Order doesn't belong to User.
//
//* $user User, null for guest
Event::listen(VANE_NS.'order.belongs', function (Order $order, User $user = null) {
  if (!$user) {
    return false;
  }

  if ($order->get_user() != $user->id and
      (!$user->can('manager') or $order->get_manager() != $user->id)) {
    return false;
  }
});

// Fired when a new (prepared) Order model needs to be inserted into the database.
// As with all Event::insertModel()'s '*.insert' hook make sure to push your own in
// front of this one as it will return non-null result and skip the rest of callbacks.
//
//= bool
Event::listen(VANE_NS.'order.insert', function (Order &$order) {
  return $order->save();
});

// Fired once a new Order has been successfully placed.
Event::listen(VANE_NS.'order.inserted', function (Order $order) {
});

// Fired to determine if given Order can be viewed by given User (or guest if null).
//
//= bool
Event::listen(VANE_NS.'order.viewable', function (Order $order, User $user = null) {
  if ($user) {
    $field = $user->can('manager') ? 'manager' : 'user';

    if ($order->{"get_$field"}() != $user->id) {
      return false;
    }
  }
});

// Fired to constrain/tweak an Order-model query based on user input.
Event::listen(VANE_NS.'order.list.query', function (Query $query, Block_Order $block, array &$can) {
  // '' - of self, '0' - of everyone, array of 'id'
  $ofMgr = $block->arrayInput('manager', '0');
  // '' - don't filter, '0' - invalid (404), array of 'id'
  $ofUser = $block->arrayInput('user');

  $globalMgr = ($block->can('manager') and $block->can('order.list.all'));
  $globalMgr or $ofMgr = '';
  $ofMgr === '' and $ofMgr = $block->user()->id;

  $ofMgr and $query->where(function ($query) use ($ofMgr) {
    $query->where_in('user', (array) $ofMgr)
          ->or_where_in('manager', (array) $ofMgr);
  });

  $ofUser and $query->where_in('user', (array) $ofUser);

  $can['ofMgr'] = !$globalMgr ? false : $ofMgr;
  $can['ofUser'] = !$block->can('manager') ? false : $ofUser;
});

Event::listen(VANE_NS.'order.list.query', function (Query $query, Block_Order $block, array &$can) {
  // '' or '!' - don't filter, '!xxx' - all but 'xxx', array of 'xxx'
  $ofStatus = $block->arrayInput('status', '');

  if ($ofStatus === '!' or !$ofStatus) {
    // Don't filter.
  } elseif (is_string($ofStatus) and $ofStatus[0] === '!') {
    $query->where('status', '!=', substr($ofStatus, 1));
  } else {
    $query->where_in('status', $ofStatus);
  }

  $can['ofStatus'] = $ofStatus;
});

Event::listen(VANE_NS.'order.list.query', function (Query $query, Block_Order $block, array &$can) {
  $sorted = $block->in('sort', '');
  $sorted or $query
    ->order_by('updated_at', 'desc')
    ->order_by('created_at', 'desc');

  $filter = (array) $block->in('filter', '');
  $query->commonList(array('paginate' => false, 'filter' => $filter));

  $can['sort'] = ($sorted ?: true);
  $can['desc'] = (string) $block->in('desc', '0') ?: '0';

  $fields = array('id', 'sum', 'address', 'phone', 'notes');
  $fields = userFields($fields, 'order');
  $defaults = S::combine($fields, null);
  $can['filter'] = array_intersect_key($block->in(), $defaults) + $defaults;
});

Event::listen(VANE_NS.'order.list.query', function (Query $query, Block_Order $block, array &$can) {
  if ($date = $block->in('date', '')) {
    $query->where(function ($query) use ($date) {
      if (strpbrk($date[0], '<>')) {
        $op = $date[0];
        $date = substr($date, 1);
      } else {
        $op = '=';
      }

      is_numeric($date) or $date = strtotime($date);
      $date = with(new \DateTime)->setTimestamp((int) $date);
      $date = strtok($date->format($query->grammar->datetime), ' ');

      $query->where(\DB::raw('DATE(created_at)'), $op, $date)
            ->or_where(\DB::raw('DATE(updated_at)'), $op, $date);
    });
  }

  if ($names = $block->in('names', '')) {
    $query->where(function ($query) use ($names) {
      $query->raw_where('LOCATE(?, name) = 1', array($names))
            ->raw_or_where('LOCATE(?, surname) = 1', array($names));
    });
  }

  $can['filter'] += compact('date', 'names');
});

// Fired to add extra attributes to Orders retrieved with order.list.query.
//
//* $orders array of Order
Event::listen(VANE_NS.'order.list.populate', function (array &$orders, Block_Order $block, array &$vars) {
  $counts = OrderProduct
    ::where_in('order', prop('id', $orders))
    ->group_by('order')
    ->select(array('*', \DB::raw('COUNT(1) AS count')))
    ->get();

  $counts = S::keys($counts, '?->order');

  foreach ($orders as $order) {
    $order->count = $counts[$order->id]->count;
  }
});

Event::listen(VANE_NS.'order.list.populate', function (array &$orders, Block_Order $block, array &$vars) {
  $recentTime = time() - 3*24*3600;

  foreach ($orders as $order) {
    $order->recent = $order->updated_at >= $recentTime;
  }
});

// Fired to do final preparations of variables returned to client/given to template.
Event::listen(VANE_NS.'order.list.populate', function (array &$vars, Block_Order $block) {
});

/*-----------------------------------------------------------------------
| POST MODEL
|----------------------------------------------------------------------*/

// Fires when a File has been attached to a post. Occurs after successfully creating
// all required database entries.
Event::listen(VANE_NS.'post.attached', function (Post $post, File $file) {
});

/*-----------------------------------------------------------------------
| USER MODEL
|----------------------------------------------------------------------*/

// Fired when a new User password needs to be generated.
//
//= str
Event::listen(VANE_NS.'user.new.password', function () {
  return Str::password(\Config::get('vanemart::password'));
});

// Fired when a new (prepared) User model needs to be inserted into the database.
//
//= bool
Event::listen(VANE_NS.'user.insert', function (User &$user) {
  return $user->save();
});

// Fired once a new User has been successfully placed.
Event::listen(VANE_NS.'user.inserted', function (User $user) {
});

// Fired to generate e-mail's recipient string, usually in "name<e@ma.il>" form.
//
//= str like 'Name Surname<e@ma.il>' or just 'e@ma.il'
Event::listen(VANE_NS.'user.recipient', function (User $user) {
  return $user->name.' '.$user->surname.'<'.$user->email.'>';
});

/*-----------------------------------------------------------------------
| CART OPERATION
|----------------------------------------------------------------------*/

// Fired to determine if current cart's contents is enough to place an order and
// to get the minimally required subtotal if it's not.
//
//= int, float, mixed otherwise (order can be placed)
Event::listen(VANE_NS.'cart.is_small', function (&$subtotal) {
  $min = Current::config('general.min_subtotal');
  if ($min > $subtotal) { return $min; }
});

// Fired when a new item was put into cart. $oldQty be null unless $product was
// present in cart and only its quantity has changed.
Event::listen(VANE_NS.'cart.added', function (Product $product, &$oldQty) {
});

// Fired when a new item was removed from cart.
Event::listen(VANE_NS.'cart.removed', function (Product $product, &$oldQty) {
});

// Fired when given $product (ID) is removed from cart or entire cart is cleared (null).
Event::listen(VANE_NS.'cart.cleared', function ($product) {
});

/*-----------------------------------------------------------------------
| CART BLOCK
|----------------------------------------------------------------------*/

Event::preview(VANE_NS.'cart.from_skus', function (array &$models, &$skus) {
  // Сonverts user-input 'SKU000 SKU001 ...' list into a hash of 'sku' => qty.
  if (!is_array($skus)) {
    $skus = array_filter(preg_split('/\s+/', trim($skus)));
    $skus = array_count_values($skus);
  }
});

// Fired to transform $skus (hash of 'sku' => int qty) into Product models with
// 'sku' attribute set.
Event::listen(VANE_NS.'cart.from_skus', function (array &$models, &$skus) {
  $goods = Product::where_in('sku', array_keys($skus))->get();

  foreach ($goods as $model) {
    $model->qty = $skus[$model->sku];
    $models[] = $model;
  }
});

/*-----------------------------------------------------------------------
| CHECKOUT BLOCK
|----------------------------------------------------------------------*/

// Fired to determine if the visitor can perform checkout with his current cart.
// If it returns exactly false checking out is prohibited.
//
//= bool
Event::listen(VANE_NS.'checkout.can', function (Block_Checkout $block) {
  if ($min = Cart::isTooSmall()) {
    $block->status('small', array(
      'min'     => Str::langNum('general.price', $min),
      'total'   => Str::langNum('general.price', Cart::subtotal()),
    ));

    return false;
  }
});

// Fired when a user has been registered on demand after successfully placing an order.
//
//* $options hash - 'password' str, 'order' Order, 'block' Block_Checkout.
Event::listen(VANE_NS.'checkout.reg_user', function (User $user, array &$options) {
  $view = Current::expand('mail.user.reg_on_order');
  $vars = array_only($options, 'password') + $user->to_array();

  Mail::sendTo($user->emailRecipient(), $view, $vars);
});

// Fired when an existing user has successfully placed a new order.
//
//* $options hash - 'order' Order, 'block' Block_Checkout.
Event::listen(VANE_NS.'checkout.old_user', function (User $user, array &$options) {
});

// Fired when an order is performed. Follows either checkout.reg_user or
// checkout.old_user events. $options are the same as of checkout.old_user.
Event::listen(VANE_NS.'checkout.done', function (User $user, array &$options) {
  $orderInfo = function ($block, Order $order) {
    $response = \Vane\Block::execCustom($block, array(
      'args'              => $order->id,
      'input'             => array('code' => $order->password, 'grouped' => 0),
      'prepare'           => function ($block) { $block->user = false; },
      'response'          => true,
      'return'            => 'response',
    ));

    return $response->render();
  };

  $view = Current::expand('mail.checkout.user');

  Mail::sendTo($user->emailRecipient(), $view, array(
    'user'        => $user->to_array(),
    'order'       => $options['order']->to_array(),
    'orderHTML'   => $orderInfo('VaneMart::order@show', $options['order']),
    'goodsHTML'   => $orderInfo('VaneMart::order@goods', $options['order']),
  ));
});

Event::listen(VANE_NS.'checkout.done', function (User $user, array &$options) {
  Cart::clear();
});

/*-----------------------------------------------------------------------
| FILE BLOCK
|----------------------------------------------------------------------*/

// Fired when a file download has been requested. If $file's attributes were changed
// during event processing the model will be saved automatically.
// If it returns non-null it's considered an exceptional case (e.g. access problem)
// and returned without initiating the download by firing file.dl.response.
//
//* $path str - absolute local file system path to stored File data.
//
//= null, mixed override normal file serving (passed through to DoubleEdge)
Event::listen(VANE_NS.'file.dl.before', function (&$path, File &$file, Block_File $block) {
  if (!is_file($path)) {
    Log::error_File("Missing stored data file [$path] of $file.");
    return E_NONE;
  } elseif (filesize($path) != $file->size) {
    $msg = "Size of local file [$path] is ".filesize($path)." bytes - this".
           " doesn't match the value stored in database ({$file->size} bytes).".
           " The file might have been corrupted or changed directly on disk.";
    Log::error_File($msg);
  }
});

// Fired to construct DoubleEdge response (e.g. Response or Redirect object or an E_*
// int code) to serve the actual file download. If $file model requires changes
// it's recommended to do so in file.dl.before since it's saved once after calling
// all event listeners that might also change it.
//
//= mixed passed through to DoubleEdge
Event::listen(VANE_NS.'file.dl.response', function (&$path, File $file, Block_File $block) {
  return Response::download($path, $file->name, array(
    'Etag'            => $file->md5,
    'Last-Modified'   => gmdate('D, d M Y H:i:s', filemtime($file->file())).' GMT',
    'Content-Type'    => $file->mime ?: 'application/octet-stream',
  ));
});

/*-----------------------------------------------------------------------
| ORDER BLOCK
|----------------------------------------------------------------------*/

// Fired to determine if given Order is accessible in given Block's environment (user,
// input, etc.). If returns exactly false access is denied.
//
//= bool
Event::listen(VANE_NS.'order.accessible', function (Order $order, Block_Order $block) {
  if (!$block->can('order.show.all') and
      !$order->isOf($block->user(false)) and
      $order->password !== $block->in('code', '')) {
    return false;
  }
});

// Fired to determine if given Order is editable in given Block's environment (user,
// input, etc.). If returns exactly false access is denied.
//
//= bool
Event::listen(VANE_NS.'order.editable', function (Order $order, Block_Order $block) {
  if (!$block->can('order.edit.all')) {
    if ((!$block->can('manager') and !$block->can('order.edit.self')) or
        !$order->isOf($block->user(false))) {
      return false;
    }
  }
});

/*-----------------------------------------------------------------------
| POST BLOCK
|----------------------------------------------------------------------*/

// Fired to return a default (automatic) post text when submitting a form without one.
// If doesn't return a non-empty string post form is returned with "Empty body" error.
//
//* $options hash - 'block' Block_Post, 'type' str, 'object' int/mixed.
//
//= str body message to post
Event::listen(VANE_NS.'post.bodyless', function (array $options) {
  $block = $options['block'];

  if ($block->userCanAttach() and $names = $block->uploadedFileNames()) {
    return __(Current::expand('post.add.bodyless_fmsg'), array(
      'text'            => Str::langNum('post.add.bodyless_ftext', count($names)),
      'files'           => join(', ', $names),
    ))->get();
  }
});

// Fired when a new (prepared) Post model needs to be inserted into the database.
//
//= bool
Event::listen(VANE_NS.'post.insert', function (Post &$post) {
  return $post->save();
});

// Fired once a new Post has been successfully placed.
Event::listen(VANE_NS.'post.inserted', function (Post $post) {
});

// Fired at the point when attachments, if any, should be added to the created Post.
// Any produced exception will cause the Post to be deleted to maintain integrity.
//
//* $options hash - the same as in post.bodyless plus 'post' Post.
Event::listen(VANE_NS.'post.attach', function (array &$models, array $options) {
  $block = $options['block'];

  if ($block->userCanAttach()) {
    if ($block->can('post.attach.limitless')) {
      $max = -1;
    } else {
      $max = \Config::get('vanemart::post.add.max_attaching_files', 10);
    }

    $attached = $options['post']->attach('attach', $max, $block->user());
    $models = array_merge($models, $attached);
  }
});

// Fired after successfully adding a post with attachments and all required database
// and other changes.
//
//* $options hash - the same as in post.attach plus 'attachments' hash of File.
Event::listen(VANE_NS.'post.added', function (array $options) {
  extract($options, EXTR_SKIP);
  $poster = $block->user(false);

  $to = User::all(arrize( \Vane\Current::config('general.post_notify_users') ));

  if ($type === 'order' and $order = Order::find($object)) {
    foreach (array('user', 'manager') as $field) {
      $to[] = $order->$field()->first();
    }
  }

  foreach ($to as $recipient) {
    if (!$poster or $poster->email != $recipient->email) {
      \Vane\Mail::sendTo($recipient->emailRecipient(), 'vanemart::mail.order.post', array(
        'order'         => $order->to_array(),
        'user'          => $block->user()->to_array(),
        'recipient'     => $recipient->to_array(),
        'post'          => $post->to_array(),
        'files'         => func('to_array', $attachments),
      ));
    }
  }
});

// Fired after post.added if post is attached to an object.
//
//* $object Eloquent
//* $options hash - the same as in post.added.
Event::listen(VANE_NS.'post.object_ref', function (&$object, array $options) {
  $object->updated_at = new \DateTime;
});

/*-----------------------------------------------------------------------
| THUMB BLOCK
|----------------------------------------------------------------------*/

// Fired after creating and configuring ThumbGen instance for custom setup.
//
//* $options hash - 'input' hash, 'options' hash (defaults to config/thumb.php).
Event::listen(VANE_NS.'thumb.configure', function (\ThumbGen $thumb, array $options) {
});

/*-----------------------------------------------------------------------
| USER BLOCK
|----------------------------------------------------------------------*/

// Fired when a new (prepared) User model needs to be inserted into the database.
//
//= bool
Event::listen(VANE_NS.'user.insert', function (User &$user) {
  return $user->save();
});

// Fired once a new User has been successfully placed.
Event::listen(VANE_NS.'user.inserted', function (User $user) {
});

// Fired after a User has successfully logged into the system. Doesn't occur for
// automatic/administrative logins, only when he has manually entered via regular
// login form.
Event::listen(VANE_NS.'user.login', function (&$response, User $user, Block_User $block) {
});

// Fired after a User manually logs out from the system.
Event::listen(VANE_NS.'user.logout', function (&$response, User $user, Block_User $block) {
});
