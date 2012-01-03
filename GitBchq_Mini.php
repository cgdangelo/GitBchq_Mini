#!/usr/bin/env php

<?php

class GitBchq_Mini {

    protected $_apiKey;
    protected $_baseUrl;
    protected $_projectId;

    public function __construct($apiKey, $baseUrl, $projectId) {
        $this->_apiKey = $apiKey;
        $this->_baseUrl = $baseUrl;
        $this->_projectId = $projectId;
    }

    public function buildRoute(Array $urlArgs) {
        $request = '';
        foreach($urlArgs as $arg => $value) {
            $request .= "/$arg/$value";
        }

        return $request;
    }

    private function curl($route = '') {
        $curl = curl_init($this->_baseUrl . $route);
        curl_setopt_array($curl, array(
            CURLOPT_HTTPHEADER => array(
                'Accept: application/xml',
                'Content-type: application/xml'
            ),
            
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERPWD => $this->_apiKey . ":"
        ));

        return curl_exec($curl);
    }

    public function getMessages() {
        $route = $this->buildRoute(array(
            'projects' => $this->_projectId,
            'posts' => ''
        ));

        $messagesXml = new SimpleXMLElement($this->curl($route));
        $messagesList = array();
        foreach($messagesXml as $message) {
            $messagesList["$message->id"] = "$message->title";
        }

        return $messagesList;
    }
}

$BCHQ_APIKEY=trim(`git config --get basecamp.apikey`);
$BCHQ_BASE_URL=trim(`git config --get basecamp.baseurl`);
$BCHQ_PROJECT_ID=trim(`git config --get basecamp.projectid`);
$GitBchq = new GitBchq_Mini($BCHQ_APIKEY, $BCHQ_BASE_URL, $BCHQ_PROJECT_ID);
