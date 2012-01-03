#!/usr/bin/env php

<?php

class GitBchq_Mini {
    
    const BCHQ_RESOURCE_TODO = 'todo_items';
    const BCHQ_RESOURCE_POST = 'posts';
    const BCHQ_RESOURCE_SKIP = 'skip';

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
     * Constructor
     *
     * @param string $apiKey BaseCamp user API key
     * @param string $baseUrl BaseCamp URL
     * @param int $projectId BaseCamp project id
     */
    public function __construct($apiKey, $baseUrl, $projectId) {
        $this->_apiKey = $apiKey;
        $this->_baseUrl = $baseUrl;
        $this->_projectId = $projectId;
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
     * HTTP PUT on route
     *
     * @return string Response text
     */
    private function put($route = null, $file = null) {
        curl_setopt_array($this->curl(), array(
            CURLOPT_PUT => true
        ));

        return curl_exec($this->curl($route));
    }

    /**
     * HTTP DELETE on route
     *
     * @return string Response text
     */
    private function delete() {

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

    public function postComment($resourceType, $resourceId, $commentBody, $attachmentId = null) {
        $route = $this->buildRoute(array(
            $resourceType => $resourceId,
            'comments.xml' => ''
        ));

        $commentXml = new SimpleXMLElement('<comment></comment>');
        $commentXml->addChild('body', $commentBody);
        if($attachmentId) {
            $commentXml->addChild('attachments');
            $commentXml->attachments->addChild('file');
            $commentXml->attachments->file->addChild('file', $attachmentId);
            $commentXml->attachments->file->addChild('content-type', 'text/plain');
            $commentXml->attachments->file->addChild('original-filename', '');
        }

        $commentXml = $commentXml->asXML();

        curl_setopt_array($this->curl(), array(
            CURLOPT_HTTPHEADER => array(
                'Content-type: application/octet-stream',
                'Content-length: ' . strlen($commentXml)
            ),
        ));

        $responseXml = $this->post($route, $commentXml);
        $responseCode = curl_getinfo($this->curl(), CURLINFO_HTTP_CODE);
        curl_close($this->curl());
        
        if($responseCode != 201) {
            return null;
        }

        $parser = new SimpleXMLElement($responseXml);
        return $parser->id;
    }

    public function getMessageComments($messageId) {
        $route = $this->buildRoute(array(
            'posts' => $messageId,
            'comments.xml' => ''
        ));

        return curl_exec($this->curl($route));
    }

    /**
     * Get last commit message, replace ANSI escape sequences with Textile markup
     *
     * @return string Textile-ready commit log
     */
    public function getLastCommit() {
        $logMessage = trim(`git log -1 --date=rfc -M -C --stat --pretty=medium --color`);
        $ansiTextile = array(
            chr(27) . '[34m' => '%{color:orange}',
            chr(27) . '[33m' => '%{color:orange}',
            chr(27) . '[m' => '%',
            chr(27) . '[32m' => '%{color:green}',
            chr(27) . '[31m' => '%{color:red}'
        );

        $logMessage = str_replace(array_keys($ansiTextile), $ansiTextile, $logMessage);
            
        return $logMessage;
    }

    /**
     * Get patch from git diff between HEAD and HEAD^
     *
     * @return string Patch
     */
    public function getPatch() {
        $diff = trim(`git diff -p -1`);

        return $diff;
    }

    /**
     * Upload patch
     *
     * @return string File id 
     */
    public function uploadPatch() {
        $patchDiff = $this->getPatch();

        curl_setopt_array($this->curl(), array(
            CURLOPT_HTTPHEADER => array(
                'Content-type: application/octet-stream',
                'Content-length: ' . strlen($patchDiff)
            ),
        ));

        $responseXml = $this->post('upload', $patchDiff);
        $responseCode = curl_getinfo($this->curl(), CURLINFO_HTTP_CODE);
        curl_close($this->curl());
        
        if($responseCode != 201) {
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
     * @return null|string Response text
     */
    public function completeTodoListItem($todoListItemId) {
        $route = $this->buildRoute(array(
            'todo_items' => $todoListItemId
        ));

        $response = $this->put($route);
        $respopnseCode = curl_getinfo($this->curl(), CURLINFO_HTTP_CODE);

        if($responseCode != 200) {
            return null;
        }

        return $response;
    }
}

/**
 * Prompt user for input into stdin
 *
 * @param string $prompt Optional single-line prompt
 * @return string
 */
function promptUser($prompt = '') {
    echo $prompt;
    return strtolower(trim(fgets(STDIN)));
}

/* Initialization */
$BCHQ_APIKEY=trim(`git config --get basecamp.apikey`);
$BCHQ_BASE_URL=trim(`git config --get basecamp.baseurl`);
$BCHQ_PROJECT_ID=trim(`git config --get basecamp.projectid`);
$GitBchq = new GitBchq_Mini($BCHQ_APIKEY, $BCHQ_BASE_URL, $BCHQ_PROJECT_ID);

$resourceType = null;
$resourceId = null;

echo 'Select a resource type: ', PHP_EOL;
echo '* 0. <None>', PHP_EOL;
echo '* 1. Todo Lists', PHP_EOL;
echo '* 2. Messages', PHP_EOL;
while(!$resourceType) {
    switch($resourceType = promptUser()) {
        case 0:
            $resourceType = 'skip';
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

while(!$resourceId) {
    switch($resourceType) {
        case GitBchq_Mini::BCHQ_RESOURCE_TODO:
            $todoListList = $GitBchq->getTodoLists();
            echo 'Select a todo list to update [0-', sizeof($todoListList), ']:', PHP_EOL;
            $i = 0;
            echo '* 0. <None>', PHP_EOL;
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

            $todoListItemsList = $GitBchq->getTodoListItems($todoListId);
            echo 'Select a todo list item to update [0-', sizeof($todoListItemsList), ']:', PHP_EOL;
            $i = 0;
            echo '* 0. <None>', PHP_EOL;
            foreach($todoListItemsList as $todoListItemId => $todoListItemData) {
                ++$i;
                $dueDate = new DateTime((string)$todoListItemData['due']);
                echo PHP_EOL, "* {$i}. [#{$todoListItemId}] ", $dueDate->diff(new DateTime())->format('+%a days, %H hours'), PHP_EOL;
                echo "- {$todoListItemData['content']}", PHP_EOL;
            }
            echo PHP_EOL;
            $todoListItemPickIndex = promptUser() - 1;
            $todoListItemPickId = array_keys($todoListItemsList);
            $resourceType = 'todo_items';
            $resourceId = $todoListItemPickId[(int)$todoListItemPickIndex];
            break;

        case GitBchq_Mini::BCHQ_RESOURCE_POST:
            $messageList = $GitBchq->getMessages();
            echo 'Select a message to update [0-', sizeof($messageList), ']:', PHP_EOL;
            $i = 0;
            echo '* 0. <None>', PHP_EOL;
            foreach($messageList as $messageId => $messageTitle) {
                ++$i;
                echo "* {$i}. [#{$messageId}] {$messageTitle}", PHP_EOL;
            }
            $messagePickIndex = promptUser() - 1;
            $messagePickId = array_keys($messageList);
            $resourceType = 'posts';
            $resourceId = $messagePickId[(int)$messagePickIndex];
            break;

        case GitBchq_Mini::BCHQ_RESOURCE_SKIP:
            $resourceId = 0;
            break;

        default:
            $resourceId = null;
    }
}

if($resourceId != 0) {
    $fileId = null;
    if(promptUser("Upload a patch? y/[n]: ") == "y") {
        echo "* Uploading. . .";
        if($fileId = $GitBchq->uploadPatch()) {
            echo "* Uploaded patch: {$fileId}", PHP_EOL;
        } else {
            echo "* Failed to upload patch!";
        }
    }

    if($commentId = $GitBchq->postComment($resourceType, $resourceId, $GitBchq->getLastCommit(), $fileId)) {
        echo "* Added comment: {$commentId}", PHP_EOL;
    } else {
        echo "* Failed to post comment!";
    }
}