<?php

namespace Kjgcoop\WekanFromZk;

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

    // This variable name is related to the default value of $defaultStageTitle,
    // which is Stage. I'm not about to go around renaming it, that's the
    // etymology, for the curious.
    protected $stage_uuid = '';

    protected $lists = [];

    // Tags will be defined as cards a created.
    protected $tags = [];

    // To-Do: move to config file -- in use both here and in Wekan <-- what?
    protected $tagBatchName = '_categories_sort';

    // Because ZK supports many batches of tags, we have to guess at which one
    // we should treat as a column. Stage is ZK's default tag/column name, so
    // relying on it here will work for most boards; this is as close to an
    // educated guess as I've come to ascertaining which tag batch Wekan should
    // treat as columns.
    //    If your data doesn't conform to this expectation, I feel bad for you,
    // son, I got 99 problems but a defaultStageTitle ain't one.
    protected $defaultStageTitle = 'Stage';

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

            if (isset($result->error) && $result->error != '') {
                die("No result from request to get boards\n");
            }

            $this->w_id      = $result->_id;
            $this->w_swim_id = $result->defaultSwimlaneId;

            echo "Newly-created board ID: ".$this->w_id."\n";

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

        // Comments are in $this->fromZk->activities, so they're not members of
        // cards. Each activity has a type, the ones we're interested are
        // commentCard. Within that, it's got originData, and within that it has
        // data. Within that, it itemizes the board, list and card the comment
        // goes to. There are IDs, but I haven't been keeping tabs on them. If
        // you have two cards in the same list with the same name, you will have
        // big sad.
        foreach ($this->fromZk->activities as $activity) {
            // This is god awful inefficent
            foreach ($this->lists as $list) {
                $card = $list->searchCardsByListEntryId($activity->listEntryId);
                print_r("Comment with listEntryID ".$activity->listEntryId." and message: '".$activity->message."'\n");

                if ($card !== false) {
                    $comment = new WekanComment($activity->message, $this->host, $this->token);
                    $card->addComment($comment);
                    print_r("Comment on '".$card->getTitle()."': ".$activity->message."\n");
                } else {
                    print_r("Uhoh! Found no card for comment with listEntryId ".$activity->listEntryId." and message: '".$activity->message."' <----- Requires manual intervention\n");
                }
            }

            if (isset($activity->originData->type) && $activity->originData->type === 'commentCard') {
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
            } else {
                echo "\tFound an activity but it has no originData\n";
            }
        }
    }

    function parseOutListNames($elementData) {
        if (isset($elementData->predefinedCategories)) {
            foreach ($elementData->predefinedCategories as $i => $cat) {
                echo "$i. Found a list, ".$cat->name." in board ".$this->w_id."\n";

                // Only send the name because we don't care about anything else.
                $this->lists[$cat->name] = new WekanList($cat->name, $this->host, $this->token, $this->tags);
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

            if ($element->name == $this->defaultStageTitle) { // These are the columns
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
        // If an unexpected tag batch has replaced the default column name,
        // this will go wonky.
        $list_key = $this->stage_uuid.$this->tagBatchName;

        if (!isset($entry->$list_key)) {
            die("Your data is weird. Go away.\n");
        }

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
            print_r("Key $key has title ".$entry->displayString."\n");

            // List name tells us what list it's in
            $list_name = $this->getListThisCardIsIn($entry);
            print_r("List this card is in: ".$list_name."\n");

            // Remove the tag group associated with the list heads so as not to
            // duplicate them with the other tags. Must be done after getting
            // the name because we use this property to ascertain the name.
            $list_key = $this->stage_uuid.$this->tagBatchName;
            unset($entry->$list_key);

            // Now that we know the list and we've removed duplicate information
            // the WekanList class can take over. Note that this will cause
            // problems if you have two lists of the same name. I don't think
            // ZK allows that, but if it does, uhoh. Potentially.
            if (isset($list_name) && isset($this->lists) && isset($this->lists[$list_name])) {
                $card = $this->lists[$list_name]->addCardFromEntry($entry, $key);

                // Tags are created as cards find them, so for this board's list
                // of tags to be correct, it needs the card to give it back the
                // full list.
                $this->tags = $card->getUpdatedBoardTags();
            } else {
                echo "There's something wonky about a list: $list_name - I'm guessing there may not be a tag batch called '".$this->defaultStageTitle."' - this script assumes that there is one to disambiguate which batch of columns it groups by. If ye brave or foolish enough, alter the code in ".__FILE__." to set defaultStageTitle to whatever the name of your tag batch is.\n";
                die();
            }

        }
        echo "\n";
    }
}
