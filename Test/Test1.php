<?php
class B {
  public function b() {
    $a = new A();
    $b = array();
    foreach ($a->a() as $value) {
      $b[$value] = 'a';
    }
  }
}
