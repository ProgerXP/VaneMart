<?php

if (Laravel\Request::cli()) {
  Laravel\Request::set_env(getenv('comspec') ? 'local' : 'production');
}

Laravel\Bundle::start('plarx');
