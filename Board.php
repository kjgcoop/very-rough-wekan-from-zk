<?php

namespace \kjgcoop\Wekan;

class Board {
    protected $rawJson;
    protected $fromZk;

    protected $displayString;
    protected $lists;

    function __construct($json) {
        $this->rawJson = $json
        $this->fromZk = json_decode($this->rawJson);

        // $this->fromZk->list contains details about the board
        $this->name = $this->fromZk->list->name;

        // $config['incoming']->entries represents the cards


    }


}


