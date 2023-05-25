<?php

namespace Kjgcoop\WekanFromZk;

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
print_r('About to create a checklist with title '.$this->title."\n");
        $headers = array(
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
        );

        $request_body = array(
            'body'  => 'A body or something',
            'title' => $this->title,
//            'items' => $this->items,
        );

        $client = new \GuzzleHttp\Client();

        try {
            echo "Checklist request going to: ".$this->host.'/api/boards/'.$board_id.'/cards/'.$card_id."/checklists\n";

            $response = $client->request('POST', $this->host.'/api/boards/'.$board_id.'/cards/'.$card_id.'/checklists', array(
                'headers' => $headers,
                'json'    => $request_body,
            ));

echo "Checklist creation response: \n";
//            print_r($response->getBody()->getContents());
            $result = json_decode($response->getBody()->getContents());
//var_dump($result);

//die("\n".'What was the body of the checklist-creation response?: '.$result->_id."\n");

            foreach ($this->items as $item) {
                $item->create($board_id, $card_id, $result->_id);
            }

die("Attempted to create a checklist\n");
        }
        catch (\GuzzleHttp\Exception\BadResponseException $e) {
            // handle exception or api errors.
            print_r($e->getMessage());
            die('Exception getting checklists');
        }
    }
}


