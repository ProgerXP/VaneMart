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

VRoute::map('(:bundle)/cart/(:any?)', 'VaneMart::cart@(:1)', true);
VRoute::map('(:bundle)/checkout', 'VaneMart::checkout', true);

VRoute::on('(:bundle)/orders')
  ->as('vanemart::orders')
  ->servers('VaneMart::order@briefs')
  ->layout(array(
    '=nav #group title'   => array('='.__('vanemart::order.title')),
    '=nav #group list'    => array('!'),
  ));

VRoute::on('(:bundle)/orders/(:num)')
  ->as('vanemart::order')
  ->servers('VaneMart::order@show')
  ->layout(array(
    '=nav #group title'   => array('='.__('vanemart::order.title')),
    '=nav #group list'    => array('VaneMart::order@briefs'),
    '+#content'           => array('!'),
  ));

VRoute::map('(:bundle)/user/(:num)', 'VaneMart::user@show', true);
VRoute::map('(:bundle)/user/reg', 'VaneMart::user@reg', 'vanemart::register');
VRoute::map('(:bundle)/user/login', 'VaneMart::user@login', 'vanemart::login');
VRoute::map('(:bundle)/user/logout', 'VaneMart::user@logout', 'vanemart::logout');

VRoute::on('GET (:bundle)/thumb')
  ->as('vanemart::thumb')
  ->naked('VaneMart::thumb');

VRoute::on('(:bundle)/help/(:all?)')
  ->as('vanemart::help')
  ->servers('Vane::textpub help')
  ->layout(array(
    '+#content'           => array('!'),
  ));

VRoute::assign('vanemart::contacts', '(:bundle)/help/contacts');
