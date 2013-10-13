<?php
return array(
  'ctlstatus'             => array(
    'logout'              => 'Всего доброго! Ждём вас снова.',
    'reset_instructions'  => 'Инструкции по смене пароля были отправлены вам на e-mail.',
    'new_password'        => 'Пароль успешно изменён. Новый пароль был выслан на ваш e-mail.',
  ),

  'mail'                  => array(
    'reg_on_order'        => array(
      'subject'           => ':short — ваш первый заказ',
      'date'              => 'Дата регистрации:',
      'intro'             => ':name :surname,',
      'legend'            => 'Ваш e-mail был автоматически зарегистрирован, так как вы недавно сделали заказ в нашем интернет-магазине.',
      'login'             => 'В любой момент вы можете :l0войти в ваш Личный кабинет:l1 и проверить историю ваших заказов.',
      'thanks'            => 'Сердечно благодарим вас за покупку и надеемся, что она будет лишь одной из многих!',
    ),
    'reset_instructions'  => array(
      'subject'           => ':short — запрос на смену пароля',
      'intro'             => 'Запрос на смену пароля',
      'link'              => 'Для сброса вашего пароля перейдите по :l0этой ссылке:l1.',
    ),
    'new_password'        => array(
      'subject'           => ':short — ваш новый пароль',
      'intro'             => 'Ваш новый пароль',
      'link'              => 'Введите пароль ниже :l0в форме входа в ваш личный кабинет:l1.',
      'password'          => ':password',
    ),
  ),

  // user@login
  'login'                 => array(
    'title'               => 'Вход в ваш кабинет',
    'submit'              => 'Продолжить',
    'reg'                 => 'Регистрация',
    'reset'               => 'Забыли пароль?',
    'legend'              => 'Если вы уже делали заказ в нашем магазине, то ваш кабинет был создан автоматически.<br>Пароль был выслан вам на почту, указанную при совершении первого заказа.',
    'remember'            => 'Входить автоматически',
    'wrong'               => 'Неверные данные.',
  ),

  // user@reg
  'reg'                   => array(
    'title'               => 'Регистрация',
    'submit'              => 'Зарегистрироваться',
    'legend'              => 'Если вы уже делали у нас заказ, то ваш e-mail был зарегистрирован автоматически — воспользуйтесь :0страницей входа:1.',
    'ordered'             => 'Я уже делал заказ',
    'referee'             => 'Мой друг:',
    'reflegend'           => 'Если вас пригласил наш покупатель, вы можете ввести его номер или e-mail — он получит от нас небольшой подарок.',
  ),

  'reset'                 => array(
    'title'               => 'Сброс пароля',
    'unknown_email'       => 'Такой e-mail не зарегистрирован.',
    'other_error'         => 'Ссылка устарела. Заполните форму и повторите запрос.',
    'legend'              => 'Инструкции по смене пароля будут отправлены на указанный e-mail.',
    'submit'              => 'Отправить',
  ),

  // for error.401 view
  'unauth'                => array(
    'required'            => 'Эта страница требует, чтобы вы :0вошли в систему:1.',
  ),
);