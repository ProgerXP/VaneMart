<?php

return array(
  'ignore' => array(E_NOTICE, E_WARNING, E_ERROR),
  'detail' => Laravel\Request::ip() === '46.32.69.67',
  'log' => true,
  'log_skip' => array('info'),
);