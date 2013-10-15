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

  static function goodsCount() {
    $table = static::$table;
    $goodsTable = Product::$table;
   
    $counts = \DB::table($table)->join($goodsTable, $goodsTable.'.group', '=', $table.'.id')
      ->group_by($table.'.id')
      ->get(array($table.'.id', \DB::raw('COUNT('.$goodsTable.'.id) as count')));

    $result = array();
    foreach ($counts as $count) {
      $result[$count->id] = $count->count;
    }

    return $result;
  }

  static function treeWithCounts($groups = null) {
    $counts = static::goodsCount();
    if ($groups === null) {
      $groups = static::get();
    }
    $tree = static::buildTree($groups);
    static::calcGoodsCount($tree, $counts);

    return $tree;
  }

  static function multipleChildrenIds($tree, $gids) {
    $result = array();
    foreach ($gids as $gid) {
      $childs = static::findGroupInTree($tree, $gid)->childs;
      $result = array_merge($result, static::findChildrenIds($childs));
    }
    return $result;
  }

  static function findChildrenIds($childs) {
    $gids = array();
    if ($childs) {
      foreach ($childs as $group) {
        $gids[] = $group->id;
        $gids = array_merge($gids, static::findChildrenIds($group->childs));
      }
    }
    return $gids;
  }

  static function findGroupInTree($tree, $gid) {
    foreach ($tree as $group) {
      if ($group->id == $gid) {
        return $group;
      }
      if ($group->childs) {
        $result = static::findGroupInTree($group->childs, $gid);
        if ($result) {
          return $result;
        }
      }
    }
    return null;
  }

  static function calcGoodsCount($tree, $counts) {
    $sum = 0;
    foreach ($tree as $group) {
      $group->goodsCount = isset($counts[$group->id]) ? $counts[$group->id] : 0;
      if ($group->childs) {
        $group->goodsCount += static::calcGoodsCount($group->childs, $counts);
      }
      $sum += $group->goodsCount;
    }
    return $sum;
  }

  static function buildTree($items, $root = null) {
    if (count($items) < 2) {
      return $root !== null ? array() : (array) $items;
    }
    $childs = array();

    foreach ($items as $item) {
      $childs[$item->parent][] = $item;
    }

    foreach ($items as $item) {
      if (isset($childs[$item->id])) {
        $item->childs = $childs[$item->id];
      }
    }

    $tree = $childs[$root];

    return $tree;
  }

  function pretty() {
    return $this->fill_raw( static::prettyOther($this->attributes) );
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