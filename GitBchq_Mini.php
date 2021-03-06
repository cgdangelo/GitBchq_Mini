#!/usr/bin/env php

<?php

class GitBchq_Mini {

    const BCHQ_RESOURCE_TODO = 'todo_items';
    const BCHQ_RESOURCE_POST = 'posts';

    /**
     * BaseCamp user API key
     *
     * @var string $_apiKey
     */
    protected $_apiKey;

    /**
     * BaseCamp URL
     *
     * @var string $_baseUrl
     */
    protected $_baseUrl;

    /**
     * BaseCamp project id
     *
     * @var int $_projectId
     */
    protected $_projectId;

    /**
     * cURL resource
     *
     * @var resource $_curl
     */
    private $_curl;

    /**
     * SHA1 for HEAD
     *
     * @var string $_head
     */
    protected $_head;

    /**
     * Constructor
     *
     * @param string $apiKey BaseCamp user API key
     * @param string $baseUrl BaseCamp URL
     * @param int $projectId BaseCamp project id
     */
    public function __construct($apiKey, $baseUrl, $projectId, $_head = 'diff') {
        $this->_apiKey = $apiKey;
        $this->_baseUrl = $baseUrl;
        $this->_projectId = $projectId;
        $this->_head = $_head;
    }

    /**
     * Build route from array of actions and arguments
     *
     * @param array $urlArgs Actions (keys) and arguments (values)
     * @return string action/arg/action/arg
     */
    public function buildRoute(Array $urlArgs) {
        $request = '';
        foreach($urlArgs as $arg => $value) {
            $request .= "$arg/$value/";
        }

        return rtrim($request, '/');
    }

    /**
     * Make sure we have a cURL resource, defaults to GET requests
     *
     * @return resource cURL
     */
    private function curl($route = null) {
        if(!isset($this->_curl) || get_resource_type($this->_curl) != 'curl') {
            $this->_curl = curl_init();

            curl_setopt_array($this->_curl, array(
                CURLOPT_HTTPHEADER => array(
                    'Accept: application/xml',
                    'Content-type: application/xml'
                ),

                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERPWD => $this->_apiKey . ":",
            ));
        }

        if(is_array($route)) {
            $route = $this->buildRoute($route);
        }

        curl_setopt($this->_curl, CURLOPT_URL, $this->_baseUrl . $route);

        return $this->_curl;
    }

    /**
     * HTTP GET on route
     *
     * @param string Route relative to base URL
     * @return string Response text
     */
    private function get($route = null) {
        return curl_exec($this->curl($route));
    }

    /**
     * HTTP POST on route
     *
     * @param string Route relative to base URL
     * @param array|string Form data or request body
     * @return string Response text
     */
    private function post($route = null, $formData) {
        curl_setopt_array($this->curl(), array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $formData
        ));

        return curl_exec($this->curl($route));
    }

    /**
     * HTTP PUT on route, which is apparently a fake HTTP POST. Thanks, libcurl.
     *
     * @param string $route Route relative to base URL
     * @return string Response text
     */
    private function put($route = null) {
        curl_setopt_array($this->curl(), array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_CUSTOMREQUEST => 'PUT',
        ));

        return curl_exec($this->curl($route));;
    }

    /**
     * Get list of messages
     *
     * @return array Messages
     */
    public function getMessages() {
        $route = $this->buildRoute(array(
            'projects' => $this->_projectId,
            'posts' => ''
        ));

        $messagesXml = new SimpleXMLElement($this->get($route));
        $messagesList = array();
        foreach($messagesXml as $message) {
            $messagesList["$message->id"] = "$message->title";
        }

        return $messagesList;
    }

    /**
     * Post comment on commentable resource
     *
     * @param string $resourceType Type of resource (e.g. todo_items, posts)
     * @param int $resourceId Resource id number
     * @param string $commentBody Unformatted comment body
     * @param string $attachmentId File to attach (if any)
     * @return bool True on success (HTTP 201 response)
     */
    public function postComment($resourceType, $resourceId, $commentBody, $attachmentId = null) {
        $route = $this->buildRoute(array(
            $resourceType => $resourceId,
            'comments.xml' => ''
        ));

        $commentXml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><comment></comment>');
        $commentXml->addChild('body', $commentBody);
        $commentXml->addChild('use-textile', '1')->addAttribute('type', 'boolean');
        if($attachmentId) {
            $commentXml->addChild('attachments');
            $commentXml->attachments->addChild('file');
            $commentXml->attachments->file->addChild('file', $attachmentId);
            $commentXml->attachments->file->addChild('content-type', 'text/plain');
            $commentXml->attachments->file->addChild('original-filename', "{$this->_head}.patch");
        }

        $commentXml = $commentXml->asXML();

        curl_setopt_array($this->curl(), array(
            CURLOPT_HTTPHEADER => array(
                'Content-type: application/xml',
                'Content-length: ' . strlen($commentXml)
            ),
        ));

        $response = $this->post($route, $commentXml);
        $responseCode = curl_getinfo($this->curl(), CURLINFO_HTTP_CODE);
        curl_close($this->curl());

        return ($responseCode == 201);
    }

    /**
     * Upload patch
     *
     * @param string $patchDiff Patch from git-diff
     * @return string File id
     */
    public function uploadPatch($patchDiff) {
        curl_setopt_array($this->curl(), array(
            CURLOPT_HTTPHEADER => array(
                'Content-type: application/octet-stream',
                'Content-length: ' . strlen($patchDiff)
            ),
        ));

        $responseXml = $this->post('upload', $patchDiff);
        $responseCode = curl_getinfo($this->curl(), CURLINFO_HTTP_CODE);
        curl_close($this->curl());

        if($responseCode != 200) {
            return null;
        }

        $parser = new SimpleXMLElement($responseXml);
        return $parser->id;
    }

    /**
     * Get all todo lists for project.
     *
     * @return array Todo list information
     */
    public function getTodoLists() {
        $route = $this->buildRoute(array(
            'projects' => $this->_projectId,
            'todo_lists.xml' => ''
        ));

        $todoListXml = new SimpleXMLElement($this->get($route));
        $todoListXml = $todoListXml->xpath('todo-list');
        $todoListList = array();
        foreach($todoListXml as $todoList) {
            $todoListList["$todoList->id"] = array(
                'name' => "$todoList->name",
                'description' => "$todoList->description"
            );
        }

        return $todoListList;
    }

    /**
     * Get all todo items on a given todo list
     *
     * @param $todoListId Todo list id
     * @return array Todo items information
     */
    public function getTodoListItems($todoListId) {
        $route = $this->buildRoute(array(
            'todo_lists' => $todoListId,
            'todo_items.xml' => ''
        ));

        $todoListItemsXml = new SimpleXMLElement($this->get($route));
        $todoListItemsXml = $todoListItemsXml->xpath('todo-item');
        $todoListItemsList = array();

        foreach($todoListItemsXml as $todoListItem) {
            $todoListItemsList["$todoListItem->id"] = array(
                'content' => "$todoListItem->content",
                'due' => $todoListItem->{'due-at'}
            );
        }

        return $todoListItemsList;
    }

    /**
     * Mark a todo item as completed
     *
     * @param $todoListItemId Todo item id
     * @return int Response code
     */
    public function completeTodoListItem($todoListItemId) {
        $route = $this->buildRoute(array(
            'todo_items' => $todoListItemId,
            'complete.xml' => ''
        ));

        $response = $this->put($route);
        $responseCode = curl_getinfo($this->curl(), CURLINFO_HTTP_CODE);

        return $responseCode;
    }
}

/**
 * Prompt user for input into stdin
 *
 * @param string $prompt Optional single-line prompt
 * @return string
 */
function promptUser($prompt = '', $multiLine = false) {
    echo $prompt;

    $inputData = '';
    $inputLine = '';
    while(!feof(STDIN)) {
        $inputLine = trim(fgets(STDIN));

        if(!$multiLine) {
            return $inputLine;
        } else if($inputLine == '%EOF%') {
            return $inputData; 
        } else {
            $inputData .= $inputLine . PHP_EOL;
        }
    }
}

/* Initialization */
$BCHQ_APIKEY=trim(`git config --get basecamp.apikey`);
$BCHQ_BASE_URL=trim(`git config --get basecamp.baseurl`);
$BCHQ_PROJECT_ID=trim(`git config --get basecamp.projectid`);
$BCHQ_SHA1_HEAD=str_replace(PHP_EOL, '-', trim(`git log --format=%h -2`));
$GitBchq = new GitBchq_Mini($BCHQ_APIKEY, $BCHQ_BASE_URL, $BCHQ_PROJECT_ID, $BCHQ_SHA1_HEAD);

$resourceType = null;
$resourceId = null;

echo 'Select a resource type: ', PHP_EOL;
echo '* 1. Todo Lists', PHP_EOL;
echo '* 2. Messages', PHP_EOL;

/* Picking a resource type */
while(!$resourceType) {
    switch($resourceType = promptUser()) {
        case 1:
            $resourceType = 'todo_items';
            break;
        case 2:
            $resourceType = 'posts';
            break;
        default:
            $resourceType = null;
    }
}
echo PHP_EOL;

/* Determining our resource id */
while(!$resourceId) {

    switch($resourceType) {

        /* Todo lists */
        case GitBchq_Mini::BCHQ_RESOURCE_TODO:
            /* @TODO Add code for creating todo lists and items? */
            if(sizeof($todoListList = $GitBchq->getTodoLists()) == 0) {
                echo '* No todo lists for this project.', PHP_EOL;
                exit();
            }

            /* Selecting a todo list */
            echo 'Select a todo list to update [1-', sizeof($todoListList), ']:', PHP_EOL;
            $i = 0;
            foreach($todoListList as $todoListId => $todoListData) {
                ++$i;
                echo PHP_EOL, "* {$i}. [#{$todoListId}] {$todoListData['name']}", PHP_EOL;
                echo "- {$todoListData['description']}", PHP_EOL;
            }
            echo PHP_EOL;

            $todoListPickIndex = null;
            while($todoListPickIndex === null) {
                $todoListPickIndex = promptUser() - 1;
                if($todoListPickIndex < 0 || $todoListPickIndex > sizeof($todoListList)) {
                    $todoListPickIndex = null;
                }
            }
            $todoListPickId = array_keys($todoListList);
            $todoListId = $todoListPickId[(int)$todoListPickIndex];
            echo PHP_EOL;

            /* Selecting a todo list item */
            if(sizeof($todoListItemsList = $GitBchq->getTodoListItems($todoListId)) == 0) {
                echo '* No todo items on this list.', PHP_EOL;
                exit();
            }

            echo 'Select a todo list item to update [1-', sizeof($todoListItemsList), ']:', PHP_EOL;
            $i = 0;
            foreach($todoListItemsList as $todoListItemId => $todoListItemData) {
                ++$i;
                $dueDate = new DateTime((string)$todoListItemData['due']);
                echo PHP_EOL, "* {$i}. [#{$todoListItemId}] ", $dueDate->diff(new DateTime())->format('+%a days, %H hours'), PHP_EOL;
                echo "- {$todoListItemData['content']}", PHP_EOL;
            }
            echo PHP_EOL;

            $todoListItemPickIndex = null;
            while($todoListItemPickIndex === null) {
                $todoListItemPickIndex = promptUser() - 1;
                if($todoListItemPickIndex < 0 || $todoListItemPickIndex > sizeof($todoListItemsList)) {
                    $todoListItemPickIndex = null;
                }
            }

            $todoListItemPickId = array_keys($todoListItemsList);
            $resourceType = 'todo_items';
            $resourceId = $todoListItemPickId[(int)$todoListItemPickIndex];
            break;

        /* Messages (a.k.a posts) */
        case GitBchq_Mini::BCHQ_RESOURCE_POST:
            $messageList = $GitBchq->getMessages();
            echo 'Select a message to update [1-', sizeof($messageList), ']:', PHP_EOL;
            $i = 0;

            foreach($messageList as $messageId => $messageTitle) {
                ++$i;
                echo "* {$i}. [#{$messageId}] {$messageTitle}", PHP_EOL;
            }
          
            $messagePickIndex = null;
            while($messagePickIndex === null) {
                $messagePickIndex = promptUser() - 1;
                if($messagePickIndex < 0 || $messagePickIndex > sizeof($messageList)) {
                    $messagePickIndex = null;
                }
            }

            $messagePickId = array_keys($messageList);
            $resourceType = 'posts';
            $resourceId = $messagePickId[(int)$messagePickIndex];
            break;

        default:
            $resourceId = null;
    }
}

if($resourceId) {
    $fileId = null;
    if(strtolower(promptUser(PHP_EOL . "Upload a patch? y/[n]: ")) == "y") {
        $patchDiff = trim(`git diff -M -C -p HEAD~1`);

        echo "* Uploading. . .", PHP_EOL;
        if($fileId = $GitBchq->uploadPatch($patchDiff)) {
            echo "* Uploaded patch: {$fileId}", PHP_EOL;
        } else {
            echo "* Failed to upload patch!", PHP_EOL;
            exit();
        }
    }

    $commentBody = "<hr /><pre>" . trim(`git log -1 --date=rfc -M -C --stat --pretty=medium`) . "</pre>";
    if($otherText = promptUser(PHP_EOL . "Any additional notes or messages to post: " . PHP_EOL, true)) {
        $commentBody = $otherText . PHP_EOL . $commentBody;
    }
    $commentBody .= PHP_EOL . PHP_EOL . "This report was automatically generated by <a href='https://github.com/cgdangelo/GitBchq_Mini/'>*GitBchq_Mini*</a>.";

    echo PHP_EOL, "<<<<< COMMENT START", PHP_EOL;
    echo $commentBody;
    echo PHP_EOL, "COMMENT END >>>>>", PHP_EOL;

    if('n' != strtolower(promptUser(PHP_EOL . 'Post the above comment? [y]/n: '))) {
        if($GitBchq->postComment($resourceType, $resourceId, $commentBody, $fileId) == 1) {
            echo "* Added comment", PHP_EOL;
        } else {
            echo "* Failed to post comment!", PHP_EOL;
            exit();
        }

        if($resourceType == GitBchq_Mini::BCHQ_RESOURCE_TODO) {
            if('y' == strtolower(promptUser(PHP_EOL .'Mark this item as complete? y/[n]: '))) {
                $responseCode = $GitBchq->completeTodoListItem($resourceId);

                if($responseCode == 200) {
                    echo "* Marked todo item #{$resourceId} as completed.";
                } else {
                    echo "* Failed to mark todo item as completed!";
                }
                echo PHP_EOL;
            }
        }
    } else {
        echo "* Aborting. . .", PHP_EOL;
    }
}
