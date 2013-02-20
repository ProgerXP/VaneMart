<!DOCTYPE html>
<html lang="{{ Config::get('application.language') }}">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title }}</title>

    <meta name="description" content="VaneMart - the flowing e-commerce software.">
    <meta name="robots" content="index,follow">
    <meta name="generator" content="VaneMart">

    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">

    {{ Asset::styles() }}

    @if (Request::is_env('local'))
      <link href="{{ VaneMart\asset('styles.less') }}" rel="stylesheet/less">
      <script>var less = {env: 'development'};</script>
      <script type="text/javascript" src="{{ VaneMart\asset('less.js') }}"></script>
    @else
      <link href="{{ VaneMart\asset('styles.css') }}" media="all" type="text/css" rel="stylesheet">
    @endif

    <!--[if lt IE 9]>
      <script src="{{ VaneMart\asset('ie9.js') }}"></script>
    <![endif]-->
  </head>
  <body>
    @if (isset(Section::$sections['content']))
      @yield('content')
    @else
      {{ $content }}
    @endif

    <script type="text/javascript" src="{{ action('vanemart::js@env') }}"></script>
    {{ Asset::scripts() }}
  </body>
</html>