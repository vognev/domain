<?php

interface Domain_Entity_Interface
{
    public function fromArray($data);

    public function toArray();

    public function set($property, $value);
}