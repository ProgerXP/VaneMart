<?php
return array(
  null                    => array(
    null                  => array(
      null                => 'vanemart::full',
      'title'             => '=VaneMart',
      'styles'            => '='.VaneMart\asset('styles.less'),
    ),
    '-header/#top'        => array(
      '|left goldn'       => array(
        '|spacer goldn'   => null,
        '|cart goldw'     => 'Vane::menu cart',
      ),
      '|right goldw'      => array('Vane::menu main', 'Vane::menu user'),
    ),
    '|nav goldn'          => array(
      '|nav/#menu goldn'  => array('Vane::logo', 'Vane::menu groups'),
      '|#group goldw'     => array(
        '-title'          => array(),
        '-list'           => array(),
      ),
    ),
    '|#content goldw'     => array(),
  ),

  'goods'                 => array(
    '=nav #group title'   => array('VaneMart::goods current'),
    '=nav #group list'    => array('VaneMart::goods'),
  ),

  'orders'                => array(
    '=nav #group title'   => array('Vane::text orders'),
    '=nav #group list'    => array('VaneMart::orders'),
  ),
);