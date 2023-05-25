<?php

namespace Kjgcoop\WekanFromZk;

class WekanCard {

    protected $w_id;

    protected $host;
    protected $token;

    protected $board_id;

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
    protected $boardTags  = [];
    protected $tagIds     = [];
    // protected $subtasks?

    protected $tagBatchName = '_categories_sort';

    function __construct($entry, $host, $token, $boardTags) {
        $this->host  = $host;
        $this->token = $token;

        $this->boardTags = $boardTags;

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

        $this->w_id = $entry->id;

        // Loop through object properties
        foreach ($entry as $key => $value) {
            if ($this->propertyIsTagBatch($key)) {
                foreach ($value as $unparsedTags) {
                    // Create the label
                    $newTag = new WekanTag($unparsedTags->name, $this->host, $this->token);

                    $this->tags[] = $newTag;

                    // Remember that it exists in the board
                    $this->boardTags[] = $newTag;
                }
            } elseif ($key === 'checklists') {
                foreach ($value as $checklist) {
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

    public function getUpdatedBoardTags() {
        return $this->boardTags;
    }

    public function boardHasLabel($key) {
        return isset($this->tags[$key]);
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

            print_r('Run request to '.$this->host.'/api/boards/'.$board_id.'/lists/'.$list_id.'/cards'."\n");

            $result = $this->runRequest($this->host.'/api/boards/'.$board_id.'/lists/'.$list_id.'/cards', $request_body);
            $this->w_id = $result->_id;

            echo "Checklists (can create the list but not the items) and tags (hangs when you try to write) are a work in progress\n"; // @todo

        }
        catch (\GuzzleHttp\Exception\BadResponseException $e) {
            // handle exception or api errors.
            print_r($e->getMessage());
            return false;
        }
/*
        foreach ($this->tags as $tag) {
            $this->tagIds[] = $tag->create($board_id);
        }
*/
        foreach ($this->checklists as $checklist) {
            // @todo Fix - creates a checklist but no items. Documentation
            // doesn't offer much guidance regarding what it's expecting of
            // checklist items.
            $checklist->create($board_id, $this->w_id);
        }

        foreach ($this->comments as $comment) {
            $comment->create($board_id, $this->w_id, $uid);
        }


        if (empty($this->dueAt) && empty($this->receivedAt) && empty($this->tagIds)) {
            return true;
        }

        // List of all editable values: https://wekan.github.io/api/v5.83/#edit_card
        // Some values can be edited on update but not creation
        $update = [
/*          "title"        => " string",
            "sort"         => " string", // @todo is this being retained from ZK?
            "parentId"     => " string",
            "description"  => " string",
            "color"        => " string",
            "vote"         => " {}",
            "poker"        => " {}",*/
            "labelIds"     => implode($this->tagIds, ','),
/*            "requestedBy"  => " string",
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

    public function getId() {
        return $this->w_id;
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

    public function getZkToken() {
        return $this->zk_token;
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
