<?php
namespace Copot\Core;
final class MediaId { public function __construct(private int $value){if($value<=0)throw new \InvalidArgumentException('Media ID must be positive.');} public function value():int{return $this->value;} }
