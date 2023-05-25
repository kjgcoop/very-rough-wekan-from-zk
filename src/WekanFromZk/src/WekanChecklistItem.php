<?php

namespace kjgcoop\WekanFromZk;

class WekanChecklistItem {
    public $title;
    public $isFinished;

    protected $host;
    protected $token;

    function __construct($title, $finished, $host, $token) {
        $this->host  = $host;
        $this->token = $token;

        $this->title = $title;
        $this->isFinished = (bool)$finished;
    }

    function getTitle() {
        return $this->title;
    }

    function getFinishedString() {
        if ($this->isFinished) {
            return 'finished';
        } else {
            return 'not finished';
        }
    }
}


