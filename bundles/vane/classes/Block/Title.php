<?php namespace Vane;

// Handler for the standard 'vane::title' block that displays value of the main
// Layout's view's 'title' variable if it's set.
class Block_Title extends Block {
  public $bundle = 'vane';

  function get_index($langName = null, $tag = null) {
    $text = trim( $langName ? __($langName)->get() : $this->viewData('title') );
    $tag or $tag = $langName ? 'h2' : 'h1';
    is_numeric($tag) and $tag = "h$tag";

    if ($text === '') {
      return '';
    } else {
      $html = $this->in('html', false) ? $text : HLEx::q($text);
      return compact('tag', 'html');
    }
  }
}