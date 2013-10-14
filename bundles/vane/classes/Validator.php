<?php namespace Vane;

class Validator extends \Px\Validator {
  function validate_vanemart_req_if($attribute, $value, $parameters) {
    list($other, $operator, $rightValue) = preg_split('/\s+/', $parameters[0], 3);
    $leftValue = (array) array_get($this->attributes, $other);
    $rightValue = (array) $rightValue;
    $leftValue = array_shift($leftValue);
    $rightValue = array_shift($rightValue);

    $code = 'return !($leftValue '.$operator.' $rightValue);';
    return eval($code);
  }

  protected function implicit($rule) {
    return $rule == 'required' or $rule == 'accepted' or $rule == 'required_with' or
           $rule == 'vanemart_req_if';
  }
}