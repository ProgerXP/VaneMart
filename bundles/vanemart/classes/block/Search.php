<?php namespace VaneMart;

class Block_Search extends BaseBlock {
  /*---------------------------------------------------------------------
  | GET search/index /PHRASE
  |
  | Show search results for PHRASE
  |--------------------------------------------------------------------*/
  function get_index() {
    $q = $this->in('phrase', null);
    if (!preg_match('/\S/u', $q)) {
      $this->layoutVars = array( 'hasResults' => false );
      return true;
    }
    
    $results = $this->getResults();

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
    $messageForm = !$hasResults && $adminEmail;
    if ($messageForm) {
      $this->title = 'Что вы искали?';
    }
    $current = null;
    return compact('results', 'q', 'messageForm', 'skuGoods', 'goods', 'current',
     'groupsTree', 'gidsTree', 'allGroups', 'hasResults');
  }
  
  /*---------------------------------------------------------------------
  | GET search/index /PHRASE
  |
  | Show HTML search results for PHRASE
  |--------------------------------------------------------------------*/
  function ajax_get_index() {
    $data = $this->get_index();
    if ($data === true) {
      return true;
    }
    return View::make('vanemart::block.search.results', $data)->render();
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
    foreach ($results as $key=>$result) {
      foreach ($result as $el) {
        if ($key == 'orders') {
          $title = 'Заказ №'.$el->id;
        } else {
          $title = $el->title;
        }
        $insert = array(
          'title' => HLEx::q($title),
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
        'from'        => $this->in('email')
      ));

      $this->status('message_sent');
      return $this->back(route('vanemart::search'));
    }
  }

  protected function getResults($maxResults = null) {
    $q = $this->in('phrase', null);
    if (!preg_match('/\S/u', $q)) {
      return array();
    }
    $qLike = strtr($q, array('%'=>'\%', '_'=>'\_'));

    $results = array();

    $funcs[] = function (&$results, $qLike, $q) {
      // sku
      if (preg_match('/[a-z0-9_\-]/i', $q)) {
        $results['sku'] = Product::where('sku', 'LIKE', $qLike.'%')->get();
      }
    };

    $funcs[] = function (&$results, $qLike, $q) {
      // orders
      if (preg_match('/^[0-9]$/i', $q)) {
        $results['orders'] = Order::where('id', '=', $q)->get();
      }
    };

    $funcs[] = function (&$results, $qLike, $q) {
      $results['groups'] = Group::where('title', 'LIKE', '%'.$qLike.'%')
        ->where_null('parent')->get();
    };

    $funcs[] = function (&$results, $qLike, $q) {
      $results['subgroups'] = Group::where('title', 'LIKE', '%'.$qLike.'%')
        ->where_not_null('parent')->get();
    };

    $funcs[] = function (&$results, $qLike, $q) {
      $results['goods'] = Product::where('title', 'LIKE', '%'.$qLike.'%')->get();
    };

    foreach ($funcs as $func) {
      $func($results, $qLike, $q);
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