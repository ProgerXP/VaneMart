<?php
return array(
  'table_prefix'          => 'vm_',
  'min_subtotal'          => 800,
  // regular space-separated list of user perms, e.g. 'cart.disable order.*'.
  'guest_perms'           => '',
  'max_attaching_files'   => 10,
  'new_order_manager'     => null,
  // array of user IDs to mail on new post: array(1, 2, ...) - inexistent are skipped.
  'post_notify_users'     => array(),
  'default_city'          => '',
  'user_fields'           => array(
    'user' => array(
      'patronymic' => 'required',
      'city' => 'required|min:2',
    ),
    'order' => array(
      'patronymic' => 'required',
      'city' => 'required|min:2',
    ),
  ),
);