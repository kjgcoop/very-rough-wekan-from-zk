<?php

namespace kjgcoop\WekanFromZk;

class WekanBoard {
    protected $fromZk;

    protected $host;
    protected $token;

    // ID given to the board after it's created in Wekan
    protected $w_id;

    // Wekan supports having more than one swimlane, but for fight now this
    // script only supports one; created by Wekan when board creation API is
    // called.
    // @todo Support multiple swimlanes
    protected $w_swim_id;

    protected $name;
    protected $stage_uuid = '';

    protected $lists = [];

    // To-Do: move to config file -- in use both here and in Wekan
    protected $tagBatchName = '_categories_sort';

    function __construct($fromZk, $host, $token, $debug = false) {
        $this->fromZk = $fromZk;
        $this->host   = $host;
        $this->token  = $token;

        // $this->fromZk->list contains details about the board
        $this->name = $this->fromZk->list->name;

        if ($debug) {
            $this->name = $this->name.' '.date('U').microtime();
        }

        echo "Board: ".$this->name."\n";

        $this->parseOutLists();

        // Must do this after the lists have been parsed.
        // @todo Move this to WekanList?
        $this->parseOutCards();

        // Must do after cards have been defined above.
        $this->parseOutComments();

    }

    function getName() {
        return $this->name;
    }

    // https://wekan.github.io/api/v5.80/?php#new_board
    function create($uid) {
        echo "About to create board ".$this->getName()." and token ".$this->token." and uid $uid\n";
//        return;

        $headers = array(
            // Documentation indicates this Content-type is needed, but it makes
            // the server return a 500 error.
//            'Content-type'  => 'multipart/form-data',
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
        );

        $client = new \GuzzleHttp\Client();

        // Define array of request body.
        $request_body = array(
            'body'  => 'Body - is this the board description?',
            'title' => $this->name,
            'owner' => $uid,
/*            // All these fields are optional
            'isAdmin'       => true,
            'isActive'      => true,
            'isNoComments'  => false,
            'isCommentOnly' => false,
            'isWorker'      => false,
            'permission'    => 'private',
            'color'         => 'wisteria',*/
        );

        try {
            $response = $client->request('POST', $this->host.'/api/boards', array(
                'headers' => $headers,
                'json'    => $request_body,
            ));
            echo 'Issued request to '.$this->host.'/api/boards'."\n";
            $result = json_decode($response->getBody()->getContents());
            echo "Resulting json: ".json_encode($result)."\n";

            $this->w_id      = $result->_id;
            $this->w_swim_id = $result->defaultSwimlaneId;
        }
        catch (\GuzzleHttp\Exception\BadResponseException $e) {
            // handle exception or api errors.
            print_r($e->getMessage());
            return false;
        }

        foreach ($this->lists as $list) {
            // @todo What do we do if this fails?
            $list->create($this->w_id, $this->w_swim_id, $uid);
        }

        return true;
    }

    // Must do after cards have been parsed out.
    function parseOutComments() {
//die("Looking for comments");

        // Comments are in $this->fromZk->activities, so they're not members of
        // cards. Each activity has a type, the ones we're interested are
        // commentCard. Within that, it's got originData, and within that it has
        // data. Within that, it itemizes the board, list and card the comment
        // goes to. There are IDs, but I haven't been keeping tabs on them. If
        // you have two cards in the same list with the same name, you will have
        // big sad.
        foreach ($this->fromZk->activities as $activity) {
            if (isset($activity->originData->type) && $activity->originData->type === 'commentCard') {
//die("Comment with message ".$activity->message."\n");
                $comment  = new WekanComment($activity->message, $this->host, $this->token);

                $cardName = $activity->originData->data->card->name;

                // Can't use the list name given in the activity because that's
                // the name of the list it was in at the time the activity took
                // place.
                $listName = $this->findWhatListCardIsIn($cardName);

                if ($listName === '' || !isset($this->lists[$listName])) {
                    echo "ERROR: Couldn't find list containing a card called $cardName\n";
                } else {
                    echo "Found $cardName in $listName\n";
                    $this->lists[$listName]->addCommentToCard($comment, $cardName);
                }
            }
        }
    }

    function parseOutListNames($elementData) {
        if (isset($elementData->predefinedCategories)) {
            foreach ($elementData->predefinedCategories as $i => $cat) {
                echo "$i. Found a list, ".$cat->name."\n";
                // Only send the name because we don't care about anything else.
                $this->lists[$cat->name] = new WekanList($cat->name, $this->host, $this->token);
            }
            echo "\n";
        } else {
            die("No lists exist\n");
        }
    }

    function parseOutLists() {
        // $this->fromZk->elements represents properties of interest per card.
        // For my sample board, they are: Name, Created, Last Updated, Created
        // By, Last Updated By, Description and Stage. The lists are stored in
        // the element named "Stage". I didn't see an easy way to ascertain its
        // index. Therefore, brute force:
        foreach ($this->fromZk->elements as $element) {
//            echo "Element: ".$element->name."\n";

            if ($element->name == 'Stage') { // These are the columns
                // Need the UUID to determine which tags have been converted to
                // list heads and therefore shouldn't be applied as tags.
                $this->stage_uuid = $element->uuid;

                if (isset($element->elementData)) {
                    $this->parseOutListNames($element->elementData);
                }
                return; // There is only one "Stage" - we're done here.
            }
        }
    }

    function findWhatListCardIsIn($cardName) {
        foreach ($this->lists as $list) {
            if ($list->hasCardByName($cardName)) {
                return $list->getName();
            }
        }

        echo "ERROR: Didn't find the card $cardName\n";
    }

    function getListThisCardIsIn($entry) {
        $list_key = $this->stage_uuid.$this->tagBatchName;

        switch (count($entry->$list_key)) {
            case 0:
                return 'No list name';
            case 1:
                return $entry->$list_key[0]->name;
            default:
                echo "There is more than one stage applied to at least one task. Board requires manual attention.\n";
                die();
        }
    }


    // $this->fromZk->entries represents the cards
    function parseOutCards() {
        echo "About to add ".count($this->fromZk->entries)." cards to a list\n";
        // Looping through object properties as opposed to an array.
        foreach ($this->fromZk->entries as $key => $entry) {

            // List name tells us what list it's in
            $list_name = $this->getListThisCardIsIn($entry);

            // Remove the tag group associated with the list heads so as not to
            // duplicate them with the other tags. Must be done after getting
            // the name because we use this property to ascertain the name.
            $list_key = $this->stage_uuid.$this->tagBatchName;
            unset($entry->$list_key);

            // Now that we know the list and we've removed duplicate information
            // the WekanList class can take over. Note that this will cause
            // problems if you have two lists of the same name. I don't think
            // ZK allows that, but if it does, uhoh. Potentially.
            $this->lists[$list_name]->addCardFromEntry($entry, $key);
            echo "Added card to list $list_name: ".$entry->displayString."\n";
        }
        echo "\n";
    }

    function printSummary() {
        echo "Summary of lists and cards:\n";
        foreach ($this->lists as $list) {
            echo "\tList: ".$list->getName()."\n";
            foreach ($list->getCards() as $card) {
                echo "\t\tCard: ".$card->getTitle()."\n";

                foreach ($card->getChecklists() as $checklist) {
                    echo "\n\t\t\tChecklist ".$checklist->getTitle().":\n";
                    foreach ($checklist->getItems() as $i => $item) {
                        echo "\t\t\t\t$i. ".$item->getTitle()." - ".$item->getFinishedString()."\n";
                    }
                }

                echo "\n\t\t\tTags:\n";
                foreach ($card->getTags() as $i => $tag) {
                    echo "\t\t\t\t$i. ".$tag->getName()."\n";
                }

                if (count($card->getComments()) > 0) {
                    echo "\n\t\t\tComments:\n";
                    foreach ($card->getComments() as $comment) {
                        echo "\t\t\t\t".$comment->getComment()."\n";
                    }
                }
            }
        }
    }
}
