<?php
use Vane\Route as VRoute;

VRoute::on('(:bundle)')
  ->layout(array(
    '+nav #group list'    => array('VaneMart::group@byList'),
  ));

VRoute::on('(:bundle)/groups/(\d+-?[^/]*)')
  ->as('vanemart::group')
  ->servers('VaneMart::group')
  ->layout(array(
    '+nav #group title'   => array('VaneMart::group@title'),
    '+nav #group list'    => array('!'),
  ));

VRoute::on('(:bundle)/goods/(\d+-?[^/]*)')
  ->as('vanemart::product')
  ->servers('VaneMart::product')
  ->layout(array(
    '+nav #group title'   => array('VaneMart::group@titleByProduct (:1)'),
    '+nav #group list'    => array('VaneMart::group@listByProduct (:1)'),
    '+#content'           => array('!'),
  ));

VRoute::on('(:bundle)/cart/(:any?)/(:num?)')
  ->as('vanemart::cart')
  ->servers('VaneMart::cart@(:1)')
  ->layout(array(
    '+nav #group list'    => array('VaneMart::group@byList cart'),
    '+#content'           => array('!'),
  ));

VRoute::on('(:bundle)/checkout')
  ->as('vanemart::checkout')
  ->servers('VaneMart::checkout')
  ->layout(array(
    '+nav #group list'    => array('VaneMart::group@byList checkout'),
    '+#content'           => array('!'),
  ));

VRoute::on('(:bundle)/orders')
  ->as('vanemart::orders')
  ->servers('VaneMart::order')
  ->layout(array(
    '+#content'           => array('!'),
  ));

VRoute::on('(:bundle)/orders/(:num)')
  ->as('vanemart::order')
  ->servers('VaneMart::order@show')
  ->layout(array(
    '+#content'           => array('!'),
  ));

VRoute::on('GET (:bundle)/thumb')
  ->as('vanemart::thumb')
  ->naked('VaneMart::thumb');

VRoute::assign('vanemart::contacts', '(:bundle)/help/contacts');
