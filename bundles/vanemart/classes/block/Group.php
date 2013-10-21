<?php namespace VaneMart;

class Block_Group extends ModelBlock {
  static $model = 'VaneMart\\Group';

  static function listResponse($imageSize, array $rows) {
    if ($rows) {
      $current = static::detectCurrentProduct();
      // prefetch all connected images as they're used in the view.
      File::all(prop('image', $rows));

      return array('rows' => S($rows, function ($product) use ($current, $imageSize) {
        return array(
          'image'           => $product->image($imageSize),
          'current'         => $current and $current->id === $product->id,
        ) + $product->to_array();
      }));
    }
  }

  //= Product
  static function detectCurrentProduct() {
    $route = \Vane\Route::current();
    if ($route and $route->lastServer instanceof Block_Product) {
      return $route->lastServer->product;
    }
  }

  /*---------------------------------------------------------------------
  | GET group/title /ID
  |
  | Simply returns group's title.
  |--------------------------------------------------------------------*/
  function get_title($id = null) {
    if ($group = static::find($id)) {
      return $group->title;
    }
  }

  /*---------------------------------------------------------------------
  | GET group/index /ID
  |
  | Outputs group contents in proper order ignoring unavailable items and
  | variations of a product.
  |--------------------------------------------------------------------*/
  function ajax_get_index($id = null) {
    if ($group = static::find($id)) {
      $rows = $group->goods(true)
        ->where_null('variation')
        ->where('available', '=', 1)
        ->order_by('sort')
        ->get();

      if (Request::ajax()) {
        return $rows;
      } elseif ($rows) {
        return static::listResponse(320, $rows);
      }
    }
  }

  /*---------------------------------------------------------------------
  | GET group/title_by_product /ID
  |
  | Returns title of the group to which product ID belongs to.
  |--------------------------------------------------------------------*/
  function get_title_by_product($id = null) {
    return $this->actByProduct('get_title', $id);
  }

  /*---------------------------------------------------------------------
  | GET group/by_product /ID
  |
  | Same as GET group/index but ID is product's ID which group is listed.
  |--------------------------------------------------------------------*/
  function get_by_product($id = null) {
    return $this->actByProduct('ajax_get_index', $id);
  }

  protected function actByProduct($method, $id) {
    if ($id = static::idFrom($id) and $model = Product::find($id) and $model->group) {
      $group = Group::find($model->group);

      if ($group and $group = $group->root()) {
        $this->layout = '.'.substr(strrchr($method, '_'), 1);
        return $this->$method($group);
      }
    }
  }

  /*---------------------------------------------------------------------
  | GET group/by_list [/NAME] [/SLIDES]
  |
  | Same as GET group/index but displays items listed in vm_goods_lists
  | table under the given NAME.
  |----------------------------------------------------------------------
  | * NAME          - optional; primary list name to look up. Defaults to
  |   'main'. Can be '*' - if this block is part of another block with a
  |   specific server its controller name is used: 'bndl::ctl.sub@actn' -
  |   list name is 'ctl.sub'. If controller starts with 'block.' (e.g.
  |   'vanemart::block.group' it's removed.
  | * SLIDES        - optional; indicates to use slideshow view instead
  |   of plain list. Also, if set only products with images are shown.
  | * default=NAME  - optional; if present and no list with NAME passed
  |   in the URL exists this name is used instead. Defaults to 'main'.
  |--------------------------------------------------------------------*/
  function get_by_list($name = 'main', $slides = false) {
    if ($name === '*') {
      if ($route = \Vane\Route::current() and $route->lastServer) {
        $name = \Bundle::element($route->lastServer->name);
        $name = S::tryUnprefix(strtok($name, '@'), 'block.');
      } else {
        $name = null;
      }
    }

    $default = $this->in('default', 'main');
    if (!$name and !$default) {
      return E_INPUT;
    }

    $query = ProductListItem::order_by('sort');
    $name and $query->where('type', '=', $name);
    $default and $query->or_where('type', '=', $default);

    if ($list = $query->get()) {
      if ($default and !S::first($list, array('?->type === ?', $name))) {
        $type = $default;
      } else {
        $type = $name;
      }

      $list = S::keep($list, array('?->type === ?', $type));
      $goods = S::keys(Product::all(prop('product', $list)), '?->id');
      $ordered = array();

      foreach ($list as $item) {
        $product = &$goods[$item->product];

        if ($product and (!$slides or $product->image)) {
          $product->extra = $item->extra;
          $ordered[] = $product;
        }
      }

      $this->layout = $slides ? '.slides' : '.index';
      return static::listResponse($slides ? 1000 : 320, $ordered);
    }
  }

  /*---------------------------------------------------------------------
  | GET group/sectionized /ID
  |
  | Displays goods in sections for given group ID. 
  | Each section has goods from a toplevel subgroup plus it's 
  | own subgroups.
  |--------------------------------------------------------------------*/
  function get_sectionized($id = null) {
    $rows = $this->ajax($id);

    $result = array();
    foreach ($rows as $gid => $goods) {
      $tmp = static::listResponse(320, $goods);
      $result[$gid] = $tmp['rows'];
    }
    return array('sections' => $result); 
  }

  /*---------------------------------------------------------------------
  | GET group/sectionized /ID
  |
  | Returns an array of goods with it's indexes equal to IDs of 
  | toplevel subgroups.
  |--------------------------------------------------------------------*/
  function ajax_get_sectionized($id = null) {
    if ($group = static::find($id)) {
      $subgroups = array();
      $gidsTree = array();
      $rows = $group->sectionizedGoods($subgroups, $gidsTree);
      $groups = array();
      foreach ($subgroups as $v) {
        $groups[$v->id] = $v;
      }
      $this->title = $group->title;
      $this->layoutVars = array(
        'groups' => $groups,
        'gidsTree' => $gidsTree,
      );
      return $rows;   
    }
  }

  /*---------------------------------------------------------------------
  | GET group/toc /DEPTH /ID
  | Displays TOC for given group.
  |
  |----------------------------------------------------------------------
  | * ID    - group's ID, should be set.
  | * DEPTH - 0 - there is no limit for depth, 
  |         1 - outputs only the first level, 2 - second, and so on.
  |--------------------------------------------------------------------*/
  function get_toc($id = null, $depth = 0) {
    if ($group = static::find($id)) {
      $depth = $depth == 0 ? ($depth - 1) : ($depth + 1);
      $subgroups = $group->subgroups($depth);
      $tree = $group->treeWithCounts($subgroups, 1);
      $tree = array_shift($tree)->childs;
      return compact('tree');
    }
  }
}