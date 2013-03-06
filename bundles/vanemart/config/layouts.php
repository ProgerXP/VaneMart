<?php
return array(
  // Default layout used for all VaneMart routes.
  null                    => array(
    // Wrapping view definition.
    null                  => array(
      // View name.
      null                => 'vanemart::full',
      // Variables to pass to it. Values are regular block(s). Leading '=' is
      // a shortcut to Vane::raw block that simply returns its input.
      'title'             => '='.Vane\Current::config('company.long'),
      'styles'            => '='.VaneMart\asset('styles.less'),
      'scrpits'           => array(),
    ),

    // Block definitions.
    // Header block.
    '-header/#top'        => array(
      '|left goldn'       => array(
        '|spacer goldn'   => null,
        '|cart goldw'     => 'Vane::menu cart',
      ),
      '|right goldw'      => array('Vane::menu main', 'Vane::menu user'),
    ),

    // Left sidebar.
    '|nav goldn'          => array(
      '|nav/#menu goldn'  => array('Vane::logo', 'Vane::menu groups'),
      '|#group goldw'     => array(
        '-title'          => array(),
        '-list'           => 'VaneMart::group@byList *',
      ),
    ),

    // Page content.
    '|#content goldw'     => array('Vane::status', 'Vane::title'),
  ),

  // Additional named layouts.
  //'name'                  => array(...),
);