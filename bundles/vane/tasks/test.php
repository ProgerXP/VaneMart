<?php

use Vane\Layout;
use Vane\LayoutItem;
use Vane\LayoutHandler;
use Vane\LayoutAlter;

function logged($msg) {
  $logged = array_get(array_pop(Vane_Test_Task::$logged), 1);
  assert(stripos($logged, $msg) !== false);
}

class Vane_Test_Task extends \Task {
  static $attached = false;
  static $logged = array();

  static function onLog($type, $msg) {
    static::$logged[] = array($type, $msg);
  }

  static function start() {
    error_reporting(-1);
    static::$logged = array();
    assert_options(ASSERT_BAIL, 1);

    if (!static::$attached) {
      static::$attached = true;
      \Event::listen('laravel.log', array(get_called_class(), 'onLog'));
    }
  }

  function run($arguments = array()) {
    foreach (get_class_methods($this) as $name) {
      if (starts_with($name, 'test_')) { $this->$name(); }
    }

    echo 'All passed.';
  }

  function layout($arguments = array()) { $this->test_layout(); }
  function layout_alter($arguments = array()) { $this->test_layout_alter(); }

  function baseLayout() {
    return array(
      // Horizontal block wrapped in <header> tag with 3 classes ('top-block', 'cl-1'
      // and 'cl-2') and 150px wide.
      '-header/top.cl-1.cl-2 150px' => array(
        // Vertical block with class 'logo-block' and 100 pixels wide.
        // Filled with application's 'logo.small' controller.
        // Note that 'small' is not class name but part of controller (ctl.sub@...).
        '|logo 100px'               => 'logo.small',
        // Block with classes 'menu-block' and 'main' using golden ratio's narrow side.
        '|menu.main goldn'          => array(
          // Filled first (left to right) with Vane's 'menu' controller given 'main'
          // as its first argument and an input array with param=abc.
          'Vane::menu main'         => array('param' => 'abc'),
          // Then filled with application's 'user' controller by invoking its 'menu'
          // method with no arguments. <aside> tag with 'cl' class wraps the content.
          'aside/user@menu.cl',
        ),
      ),
      // Horizontal block wrapped in <div> with class 'page-block'.
      '-page'                       => array(
        // Vertical block <nav> with classes 'menu-block' and 'left', 3 columns wide.
        // Filled with 'categories' menu handler from VaneMart bundle/namespace.
        '|nav/menu.left 3'          => array('VaneMart::categories'),
        // Empty <div> block with 'content-block' class, 9 columns wide.
        '|content 9'                => '',
      ),
    );
  }

  function viewLayout() {
    return array(
      ''                            => array(
        ''                          => 'vane::block.logo',
        'scalar'                    => 'var',
        'nested'                    => array(
          '-row.cl-1'               => 'rowctl@actn',
          '-row.cl-2'               => array('rowctl main', 'extra'),
        ),
      ),
      '-normal'                     => array(
        'block',
      ),
    );
  }

  function test_layout($l = null) {
    $this->start();

    $l = ($l instanceof Layout) ? $l : Layout::make($this->baseLayout());
    $l->parseAll();

    assert(count($l->blocks) == 2);
    assert($l->blocks[0] instanceof Layout);
    assert($l->blocks[1] instanceof Layout);

    assert($l->blocks[0]->column === false);
    assert($l->blocks[0]->row === true);
    assert($l->blocks[0]->tag === 'header');
    assert($l->blocks[0]->classes === array('top', 'cl-1', 'cl-2'));
    assert($l->blocks[0]->size === '150px');
    assert(trim($l->blocks[0]->openTag()) === '<header class="top-block cl-1 cl-2" style="width: 150px">');
    assert(trim($l->blocks[0]->closeTag()) === '</header>');

    assert($l->blocks[1]->column === false);
    assert($l->blocks[1]->row === true);
    assert($l->blocks[1]->tag === 'div');
    assert($l->blocks[1]->classes === array('page'));
    assert($l->blocks[1]->size == null);
    assert(trim($l->blocks[1]->openTag()) === '<div class="page-block">');
    assert(trim($l->blocks[1]->closeTag()) === '</div>');

    //
    // -header/top.cl-1.cl-2 150px
    //
    $row = $l->blocks[0]->parseAll();

      assert(count($row->blocks) == 2);
      assert($row->blocks[0] instanceof Layout);
      assert($row->blocks[1] instanceof Layout);

      assert($row->blocks[0]->column === true);
      assert($row->blocks[0]->row === false);
      assert(trim($row->blocks[0]->openTag()) === '<div class="logo-block" style="width: 100px">');

      //
      // |logo 100px
      $handlers = $row->blocks[0]->parseAll();

        assert(count($handlers->blocks) == 1);
        assert($handlers->blocks[0] instanceof LayoutHandler);

        // logo.small
        assert($handlers->blocks[0]->controller === 'logo.small');
        assert($handlers->blocks[0]->action == '');
        assert($handlers->blocks[0]->argArray() == array());
        assert($handlers->blocks[0]->options == array());
        assert($handlers->blocks[0]->tag == '');
        assert($handlers->blocks[0]->classes == array());
        assert($handlers->blocks[0]->openTag() == '');    // because $tag is unset.
        assert($handlers->blocks[0]->closeTag() == '');

      assert($row->blocks[1]->column === true);
      assert($row->blocks[1]->row === false);
      assert(trim($row->blocks[1]->openTag()) === '<div class="menu-block main size-goldn">');

      //
      // |menu.main goldn
      $handlers = $row->blocks[1]->parseAll();

        assert(count($handlers->blocks) == 2);
        assert($handlers->blocks[0] instanceof LayoutHandler);
        assert($handlers->blocks[1] instanceof LayoutHandler);

        // Vane::menu main
        assert($handlers->blocks[0]->controller === 'Vane::menu');
        assert($handlers->blocks[0]->action == '');
        assert($handlers->blocks[0]->argArray() === array('main'));
        assert($handlers->blocks[0]->options === array('param' => 'abc'));
        assert($handlers->blocks[0]->classes == array());
        assert($handlers->blocks[0]->openTag() == '');

        // aside/user@menu.cl
        assert($handlers->blocks[1]->controller === 'user');
        assert($handlers->blocks[1]->action == 'menu');
        assert($handlers->blocks[1]->argArray() == array());
        assert($handlers->blocks[1]->options == array());
        assert($handlers->blocks[1]->tag === 'aside');
        assert($handlers->blocks[1]->classes == array('cl'));
        assert(trim($handlers->blocks[1]->openTag()) == '<aside class="cl">');
        assert(trim($handlers->blocks[1]->closeTag()) == '</aside>');

    //
    // -page
    //
    $row = $l->blocks[1]->parseAll();

      assert(count($row->blocks) == 2);
      assert($row->blocks[0] instanceof Layout);
      assert($row->blocks[1] instanceof Layout);

      assert($row->blocks[0]->column === true);
      assert($row->blocks[0]->row === false);
      assert(trim($row->blocks[0]->openTag()) === '<nav class="menu-block left span-3">');

      $handlers = $row->blocks[0]->parseAll();

        assert(count($handlers->blocks) == 1);
        assert($handlers->blocks[0] instanceof LayoutHandler);

        assert($handlers->blocks[0]->controller === 'VaneMart::categories');
        assert($handlers->blocks[0]->action == '');
        assert($handlers->blocks[0]->argArray() == array());
        assert($handlers->blocks[0]->options == array());
        assert($handlers->blocks[0]->closeTag() == '');

      assert($row->blocks[1]->column === true);
      assert($row->blocks[1]->row === false);
      assert(trim($row->blocks[1]->openTag()) === '<div class="content-block span-9">');

      $handlers = $row->blocks[1]->parseAll();

        assert(count($handlers->blocks) == 0);
  }

  function test_layout_alter() {
    $this->start();

    // Warning when creating Layout with alter block(s).
    Layout
      ::make(array('|top' => '', '=alter' => 'x', '^prepend' => 'x'))
      ->parseAll();
    logged('Ignoring 2 Alter blocks');

    $l = Layout::make($this->baseLayout());
    // Multiple reparsings should not affect already parsed blocks.
    $l->parseAll()->parseAll()->parseAll();
    $this->test_layout($l);

    $l->alter(array('=top menu.right' => 'x'));
    logged('No matching block');
    $this->test_layout($l);

    // Different alter modes.
    $l->alter(array('=page content' => 'textpage'));
    assert($l->blocks[1]->parseAll()->blocks[1]->blocks === array('textpage'));
    $l->alter(array('=page content' => array('replaced')));
    assert($l->blocks[1]->parseAll()->blocks[1]->blocks === array('replaced'));
    $l->alter(array('+page content' => array('appended')));
    assert($l->blocks[1]->parseAll()->blocks[1]->blocks === array('replaced', 'appended'));
    $l->alter(array('^page content' => array('prepended')));
    assert($l->blocks[1]->parseAll()->blocks[1]->blocks === array('prepended', 'replaced', 'appended'));

    assert(!static::$logged);

    // Restoring original content to see if anything apart from it was tainted with
    // the above alterations.
    $l->alter(array('=page content' => ''));
    assert(!static::$logged);
    $this->test_layout($l);

    // 'content' class exists but under 'page', not in the root.
    $l->alter(array('=content' => 'x'));
    logged('No matching block');
    $this->test_layout($l);

    // Matching against unlisted class
    $l->alter(array('=top menu.main.extra' => 'x'));
    logged('No matching block');
    // Matching against tag.
    $l->alter(array('=header' => 'x'));
    logged('No matching block');
    // Matching against size.
    $l->alter(array('=top menu.goldn' => 'x'));
    logged('No matching block');
    // Matching against size class that shouldn't be listed among user classes.
    $l->alter(array('=top menu.size-goldn' => 'x'));
    logged('No matching block');

    $this->test_layout($l);

    // It should be okay to append 'nothing'.
    $l->alter(array('+top.cl-1.cl-2' => ''));
    assert(!static::$logged);
    $this->test_layout($l);

    // Matching by full list of classes and in arbitrary order - fine.
    $l->alter(array('+cl-2.top.cl-1' => 'appended'));
    assert(!static::$logged);
    assert(end($l->blocks[0]->blocks) === 'appended');

    // Testing class character case sensitivity.
    $l->alter(array('+TOP.cl-1.cl-2' => ''));
    logged('No matching block');

    // Matching by multiple but not all classes - fine.
    $l->alter(array('=top menu.main' => 'replaced'));
    assert(!static::$logged);
    assert($l->blocks[0]->parseAll()->blocks[1]->blocks === array('replaced'));

    // alter() should both alter current blocks and append new ones if they're given.
    $l->alter(array('-new.item' => 'my@handler', '=missing' => 'x'));
    assert($l->blocks[2]->column === false);
    assert($l->blocks[2]->row === true);
    assert(trim($l->blocks[2]->openTag()) === '<div class="new-block item">');
    assert($l->blocks[2]->blocks === array('my@handler'));
    logged('No matching block');
  }

  function test_layout_view($l = null) {
    $this->start();

    $l = ($l instanceof Layout) ? $l : Layout::make($this->viewLayout());
    $l->parseAll();

    assert(count($l->blocks) == 2);
    assert($l->blocks[0] instanceof Layout);
    assert($l->blocks[1] instanceof Layout);
    assert($l->blocks[0]->isView() === true);

    $viewBlock = $l->blocks[0]->parseAll();

      assert(count($viewBlock->blocks) === 3);
      assert($viewBlock->blocks[0] instanceof Layout);
      assert($viewBlock->blocks[1] instanceof Layout);
      assert($viewBlock->blocks[2] instanceof Layout);

      assert($viewBlock->blocks[0]->classes === array());
      assert($viewBlock->blocks[1]->classes === array('scalar'));
      assert($viewBlock->blocks[2]->classes === array('nested'));

    assert($viewBlock->emptyView() == null);
    assert($viewBlock->isView() == true);
    assert($viewBlock->blocks[0]->emptyView() instanceof Laravel\View);
    assert($viewBlock->blocks[0]->emptyView()->view === 'vane::block.logo');
    assert($viewBlock->blocks[1]->isView() == false);
    assert($viewBlock->blocks[1]->emptyView() == null);
  }

  function test_layout_nested_view() {
    $this->start();

    $l = Layout::make(array(
      ''                  => array(
        ''                => array(
          ''              => 'vane::block.logo',
          'nested'        => '=value',
        ),
        'top'             => '=data',
      ),
    ));

    $view = $l->view();
    assert($view instanceof Laravel\View);
    assert($view->view === 'vane::block.logo');
    assert($view->data['nested'] === 'value');
    assert($view->data['top'] === 'data');
  }

  function test_layout_alter_view() {
    $this->start();

    $l = Layout::make($this->viewLayout());
    $l->parseAll();

    $l->alter(array('. .' => 'new.view'));
    assert($l->blocks[0]->blocks[0]->blocks === array('new.view'));

    $l->alter(array('. scalar' => 'new scalar'));
    assert($l->blocks[0]->blocks[1]->blocks === array('new scalar'));
    // Leading '=' is optional.
    $l->alter(array('=. scalar' => 'replaced'));
    assert($l->blocks[0]->blocks[1]->blocks === array('replaced'));
    // Number of dots (that craete 'empty class reference') doesn't matter.
    $l->alter(array('..... scalar' => 'new-r'));
    assert($l->blocks[0]->blocks[1]->blocks === array('new-r'));
    $l->alter(array('+. scalar' => 'appended'));
    assert($l->blocks[0]->blocks[1]->blocks === array('new-r', 'appended'));

    // Different alter modes.
    $l->alter(array('. nested cl-1.row' => 'replaced'));
    assert($l->blocks[0]->blocks[2]->blocks[0]->blocks === array('replaced'));
    $l->alter(array('+. nested cl-1.row' => 'appended'));
    assert($l->blocks[0]->blocks[2]->blocks[0]->blocks === array('replaced', 'appended'));
    $l->alter(array('^. nested cl-1.row' => 'prepended'));
    assert($l->blocks[0]->blocks[2]->blocks[0]->blocks === array('prepended', 'replaced', 'appended'));

    assert(!static::$logged);

    // Operating on a non-existent view key creates it regardless of match type.
    $l->alter(array('. replace' => 'replaced'));
    assert(!static::$logged);
    assert($l->blocks[0]->blocks[3] instanceof Layout);
    assert($l->blocks[0]->blocks[3]->classes === array('replace'));
    assert($l->blocks[0]->blocks[3]->blocks === array('replaced'));

    $l->alter(array('+. app.end' => 'appended'));
    assert(!static::$logged);
    assert($l->blocks[0]->blocks[4] instanceof Layout);
    assert($l->blocks[0]->blocks[4]->classes === array('app', 'end'));
    assert($l->blocks[0]->blocks[4]->blocks === array('appended'));

    $l->alter(array('^. pre.pe.nd' => 'pre.pen.ded'));
    assert(!static::$logged);
    assert($l->blocks[0]->blocks[5] instanceof Layout);
    assert($l->blocks[0]->blocks[5]->classes === array('pre', 'pe', 'nd'));
    assert($l->blocks[0]->blocks[5]->blocks === array('pre.pen.ded'));

    assert(count($l->blocks[0]->blocks) === 6);

    $l->alter(array('. no nested' => 'creation'));
    logged('No matching block');

    // Automatic variable creation should only work for view blocks.
    $l->alter(array('=none' => 'created'));
    logged('No matching block');
  }
}