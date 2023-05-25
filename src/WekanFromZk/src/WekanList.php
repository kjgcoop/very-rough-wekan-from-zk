<?php

namespace kjgcoop\WekanFromZk;

class WekanList {
    protected $w_id;

    protected $host;
    protected $token;

    protected $name;
    protected $cards = [];

    function __construct($name, $host, $token) {
        $this->host  = $host;
        $this->token = $token;

        $this->name = $name;
    }

    // entry is what the ZK dump calls a card.
    function addCardFromEntry($entry, $key) {

        $card = new WekanCard($entry, $this->host, $this->token);
        $this->cards[$card->getTitle()] = $card;
    }

    function addCommentToCard($comment, $cardName) {
        if (isset($this->cards[$cardName])) {
            $this->cards[$cardName]->addComment($comment);
        } else {
            print_r($this->cards);
            die("Trying to add comment to non-existant card ($cardName) in list ".$this->getName()."\n");
        }
    }

    function create($board_id, $swim_id, $uid) {

        $headers = array(
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
        );

        $client = new \GuzzleHttp\Client();

        // Define array of request body
        $request_body = array('title' => $this->getName());

        try {
            echo "Now creating list ".$this->getName()."\n";
            $response = $client->request('POST', $this->host.'/api/boards/'.$board_id.'/lists', array(
                'headers' => $headers,
                'json'    => $request_body,
            ));
            $result = json_decode($response->getBody()->getContents());
//            print_r($result);

            $this->w_id = $result->_id;
//            echo "Created list ".$this->getName()."\n";

        }
        catch (\GuzzleHttp\Exception\BadResponseException $e) {
            // handle exception or api errors.
            print_r($e->getMessage());
            return false;
        }

        echo "About to create cards.\n";
        foreach ($this->cards as $card) {
            // @todo What do we do if this fails?
            $card->create($board_id, $this->w_id, $swim_id, $uid);
        }

        return true;

    }

    function getCards() {
        return $this->cards;
    }

    function getName() {
        return $this->name;
    }

    function hasCardByName($cardName) {
        return isset($this->cards[$cardName]);
    }
}


