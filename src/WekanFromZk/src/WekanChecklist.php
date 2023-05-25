<?php

namespace kjgcoop\WekanFromZk;

class WekanChecklist {
    protected $host;
    protected $token;

    protected $title;
    protected $items = [];

    function __construct($title, $items, $host, $token) {
        $this->host  = $host;
        $this->token = $token;

        $this->title = $title;

        foreach ($items as $item) {
            $this->items[] = new WekanChecklistItem($item->text, $item->checked, $this->host, $this->token);
        }
    }

    function getItems() {
        return $this->items;
    }

    function getTitle() {
        return $this->title;
    }

    public function create($board_id, $card_id) {
        $headers = array(
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
        );

        $request_body = array(
            'title' => $this->title,
            'items' => $this->items,
        );

        $client = new \GuzzleHttp\Client();

        try {
            $response = $client->request('POST', $this->host.'/api/boards/'.$board_id.'/cards/'.$card_id.'/checklists', array(
                'headers' => $headers,
                'json'    => $request_body,
            ));

            print_r($response->getBody()->getContents());
        }
        catch (\GuzzleHttp\Exception\BadResponseException $e) {
            // handle exception or api errors.
            print_r($e->getMessage());
        }
    }
}


