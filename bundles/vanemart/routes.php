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
    '=nav #group list'    => array('VaneMart::group@listByProduct (:1)'),
    '=#content'           => array('!'),
  ));

VRoute::on('GET (:bundle)/thumb')
  ->as('vanemart::thumb')
  ->naked('VaneMart::thumb');

VRoute::assign('vanemart::contacts', '(:bundle)/help/contacts');
