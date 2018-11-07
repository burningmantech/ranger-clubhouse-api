<?php


namespace App\Models;

abstract class ApihouseResult {
    public function toArray() {
        return json_decode(json_encode($this),true);
    }
}
