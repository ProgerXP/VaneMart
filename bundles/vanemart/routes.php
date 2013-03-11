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
    '=nav #group title'   => array('VaneMart::group@title_by_product (:1)'),
    '=nav #group list'    => array('VaneMart::group@by_product (:1)'),
    '+#content'           => array('!'),
  ));

VRoute::on('(:bundle)/cart/(:any?)/(:num?)')
  ->as('vanemart::cart')
  ->servers('VaneMart::cart@(:1)')
  ->layout(array(
    '+#content'           => array('!', 'VaneMart::cart@add_sku'),
  ));

VRoute::map('(:bundle)/checkout', 'VaneMart::checkout', true)
  ->layout(array(
    '+#content' => array('!', 'VaneMart::cart'),
  ));

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
      '|order goldw'      => array('!', 'VaneMart::order@goods (:1)' => '!'),
      '|posts goldn'      => array(
        'Vane::title vanemart::order.posts',
        'VaneMart::post@add orders (:1)',
        'VaneMart::post orders (:1)',
      ),
    ),
  ));

VRoute::map('(:bundle)/users/(:num)', 'VaneMart::user@show', true);
VRoute::map('(:bundle)/users/reg', 'VaneMart::user@reg', 'vanemart::register');
VRoute::map('(:bundle)/users/login', 'VaneMart::user@login', 'vanemart::login');
VRoute::map('(:bundle)/users/logout', 'VaneMart::user@logout', 'vanemart::logout');

VRoute::on('GET (:bundle)/thumb')
  ->as('vanemart::thumb')
  ->naked('VaneMart::thumb');

VRoute::on('(:bundle)/posts/add/(:any)/(:num?)')
  ->as('vanemart::post')
  ->naked('VaneMart::post@add');

VRoute::on('(:bundle)/files/(:any)')
  ->as('vanemart::file')
  ->naked('VaneMart::file@dl');

VRoute::on('(:bundle)/help/(:all?)')
  ->as('vanemart::help')
  ->servers('Vane::textpub help')
  ->layout(array(
    '+#content'           => array('!'),
  ));

VRoute::assign('vanemart::contacts', '(:bundle)/help/contacts');
