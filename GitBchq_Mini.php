#!/usr/bin/env php

<?php

class GitBchq_Mini {

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
    private function put() {

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

    /**
     * Get last commit message, replace ANSI escape sequences with Textile markup
     *
     * @return string Textile-ready commit log
     */
    public function getLastCommit() {
        $logMessage = trim(`git log -1 --date=rfc -M -C --stat --pretty=medium --color`);
        $ansiTextile = array(
            chr(27) . "[33m" => '%{color:orange}',
            chr(27) . "[m" => '%',
            chr(27) . "[32m" => '%{color:green}',
            chr(27) . "[31m" => '%{color:red}'
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
     * @TODO Parse response to get attachment id only?
     * @return string Response XML
     */
    public function uploadPatch() {
        $patchDiff = $this->getPatch();

        curl_setopt_array($this->curl(), array(
            CURLOPT_HTTPHEADER => array(
                'Content-type: application/octet-stream',
                'Content-length: ' . strlen($patchDiff)
            ),
        ));

        $response = $this->post('upload', $patchDiff);
        curl_close($this->curl());
        
        return $response;
    }
}

/**
 * Prompt user for input into stdin
 *
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

/* Work */
$messageList = $GitBchq->getMessages();
echo 'Select a message to update [1-', sizeof($messageList), ']:', PHP_EOL;
$i = 0;
foreach($messageList as $messageId => $messageTitle) {
    ++$i;
    echo "* {$i}. [#{$messageId}] {$messageTitle}", PHP_EOL;
}
$messagePick = promptUser();
echo PHP_EOL;

if(promptUser("Upload a patch? y/[n]: ") == "y") {
    echo "* Uploading. . .";
    //$GitBchq->uploadPatch();
}
