<?php
return array(
  'logo'                  => VaneMart\asset('vanemart::logo.png'),
  'short'                 => 'VaneMart',
  'long'                  => 'VaneMart - flowing e-commerce software',
  'motto'                 => 'where the trend goes',
  'landline'              => array('995-99-05'),
  'cellular'              => array('+7 (900) 12-45-67'),
  'address'               => array(
    array(
      'country'           => 'Russia',
      'city'              => 'Saint-Petersburg',
      'address'           => 'Krasnaya, 13, 2',
      'map'               => '',    // URL
    ),
  ),
  'contactsURL'           => route('vanemart::contacts'),
);