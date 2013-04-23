<?php
return array(
  'title'                 => 'Ваши заказы',
  'total'                 => 'Общая сумма:',
  'link'                  => 'Отслеживание:',
  'link_text'             => '[ :0ссылка:1 ]:',
  'posts'                 => 'Вопросы по вашему заказу',

  'status'                => array(
    'new'                 => 'Новый',
    'received'            => 'Получен, обрабатывается',
    'invoice'             => 'Счёт на согласовании',
    'paid'                => 'Оплата получена',
    'sent'                => 'Отгружен',
  ),

  'ctlstatus'             => array(
    'set'                 => 'Параметры заказа были изменены.',
  ),

  'mail'                  => array(
    'post'                => array(
      'subject'           => 'Обновление заказа №:id — :short',
      'legend'            => ':l0Заказ №:id:l1 был обновлён:',
      'signature'         => ':name :surname',
      'to_reply'          => 'Вы можете продолжить диалог или задать любой интересующий вас вопрос :l0по этой ссылке:l1.',
    ),
  ),

  // order@index
  'index'                 => array(
    'title'               => 'Ваши заказы',
    'show_for_all'        => 'Показать заказы менеджеров',
    'item_title'          => 'Заказ №:id от :created',
    'from'                => ':surname :name',
    'for'                 => 'мен.',
    'for_manager'         => ':n0. :surname',
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
    'relink'              => 'Создать новую',
    'manager'             => ':surname :name',
    'set_manager'         => 'Назначить',
  ),

  // order@goods
  'goods'                 => array(
    'title'               => 'Заказанный товар',
  ),
);