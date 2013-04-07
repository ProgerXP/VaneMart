<?php namespace Vane;

use TextPub;

// Handler for the standard 'vane::textpub' block that uses TextPub bundle to serve
// static pages.
class Block_Textpub extends Block {
  public $bundle = 'vane';

  function get_index($root, $page = '') {
    \Config::set('textpub::general.layout', $this->name);
    TextPub::paths( TextPub::option('paths') );

    foreach (TextPub::$paths as $key => $info) {
      if ($info['path'] === $root) {
        $view = TextPub::serve_by($key, $page);

        if ($view instanceof \Laravel\View) {
          $this->title = array($view['title']);
        }

        return $view;
      }
    }

    Log::warn_Textpub("Cannot determine TextPub path key of root [$root].");
  }
}