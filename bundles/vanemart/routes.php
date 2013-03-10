<?php
use Vane\Route as VRoute;

VRoute::on('(:bundle)');

VRoute::on('(:bundle)/groups/(\d+-?[^/]*)')
  ->as('vanemart::group')
  ->servers('VaneMart::group')
  ->layout(array(
    '=nav #group title'   => array('VaneMart::group@title'),
    '=nav #group list'    => array('!'),
  ));

VRoute::on('(:bundle)/goods/(\d+-?[^/]*)')
  ->as('vanemart::product')
  ->servers('VaneMart::product')
  ->layout(array(
    '=nav #group title'   => array('VaneMart::group@titleByProduct (:1)'),
    '=nav #group list'    => array('VaneMart::group@byProduct (:1)'),
    '+#content'           => array('!'),
  ));

VRoute::on('(:bundle)/cart/(:any?)/(:num?)')
  ->as('vanemart::cart')
  ->servers('VaneMart::cart@(:1)')
  ->layout(array(
    '+#content'           => array('!', 'VaneMart::cart@addBySKU'),
  ));

VRoute::map('(:bundle)/checkout', 'VaneMart::checkout', true);

VRoute::on('(:bundle)/orders')
  ->as('vanemart::orders')
  ->servers('VaneMart::order')
  ->layout(array(
    '=nav #group title'   => array('='.__('vanemart::order.title')),
    '=nav #group list'    => array('!'),
  ));

VRoute::on('(:bundle)/orders/(:num)')
  ->as('vanemart::order')
  ->servers('VaneMart::order@show')
  ->layout(array(
    '=nav #group title'   => array('='.__('vanemart::order.title')),
    '=nav #group list'    => array('VaneMart::order'),
    '+#content'           => array(
      '|order goldw'      => array(
        '!', 'VaneMart::order@goods (:1)',
      ),
      '|posts goldn'      => array(
        'VaneMart::post@add orders (:1)', 'VaneMart::post orders (:1)',
      ),
    ),
  ));

VRoute::map('(:bundle)/user/(:num)', 'VaneMart::user@show', true);
VRoute::map('(:bundle)/user/reg', 'VaneMart::user@reg', 'vanemart::register');
VRoute::map('(:bundle)/user/login', 'VaneMart::user@login', 'vanemart::login');
VRoute::map('(:bundle)/user/logout', 'VaneMart::user@logout', 'vanemart::logout');

VRoute::on('GET (:bundle)/thumb')
  ->as('vanemart::thumb')
  ->naked('VaneMart::thumb');

VRoute::on('GET (:bundle)/post/add/(:any)/(:num?)')
  ->as('vanemart::post')
  ->naked('VaneMart::post@add');

VRoute::on('GET (:bundle)/file/(:any)')
  ->as('vanemart::file')
  ->naked('VaneMart::file@dl');

VRoute::on('(:bundle)/help/(:all?)')
  ->as('vanemart::help')
  ->servers('Vane::textpub help')
  ->layout(array(
    '+#content'           => array('!'),
  ));

VRoute::assign('vanemart::contacts', '(:bundle)/help/contacts');
