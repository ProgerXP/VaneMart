<?php namespace Vane;

// Handler for the standard 'vane::title' block that displays value of the main
// Layout's view's 'title' variable if it's set.
class Block_Title extends Block {
  public $bundle = 'vane';

  function get_index($tag = 'h1') {
    $text = trim( $this->viewData('title') ?: $this->viewData('winTitle') );

    if ($text === '') {
      return '';
    } else {
      $html = $this->in('html', false) ? $text : HLEx::q($text);
      return compact('tag', 'html');
    }
  }
}