<?php namespace Vane;

class Event extends \Px\Event {
  static function fire($events, $parameters = array(), $halt = false) {
    $expand = function ($event) {
      if (strpos($event, '::') === false) {
        return Current::$ns.$event;
      }
    };

    $events = array_map($expand, (array) $events);
    return parent::fire($events, $parameters, $halt);
  }
}