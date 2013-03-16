<?php
return array(
  'empty'                 => 'Ваша корзина пуста.',
  'summary'               => ':items на сумму :sum',

  'ctlstatus'             => array(
    'add_one'             => ':title теперь в вашей корзине.',
    'add_many'            => 'Товары были помещены в корзину.',
    'remove'              => 'Товар :title был удалён из корзины.',
    'clear'               => 'Ваша корзина теперь пуста.',
  ),

  // cart@index
  'index'                 => array(
    'title'               => 'Ваша корзина',
  ),

  // cart@set
  'set'                   => array(
    // used by goods.ki view in 'edit mode'
    'clear'               => 'Очистить корзину',
    'update'              => 'Пересчитать',
    'delete'              => '(:0удалить:1)',
  ),

  // cart@add_sku
  'sku'                   => array(
    'submit'              => 'Добавить',
    'checkout'            => 'Оформить',
    'sku'                 =>
      'Введите артикулы через пробел, чтобы добавить товары по коду.'.
      ' Можно ввести артикул больше одного раза для добавления несколько единиц товара.',
  ),
);