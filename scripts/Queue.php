<?php

/**
 * @author              Dmitriy Dergachev (ArtMares)
 * @date                28.03.2017
 * @copyright           artmares@influ.su
 */
class Queue {
    
    protected $queue = [];
    
    public function put($value) {
        $this->queue[] = $value;
    }
    
    public function get() {
        $value = array_shift($this->queue);
        return $value;
    }
    
    public function isEmpty() {
        $result = count($this->queue) > 0 ? false : true;
        return $result;
    }
}