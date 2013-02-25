<?php
return array(
  null                    => array(
    null                  => array(
      null                => 'vanemart::full',
      'title'             => '=VaneMart',
      'styles'            => '='.VaneMart\asset('styles.less'),
    ),
    '-header/head'        => array(
      '|left goldn'       => array(
        '|spacer goldn'   => null,
        '|cart goldw'     => 'Vane::menu cart',
      ),
      '|right goldw'      => array('Vane::menu main', 'Vane::menu user'),
    ),
    '|nav goldn'          => array(
      '|nav/menu goldn'   => array('Vane::logo', 'Vane::menu groups'),
      '|items goldw'      => array(
        '-title'          => array(),
        '-list'           => array(),
      ),
    ),
    '|content goldw'      => array(),
  ),

  'goods'                 => array(
    '=nav items title'    => array('VaneMart::goods current'),
    '=nav items list'     => array('VaneMart::goods'),
  ),

  'orders'                => array(
    '=nav items title'    => array('Vane::text orders'),
    '=nav items list'     => array('VaneMart::orders'),
  ),
);