<?php

namespace kjgcoop\WekanFromZk;

// Wekan calls these Labels. Whatever.
class WekanTag {
    protected $host;
    protected $token;

    protected $name;
    protected $color;

    function __construct($name, $color, $host, $token) {
        $this->host  = $host;
        $this->token = $token;

        $this->name = $name;

        // I didn't see a way to import this into Wekan :(
        $this->color = $color;

    }

    public function create($board_id) {
        echo "About to create tag {$this->name}\n";

        $headers = array(
//            'Content-type' => 'multipart/form-data',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
        );

        $client = new \GuzzleHttp\Client();

        // Define array of request body.
        $request_body = array(
            'body'  => '',
            'label' => $this->name,
            'color' => 'red'
        );

        try {
            $response = $client->request('PUT', $this->host."/api/boards/$board_id/labels", array(
                'headers' => $headers,
                'json'    => $request_body,
            ));

            // https://github.com/guzzle/guzzle/issues/1841
            // Internet says this works; I beg to differ:
/*            $response = $client->put($this->host."/api/boards/$board_id/labels", [
                'headers'     => $headers,
                'form-params' => $request_body
            ]);
*/

            $result = json_decode($response->getBody()->getContents());

            print_r("Result of tag creation: $result with status code ".$response->getStatusCode()."\n");
            die();
//            die('Result above');

//            $this->w_id = $result->_id;
        }
        catch (\GuzzleHttp\Exception\BadResponseException $e) {
            // handle exception or api errors.
            print_r($e->getMessage());
            die('Curl exception');
        }
    }

    public function getName() {
        return $this->name;
    }

}


