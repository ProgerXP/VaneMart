<?php
return array(
  'from'                  => Vane\Current::config('company.email'),
  'returnPath'            => null,
  'copyTo'                => array(),
  'bccTo'                 => array(),
  'headers'               => array(),
  'allowedHeaders'        => array(),
  'bodyEncoding'          => 'base64',
  'bodyCharsets'          => array(),
  'forceBodyCharset'      => false,
  'makeTextBodyFromHTML'  => true,
  'textBodyFormat'        => 'flowed',
  'allowedAttachments'    => '1',
  'headerEOLN'            => "\n",
  'sortHeaders'           => true,
  // sendmail CLI parameters.
  'params'                => true,
  'eoln'                  => "\r\n",
  'skipRelatedAttIfNoHtmlBody'  => true,
  // 0 (low), 1 (normal), 2 (high).
  'priority'              => 1,

  'simulateSending'       => Request::is_env('local'),
  'echoPath'              =>
    Request::is_env('local') ? Bundle::path('vanemart').'storage/mail/' : false,
);