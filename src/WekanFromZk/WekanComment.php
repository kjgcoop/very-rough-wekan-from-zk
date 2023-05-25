<?php

namespace Kjgcoop\WekanFromZk;

class WekanComment {
    protected $host;
    protected $token;

    protected $comment;

    function __construct($comment, $host, $token) {
        $this->host  = $host;
        $this->token = $token;

        $this->comment = $comment;
    }

    function getComment() {
        return $this->comment;
    }

    public function create($board_id, $card_id, $author_id) {
        $headers = array(
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
        );

        $client = new \GuzzleHttp\Client();

        // Define array of request body.
        $request_body = array(
            'body'     => '',
            'authorId' => $author_id,
            'comment'  => $this->getComment(),
        );

        try {
            $response = $client->request('POST', $this->host.'/api/boards/'.$board_id.'/cards/'.$card_id.'/comments', array(
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


