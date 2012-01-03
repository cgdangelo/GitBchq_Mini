#!/usr/bin/env php

<?php

class GitBchq_Mini {

    protected $_apiKey;
    protected $_baseUrl;
    protected $_projectId;
    private $_curl;

    public function __construct($apiKey, $baseUrl, $projectId) {
        $this->_apiKey = $apiKey;
        $this->_baseUrl = $baseUrl;
        $this->_projectId = $projectId;
    }

    public function buildRoute(Array $urlArgs) {
        $request = '';
        foreach($urlArgs as $arg => $value) {
            $request .= "$arg/$value/";
        }

        return rtrim($request, '/');
    }

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
                CURLOPT_VERBOSE => true
            ));
        }

        if(is_array($route)) {
            $route = $this->buildRoute($route);
        }

        curl_setopt($this->_curl, CURLOPT_URL, $this->_baseUrl . $route);

        return $this->_curl;
    }

    public function get($route = null) {
        return curl_exec($this->curl($route));
    }

    public function post($route = null, $formData) {
        curl_setopt_array($this->curl(), array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $formData
        ));
        
        return curl_exec($this->curl($route));
    }

    private function put() {

    }

    private function delete() {

    }

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


    public function getPatch() {
        $diff = trim(`git diff -p -1`);

        return $diff;
    }

    public function uploadPatch() {
        $patchDiff = $this->getPatch();

        curl_setopt_array($this->curl(), array(
            CURLOPT_HTTPHEADER => array(
                'Content-type: application/octet-stream',
                'Content-length: ' . strlen($patchDiff)
            ),
            CURLOPT_VERBOSE => true
        ));

        $response = $this->post('upload', $patchDiff);
        curl_close($this->curl());
        
        return $response;
    }
}

$BCHQ_APIKEY=trim(`git config --get basecamp.apikey`);
$BCHQ_BASE_URL=trim(`git config --get basecamp.baseurl`);
$BCHQ_PROJECT_ID=trim(`git config --get basecamp.projectid`);
$GitBchq = new GitBchq_Mini($BCHQ_APIKEY, $BCHQ_BASE_URL, $BCHQ_PROJECT_ID);
