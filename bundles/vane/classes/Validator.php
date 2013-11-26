<?php namespace Vane;

class Validator extends \Px\Validator {
  function validate_vane_required_if($attribute, $value, $parameters) {
    list($other, $operator, $rightValue) = preg_split('/\s+/', $parameters[0], 3);
    $leftValue = (array) array_get($this->attributes, $other);
    $rightValue = (array) $rightValue;
    $leftValue = array_shift($leftValue);
    $rightValue = array_shift($rightValue);

    $code = 'return !($leftValue '.$operator.' $rightValue);';
    return eval($code) or $this->validate_required($attribute, $value);
  }

  protected function implicit($rule) {
    return parent::implicit($rule) or $rule == 'vane_required_if';
  }
}