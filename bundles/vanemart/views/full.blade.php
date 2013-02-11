<!DOCTYPE html>
<html lang="{{ Config::get('application.language') }}">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title }}</title>

    <meta name="description" content="VaneMart - the flowing e-commerce software.">
    <meta name="robots" content="index,follow">
    <meta name="generator" content="Vane engine">

    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">

    {{ Asset::styles() }}

    <!--[if lt IE 9]>
      <script src="{{ asset('js/ie9.js') }}"></script>
    <![endif]-->
  </head>
  <body>
    @yield('content')

    <script type="text/javascript" src="{{ action('js/env') }}"></script>
    {{ Asset::scripts() }}
  </body>
</html>