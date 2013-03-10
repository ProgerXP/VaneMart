<?php
return array(
  // post@index
  'index'                 => array(
    'author'              => ':name :surname',
  ),

  // post@add
  'add'                   => array(
    'submit'              => 'Отправить',
    'title'               => 'Тема сообщения',
    'body'                => 'Хотите что-то сказать?',
    'attach'              => 'Прикрепить файл',
    'bodyless_fmsg'       => ':text: :files.',
    'bodyless_ftext'      => ', Добавлено $ файлов, Добавлен $ файл, Добавлено $ файла, Добавлено $ файлов',
  ),
);