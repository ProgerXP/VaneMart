<?php
return array(
  '/'                     => array(
    '=nav items list'     => array('mart:goods offers'),
    '=content'            => array('mart:offers'),
  ),

  'goods/~\d+~ - goods'   => array(
    '=content'            => array(
      '|product goldw'    => array('mart:product'),
      '|comments goldn'   => array('mart:comments'),
    ),
  ),

  'goods/~\w*~ - goods'   => array(
    '=content'            => array('mart:offers'),
  ),

  'orders - orders'       => array(
    '=content'            => array(),
  ),

  'orders/~\d+~ - orders' => array(
    '=content'            => array(
      '|goods goldw'      => array('mart:order'),
      '|talk goldn'       => array('talk'),
    ),
  ),

  'my'                    => 'My',

  'users/~\d+~ - orders'  => array(
    '=content'            => array(
      '|info goldn'       => array('mart:userinfo'),
      '|order goldn'      => array(),
    ),
  ),

  'help/~.*~'             => array(
    '=nav items list'     => array('textpages'),
    '=content'            => array('textpage'),
  ),
);