<?php
use Vane\Route as VRoute;

VRoute::on('(:bundle)');

VRoute::on('(:bundle)/groups/(\d+-?[^/]*)')
  ->as('vanemart::group')
  ->servers('VaneMart::group')
  ->layout(array(
    '=nav items title'    => array('VaneMart::group@title'),
    '=nav items list'     => array('!'),
  ));

VRoute::on('(:bundle)/goods/(\d+-?[^/]*)')
  ->as('vanemart::product')
  ->baseLayouts('goods')
  ->layout(array(
  ));

VRoute::on('GET (:bundle)/thumb')
  ->as('vanemart::thumb')
  ->naked('VaneMart::thumb');
