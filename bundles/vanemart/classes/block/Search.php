<?php namespace VaneMart;

class Block_Search extends BaseBlock {
  public $searchTemplate = 'vanemart::block.search.results';

  /*---------------------------------------------------------------------
  | GET search/index /PHRASE
  |
  | Show search results for PHRASE
  |--------------------------------------------------------------------*/
  function get_index() {
    $this->searchTemplate = 'vanemart::block.search.index';
    return $this->ajax_get_index();
  }

  /*---------------------------------------------------------------------
  | GET search/index /PHRASE
  |
  | Show HTML search results for PHRASE
  |--------------------------------------------------------------------*/
  function ajax_get_index() {
    $query = $this->in('phrase', '');
    $results = $this->getResults();
    if ($results === null) {
      $this->layoutVars = array( 'hasResults' => false );
      return true;
    }

    // making a tree from found groups
    $groups = array_merge(array_get($results, 'groups', array()),
      array_get($results, 'subgroups', array()));
    $tmp = Group::order_by('sort')->get();
    $allGroups = array();
    foreach ($tmp as $group) {
      $allGroups[$group->id] = $group;
    }
    $allTree = Group::buildTree($allGroups);

    $groupsTree = array();
    $hits = prop('id', $groups);
    if ($hits) {
      // [id1 => 1, id2 => 1, id3 => 1]
      $hits = array_combine($hits, array_fill(0, count($hits), 1));
      // making copy of groups to prevent overlapping
      $copiedGroups = array();
      foreach ($allGroups as $key => $value) {
        $copiedGroups[$key] = clone $value;
        // installing a marked title
        foreach ($groups as $group) {
          if ($group->id === $value->id) {
            $copiedGroups[$key]->titleQ = $group->titleQ;
          }
        }
      }
      $groupsTree = Group::buildTree($copiedGroups);
      Group::calcGoodsCount($groupsTree, $hits, 'found');
      $groupsTree = Group::removeChild($groupsTree, 'found');
    }

    // goods cells
    $skuGoods = array();
    if (!empty($results['sku'])) {
      $tmp = Block_Group::listResponse(320, $results['sku']);
      $skuGoods = $tmp['rows'];
    }
    // [ gid1 => [product1, product2], gid2 => [product3] ]
    $goods = array();
    if (!empty($results['goods'])) {
      $rows = Group::groupsWithGoods($results['goods'], $allGroups);
      foreach ($rows as $gid => $row) {
        if (!empty($row['products'])) {
          $tmp = Block_Group::listResponse(320, $row['products']);
          $rows[$gid]['products'] = $tmp['rows'];
        }

        if (!empty($row['subgroups'])) {
          foreach ($row['subgroups'] as $subId => $subrow) {
            $tmp = Block_Group::listResponse(320, $subrow['products']);
            $rows[$gid]['subgroups'][$subId]['products'] = $tmp['rows'];
          }
        }
      }
      $goods = $rows;
    }

    $adminEmail = \Config::get('vanemart::company.email', null);
    $hasResults = (bool) $this->resultsCount($results);
    $messageForm = (!$hasResults and $adminEmail);
    if ($messageForm) {
      $this->title = __('vanemart::search.message.title');
    }
    $current = null;
    $data = compact('results', 'query', 'messageForm', 'skuGoods', 'goods', 'current',
     'groupsTree', 'allGroups', 'hasResults');

    return View::make($this->searchTemplate, $data)->render();
  }

  /*---------------------------------------------------------------------
  | GET search/autocomplete /PHRASE
  |
  | Returns an array in JSON format with search results for PHRASE
  |--------------------------------------------------------------------*/
  function ajax_get_autocomplete() {
    $maxResults = 15;
    $data = array();

    $results = $this->getResults($maxResults);
    if ($results === null) {
      return null;
    }

    foreach ($results as $key => $result) {
      foreach ($result as $el) {
        if ($key == 'orders') {
          $title = __('vanemart::search.results.order', $el->id) . '';
          $titleQ = $title;
        } else {
          $title = $el->title;
          $titleQ = $el->titleQ;
        }
        $insert = array(
          'title' => $title,
          'titleQ' => $titleQ,
          'url'   => $el->url(),
        );
        $data[$key][] = $insert;
      }
    }
    return $data;
  }

  /*---------------------------------------------------------------------
  | GET search/message
  |
  | Post a message via email, or shows a contact form with errors
  |--------------------------------------------------------------------*/
  function post_message() {
    $email = \Config::get('vanemart::company.email', null);
    if ($email != '') {
      $rules = array(
        'message'             => 'required|min:10',
      );
      $valid = Validator::make($this->in(), $rules);
      if ($valid->fails()) {
        return $valid;
      }
      $from = trim($this->in('from', ''));
      if ($from == '') {
        $from = __('vanemart::search.mail.message.default') . '';
      }
      \Vane\Mail::sendTo($email, 'vanemart::mail.search.message', array(
        'messageQ'    => nl2br(HLEx::q( $this->in('message') )),
        'from'        => $from,
      ));

      $this->status('message_sent');
      return $this->back(route('vanemart::search'));
    }
  }

  protected static function checkResult($str, $query, $mark = true) {
    $querySafe = preg_quote($query, '/');
    $found = false;
    $str = q($str);
    $str = preg_replace_callback('/^'.$querySafe.'/iu', function ($matches) use ($mark, &$found) {
      $found = true;
      return $mark ? '<mark>'.$matches[0].'</mark>' : $matches[0];
    }, $str);

    $str = preg_replace_callback('/(\s+)('.$querySafe.')/iu', function ($matches) use ($mark, &$found) {
      $found = true;
      return $mark ? $matches[1].'<mark>'.$matches[2].'</mark>' : $matches[0];
    }, $str);

    return $found ? $str : false;
  }

  protected function getResults($maxResults = null) {
    $query = $this->in('phrase', null);
    if ($query === null or !preg_match('/\S/u', $query)) {
      return null;
    }
    $qLike = strtr($query, array('%'=>'\%', '_'=>'\_'));

    $results = array();

    $funcs['sku'] = function ($qLike, $query) {
      // sku
      if (strlen($query) > 2 and preg_match('/[a-z0-9_\-]+/i', $query)) {
        return Product::where('sku', 'LIKE', $qLike.'%')
          ->where_null('variation')
          ->where('available', '=', 1)
          ->order_by('sort')
          ->get();
      }
    };

    $funcs['orders'] = function ($qLike, $query) {
      // orders
      if (!ltrim($query, '0..9')) {
        return Order::where('id', '=', $query)->get();
      }
    };

    $funcs['groups'] = function ($qLike, $query) {
      return Group::where('title', 'LIKE', '%'.$qLike.'%')
        ->where_null('parent')
        ->order_by('sort')
        ->get();
    };

    $funcs['subgroups'] = function ($qLike, $query) {
      return Group::where('title', 'LIKE', '%'.$qLike.'%')
        ->where_not_null('parent')
        ->order_by('sort')
        ->get();
    };

    $funcs['goods'] = function ($qLike, $query) {
      return Product::where('title', 'LIKE', '%'.$qLike.'%')
        ->where_null('variation')
        ->where('available', '=', 1)
        ->order_by('sort')
        ->get();
    };

    foreach ($funcs as $type => $func) {
      $data = $func($qLike, $query);
      if ($data == null) {
        continue;
      }

      foreach ($data as $result) {
        if (in_array($type, array('orders', 'sku'))) {
          $result->titleQ = q($result->title);
          $results[$type][] = $result;
        } else {
          $titleQ = static::checkResult($result->title, $query);
          if ($titleQ !== false) {
            $result->titleQ = $titleQ;
            $results[$type][] = $result;
          }
        }
      }

      if ($maxResults > 0 and $this->resultsCount($results) >= $maxResults) {
        break;
      }
    }

    return $results;
  }

  protected function resultsCount($results) {
    $sum = 0;
    foreach ($results as $result) {
      $sum += count($result);
    }
    return $sum;
  }
}