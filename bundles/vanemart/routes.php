<?php
use Vane\Route as VRoute;

VRoute::on('(:bundle)');

VRoute::on('(:bundle)/groups/(\d+-?[^/]*)')
  ->as('vanemart::group')
  ->baseLayouts('goods')
  ->layout(array(
  ));

VRoute::on('(:bundle)/goods/(\d+-?[^/]*)')
  ->as('vanemart::product')
  ->baseLayouts('goods')
  ->layout(array(
  ));

