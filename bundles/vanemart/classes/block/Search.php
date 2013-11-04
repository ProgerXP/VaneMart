<?php namespace VaneMart;

class Block_Search extends BaseBlock {
  function get_index() {
    $q = $this->in('phrase', null);
    if ($q === null or Str::length($q) === 0) {
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
    $hasResults = $this->resultsNotEmpty($results);
    $messageForm = !$hasResults && $adminEmail;
    if ($messageForm) {
      $this->title = 'Что вы искали?';
    }
    $current = null;
    return compact('results', 'q', 'messageForm', 'skuGoods', 'goods', 'current',
     'groupsTree', 'gidsTree', 'allGroups', 'hasResults');
  }

  function ajax_get_index() {
    $data = $this->get_index();
    if ($data === true) {
      return true;
    }
    return View::make('vanemart::block.search.results', $data)->render();
  }

  /*function ajax_get_autocomplete() {
    $results = $this->getResults();
    return $results;
  }*/

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

  protected function getResults() {
    $q = $this->in('phrase', null);
    $qLike = strtr($q, array('%'=>'\%', '_'=>'\_'));

    $results = array();
    $results['groups'] = Group::where('title', 'LIKE', '%'.$qLike.'%')
      ->where_null('parent')->get();
    $results['subgroups'] = Group::where('title', 'LIKE', '%'.$qLike.'%')
      ->where_not_null('parent')->get();
    $results['goods'] = Product::where('title', 'LIKE', '%'.$qLike.'%')->get();
    // sku
    if (preg_match('/[a-z0-9_\-]/i', $q)) {
      $results['sku'] = Product::where('sku', 'LIKE', $qLike.'%')->get();
    }
    // order
    if (preg_match('/^[0-9]$/i', $q)) {
      $results['orders'] = Order::where('id', '=', $q)->first();
    }

    return $results;
  }

  protected function resultsNotEmpty($results) {
    $return = false;
    foreach ($results as $result) {
      if (count($result) > 0) {
        $return = true;
        break;
      }
    }
    return $return;
  }
}