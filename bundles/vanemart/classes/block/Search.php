<?php namespace VaneMart;

class Block_Search extends BaseBlock {
  public $searchTemplate = 'vanemart::block.search.results';
  static $query = '';
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
    $query = $this->getQuery();
    if ($query === null) {
      $this->layoutVars = array( 'hasResults' => false );
      return true;
    }
    
    $results = $this->getResults($query);

    // making a tree from found groups
    $groups = array_merge($results['groups'], $results['subgroups']);
    $tmp = Group::order_by('sort')->get();
    foreach ($tmp as $group) {
      $allGroups[$group->id] = $group;
    }
    $allTree = Group::buildTree($allGroups);
    $groupsTree = array();
    $hits = prop('id', $groups);
    if ($hits) {
      // [id1 => 1, id2 => 1, id3 => 1]
      $hits = array_combine($hits, array_fill(0, count($hits), 1));
      $groupsTree = $allTree;
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
     'groupsTree', 'gidsTree', 'allGroups', 'hasResults');

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

    $query = $this->getQuery();
    if ($query === null) {
      return null;
    }

    $results = $this->getResults($query, $maxResults);
    foreach ($results as $key => $result) {
      foreach ($result as $el) {
        if ($key == 'orders') {
          $title = __('vanemart::search.results.order', $el->id) . '';
        } else {
          $title = $el->title;
        }
        $insert = array(
          'title' => $title,
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
      
      \Vane\Mail::sendTo($email, 'vanemart::mail.search.message', array(
        'message'     => $this->in('message'),
        'from'        => $this->in('from', '')
      ));

      $this->status('message_sent');
      return $this->back(route('vanemart::search'));
    }
  }

  static function mark($string) {
    $string = str_replace(array('<mark>','</mark>'), '', $string);
    return static::markResult(q($string));
  }

  protected static function markResult($s) {
    $querySafe = preg_quote(static::$query);
    $s = preg_replace_callback('/^'.$querySafe.'/iu', function ($matches) {
      return '<mark>'.$matches[0].'</mark>';
    }, $s);
    $s = preg_replace_callback('/(\s+)('.$querySafe.')/iu', function ($matches) {
      return $matches[1].'<mark>'.$matches[2].'</mark>';
    }, $s);
    return $s;
  }

  protected function getQuery() {
    $query = $this->in('phrase', null);
    if ($query === null or !preg_match('/\S/u', $query)) {
      $query = null;
    }
    static::$query = $query;
    return $query;
  }

  protected function getResults($query, $maxResults = null) {
    $qLike = strtr($query, array('%'=>'\%', '_'=>'\_'));

    $results = array();

    $funcs[] = function (&$results, $qLike, $query) {
      // sku
      if (preg_match('/[a-z0-9_\-]/i', $query)) {
        $results['sku'] = Product::where('sku', 'LIKE', $qLike.'%')
          ->where_null('variation')
          ->where('available', '=', 1)
          ->order_by('sort')
          ->get();
      }
    };

    $funcs[] = function (&$results, $qLike, $query) {
      // orders
      if (preg_match('/^[0-9]$/i', $query)) {
        $results['orders'] = Order::where('id', '=', $query)->get();
      }
    };

    $funcs[] = function (&$results, $qLike, $query) {
      $results['groups'] = Group::where('title', 'LIKE', '%'.$qLike.'%')
        ->where_null('parent')
        ->order_by('sort')
        ->get();
    };

    $funcs[] = function (&$results, $qLike, $query) {
      $results['subgroups'] = Group::where('title', 'LIKE', '%'.$qLike.'%')
        ->where_not_null('parent')
        ->order_by('sort')
        ->get();
    };

    $funcs[] = function (&$results, $qLike, $query) {
      $results['goods'] = Product::where('title', 'LIKE', '%'.$qLike.'%')
        ->where_null('variation')
        ->where('available', '=', 1)
        ->order_by('sort')
        ->get();
    };

    foreach ($funcs as $func) {
      $func($results, $qLike, $query);
      if ($maxResults > 0 and $this->resultsCount($results) >= $maxResults) {
        break;
      }
    }

    foreach ($results as $type => $subresults) {
      if ($type === 'orders' or $type === 'sku') {
        continue;
      }
      foreach ($subresults as $i => $result) {
        $result->title = static::markResult($result->title);
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