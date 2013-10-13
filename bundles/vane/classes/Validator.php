<?php namespace Vane;

class Validator extends \Px\Validator {
  function validate_vanemart_req_if($attribute, $value, $parameters) {
    list($other, $operator, $rightValue) = preg_split('/\s+/', $parameters[0], 3);
    $leftValue = array_get($this->attributes, $other);
    $code = 'return !("'.addslashes($leftValue).'" '.$operator.' "'.addslashes($rightValue).'");';
    return eval($code);
  }

  protected function implicit($rule) {
    return $rule == 'required' or $rule == 'accepted' or $rule == 'required_with' or
           $rule == 'vanemart_req_if';
  }
}