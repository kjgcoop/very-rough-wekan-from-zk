<?php
require('vendor/autoload.php');

// Wekan API documentation: https://wekan.github.io/api/v5.80/?php#get_all_lists

// I had trouble programmatically getting a token, however when I sent a curl
// request to http://wekan:3001/users/login -d "username=whatever&password=whatever",
// I got the token, which I hard-coded in my config file.

use \Kjgcoop\WekanFromZk\WekanBoard;
use \Kjgcoop\WekanFromZk\WekanList;
use \Kjgcoop\WekanFromZk\WekanCard;
use \Kjgcoop\WekanFromZk\WekanTag;
use \Kjgcoop\WekanFromZk\WekanComment;
use \Kjgcoop\WekanFromZk\WekanChecklist;

/**
 * @todo
 *   - Attachments, what are those?
 *        - in [big hash]_filesData
 *        - Example: https://projects.zenkit.com/api/v1/lists/2392635/files/4609287 has "id": "4609287, and "listId": 2392635
 *        - Do all files follow that format?
 *   - Detect dupicate boards based on name
 *   - Lists are in no particular order
 *   - Get the boards from ZK directly; I think it returns the same json the
 *     manual export returns, so it should be pretty simple. However, see
 *     https://www.globalnerdy.com/2021/06/07/the-programmers-credo/
 */


// Same as $settings['W_UID'] but don't need to hard-code;
function getUserToUse($settings) {

    $client = new \GuzzleHttp\Client();

    $headers = [
        'Accept'        => 'application/json',
        'Authorization' => 'Bearer '.$settings['TOKEN'],
    ];

    try {
        $path = $settings['W_HOST'].'/api/users';
        $response = $client->request('GET', $path, array(
            'headers' => $headers,
            'json'    => [],
        ));
        echo "Issued request for users at $path\n";
        $users = json_decode($response->getBody()->getContents());
        echo "Resulting json: ".json_encode($users)."\n";

        if (isset($result->error) && $result->error != '') {
            die('No result from getting users'."\n");
        }

        foreach ($users as $user) {
            if ($user->username === $settings['WRITE_AS']) {
                die('Found user '.$user->username);
                return $user;
            }
        }

        throw new Exception('Could not find user '.$settings['WRITE_AS']);

    }
    catch (\Exception $e) {
        // handle exception or api errors.
        print_r($e->getMessage());
die();
        return false;
    }


}

function login($settings) {

    $client = new \GuzzleHttp\Client();

    // Define array of request body.
    $request_body = array(
        'username' => $settings['W_USER'],
        'password' => $settings['W_PASS'],
    );

    $headers = [
        'Accept' => '*/*',
        'Content-Type' => 'application/x-www-form-urlencoded',
    ];

    try {
        $login_path = $settings['W_HOST'].'/users/login';
        $response = $client->request('POST', $login_path, array(
            'headers' => $headers,
            'json'    => $request_body,
        ));
        echo "Issued request to user login at $login_path\n";


        $result = json_decode($response->getBody()->getContents());
        echo "Body: ".json_encode($request_body)."\n";
        echo "Resulting json: ".json_encode($result)."\n";


        if (isset($result->error) && $result->error != '') {
            die('No result from login: '."\n");
        }
    }
    catch (\GuzzleHttp\Exception\BadResponseException $e) {
        echo "Got an exception from $login_path:\n";

        // handle exception or api errors.
        print_r($e->getMessage());
        return false;
    }
}

// Grab the config values - don't let the program edit them.
$env = 'dev';
$settings_file = '.'.$env;

// If a list has this name, don't create it.
$skip_list = 'Done';

echo "About to read config files for environment $env at $settings_file\n";


if (file_exists($settings_file)) {
    $settings = parse_ini_file($settings_file);
    echo "Settings found in $settings_file:\n";
    print_r($settings);
    echo "\n";

} else {
    die("Could not include non-existant file at $settings_file\n");
}

echo "\nLogged in as ".$settings['W_USER']." and received token ".$settings['TOKEN']."\n";

if (!file_exists($settings['ZK_JSON_DIR'])) {
    die("Couldn't find JSON directory at ".$settings['ZK_JSON_DIR']."\n");
}

$files = glob($settings['ZK_JSON_DIR']."/*.json");

foreach ($files as $file) {
    if (!file_exists($file)) {
        die("Couldn't find JSON file at $file\n");
    }

    // Get the JSON
    echo "About to get JSON from file $file\n\n";
    $json  = json_decode(file_get_contents($file));
    $board = new WekanBoard($json, $settings['W_HOST'], $settings['TOKEN'], $settings['DEBUG']);

    echo "\nCreate board ".$board->getName()." in Wekan\n";
    $board->create($settings['W_UID']);

    echo "\n";
}
