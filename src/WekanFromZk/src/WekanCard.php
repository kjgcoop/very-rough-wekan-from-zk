<?php

namespace kjgcoop\WekanFromZk;

class WekanCard {

    protected $w_id;

    protected $host;
    protected $token;

    // ZK has a bunch of properties not supported by Wekan's creation API :(
    // Wekan's add API has a bunch of properties not in use with incoming ZK
    // data -- ignore.
    protected $title;       // ZK calls this displayString
    protected $description;
    protected $dueAt;       // ZK calls this [big hash]_date
    protected $receivedAt;

    protected $checklists = [];
    protected $comments   = [];
    protected $tags       = [];
    // protected $subtasks?

    protected $tagBatchName = '_categories_sort';

    function __construct($entry, $host, $token) {
        $this->host  = $host;
        $this->token = $token;

        if (empty($entry->displayString)) {
            $this->title = 'No title';
        } else {
            $this->title = $entry->displayString;
        }

        if (empty($entry->description)) {
            $this->description = '';
        } else {
            $this->description = $entry->description;
        }

        // Using ZK's created_at - they're not the same, but it's the closest
        // I can get.
        if (isset($entry->created_at) && strtotime($entry->created_at) !== false) {
            $this->receivedAt = $entry->created_at;
        }

        // Loop through object properties
        foreach ($entry as $key => $value) {

            if ($this->propertyIsTagBatch($key)) {
                foreach ($value as $unparsedTags) {
                    $this->tags[$unparsedTags->name.'-'.$unparsedTags->colorHex] = new WekanTag($unparsedTags->name, $unparsedTags->colorHex, $this->host, $this->token);
                }
            } elseif ($key === 'checklists') {
                foreach ($value as $checklist) {
                    //print_r($checklist);
                    //die('Checklist');

                    $this->checklists[] = new WekanChecklist($checklist->name, $checklist->items, $this->host, $this->token);
                }

            } elseif ($key === 'commentCard') {
                $this->comments = $value;
            } elseif ($this->isDueDate($key, $value)) {
                $this->dueAt = date('Y-m-d H:i:s', strtotime($value));

            // Wekan API doesn't support creating attachments, but, when it does
            // (when!), this code will be ready.
            } elseif ($this->isAttachment($key) && !empty($value)) {

                // Example: https://projects.zenkit.com/api/v1/lists/2392635/files/4609287 has "id": "4609287, and "listId": 2392635
                // @todo Is there always exactly one array item per attachment?
                $details = array_pop($value);
                $url     = 'https://projects.zenkit.com/api/v1/lists/'.$details->listId.'/files/'.$details->id;

                echo "\t\tFound an attachment for ".$this->getTitle()." at $url, not that I can do anything with it. <----------------\n";
            }
        }
    }

    public function addComment($comment) {
        $this->comments[] = $comment;
    }

    public function isDueDate($key, $value) {
        $datePhrase = '_date';
        return is_scalar($value) && substr($key, 0-(strlen($datePhrase))) === $datePhrase && @strtotime($value) !== false;
    }

    public function runRequest($url, $request_body, $method = 'POST') {

        $headers = array(
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
        );

        $client = new \GuzzleHttp\Client();

        try {
            echo "Running request to $url for card ".$this->getTitle()."\n";

            $response = $client->request($method, $url, array(
                'headers' => $headers,
                'json' => $request_body,
            ));

            return json_decode($response->getBody()->getContents());
        }
        catch (\GuzzleHttp\Exception\BadResponseException $e) {
            // handle exception or api errors.
            print_r($e->getMessage());
            return false;
        }
    }

    public function isAttachment($key) {
        $keyEndsWith = '_filesData';
        return substr($key, 0-(strlen($keyEndsWith))) === $keyEndsWith;
    }

    // Can't set the due date on initial add - must make a second call to edit
    // the newly-created card.
    public function create($board_id, $list_id, $swim_id, $uid) {

        // Temporarily comment this out so I can run a bunch of tests without
        // creating too much garbage (garbage boards and lists will still be
        // created).
        //return true;
        try {
            echo "Now creating card ".$this->getTitle()."\n";

            // Define array of request body.
            $request_body = array(
                'authorId'    => $uid,
    //            'members'     => '', // optional
    //            'assignees'   => '', // optional
                'title'       => $this->getTitle(),
                'description' => $this->getDescription(),
                'swimlaneId'  => $swim_id
            );

            $result = $this->runRequest($this->host.'/api/boards/'.$board_id.'/lists/'.$list_id.'/cards', $request_body);
            $this->w_id = $result->_id;

            echo "Checklists and tags are a work in progress\n"; // @todo

        }
        catch (\GuzzleHttp\Exception\BadResponseException $e) {
            // handle exception or api errors.
            print_r($e->getMessage());
            return false;
        }
/*
        foreach ($this->tags as $tag) {
            $tag->create($board_id);
        }*/
/*
        foreach ($this->checklists as $checklist) {
            // @todo Fix - creates a checklist but no items. Documentation
            // doesn't offer much guidance regarding what it's expecting of
            // checklist items.
            $checklist->create($board_id, $this->w_id);
        }*/

        foreach ($this->comments as $comment) {
            $comment->create($board_id, $this->w_id);
        }


        if (empty($this->dueAt) && empty($this->receivedAt)) {
            return true;
        }

        // List of all editable values: https://wekan.github.io/api/v5.83/#edit_card
        $update = [
/*          "title"        => " string",
            "sort"         => " string", // @todo is this being retained from ZK?
            "parentId"     => " string",
            "description"  => " string",
            "color"        => " string",
            "vote"         => " {}",
            "poker"        => " {}",
            "labelIds"     => " string",
            "requestedBy"  => " string",
            "assignedBy"   => " string",*/
            "receivedAt"   => $this->receivedAt,
//            "startAt"      => " string",
            // I don't know if this is really what ZK calls it.
            'dueAt'        => $this->dueAt,
/*          "endAt"        => " string",
            "spentTime"    => " string",
            "isOverTime"   => " true",
            "customFields" => " string",
            "members"      => " string",
            "assignees"    => " string",
            "swimlaneId"   => " string",
            "listId"       => " string",
            "authorId"     => " string",
*/
        ];

        return $this->update($board_id, $list_id, $update);
    }

    public function getDescription() {
        return $this->description;
    }

    public function getDueAt() {
        return $this->dueAt;
    }

    public function getTitle() {
        return $this->title;
    }

    public function update($board_id, $list_id, $newValues) {
        try {
            $this->runRequest($this->host.'/api/boards/'.$board_id.'/lists/'.$list_id.'/cards/'.$this->w_id, $newValues, 'PUT');
        }
        catch (\GuzzleHttp\Exception\BadResponseException $e) {
            // handle exception or api errors.
            print_r($e->getMessage());
            return false;
        }

    }


    public function getChecklists() {
        return $this->checklists;
    }

    public function getComments() {
        return $this->comments;
    }

    public function getTags() {
        return $this->tags;
    }

    function propertyIsTagBatch($key) {
        return substr($key, 0-(strlen($this->tagBatchName))) === $this->tagBatchName;
    }
}
