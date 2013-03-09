<?php
return array(
  'title'                 => 'Ваши заказы',
  'total'                 => 'Общая сумма:',
  'link'                  => 'Отслеживание:',
  'link_text'             => '[ :0ссылка:1 ]:',
  'posts'                 => 'Вопросы по вашему заказу',

  'status'                => array(
    'new'                 => 'Новый',
  ),

  'ctlstatus'             => array(
    'set'                 => 'Параметры заказа были изменены.',
  ),

  // order@index
  'index'                 => array(
    'item_title'          => 'Заказ №:id от :created',
    'from'                => ':surname :name',
    'count_sum'           => ':counts на сумму :sums',
    'info'                => ':status, обновлён :updated',
  ),

  // order@show
  'show'                  => array(
    'title'               => 'Заказ №:0',
  ),

  // POST order@show
  'set'                   => array(
    'post'                => "Параметры заказа были изменены:\n:0",
    'line'                => array(
      'add'               => '  * :field :new',
      'set'               => '  * :field :old → :new',
      'delete'            => '  * :field (удалено :old)',
    ),

    // used by order@show in 'edit mode' (e.g. for managers)
    'submit'              => 'Сохранить изменения',
    'change_to'           => '— изменить на:',
    'relink'              => 'Создать новую',
  ),
);