<?php

namespace Kjgcoop\WekanFromZk;

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



    public function create($board_id, $card_id, $checklist_id) {
print_r('About to create a checklist with title '.$this->title."\n");
        $headers = array(
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
        );

        $request_body = array(
            'body' => 'A checklist item body or something',
            'isFinished' => $this->isFinished,
            'title' => $this->title,
        );

        $client = new \GuzzleHttp\Client();

        try {
            echo "Checklist request going to: ".$this->host.'/api/boards/'.$board_id.'/cards/'.$card_id."/checklists/".$checklist_id."/items\n";

            $response = $client->request('POST', $this->host.'/api/boards/'.$board_id.'/cards/'.$card_id."/checklists/".$checklist_id."/items", array(
                'headers' => $headers,
                'json'    => $request_body,
            ));

            print_r($response->getBody()->getContents());
die("Attempted to create a checklist\n");
        }
        catch (\GuzzleHttp\Exception\BadResponseException $e) {
            // handle exception or api errors.
            print_r($e->getMessage());
            die('Exception getting checklists');
        }
    }


}


