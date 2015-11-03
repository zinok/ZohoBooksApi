<?php

/**
 * PHP Interface for Zoho Books API 
 *
 * CHANGELOG v1.0:
 * - Supports dynamic calls to ZohoBooks entities
 * - Actions supported - List, Get, Create, Update, Delete
 * - Possibiltiy to add non-typical methods
 * - File-download supported if custom method has raw=false attribute
 *
 * @version 1.0
 * @author Andrii Zinchenko <mail@zinok.org>
 */
class ZohoBooksApi 
{
    /**
     * Zoho Books API Auth Token 
     */
    public $authToken;

    /** 
     * Zoho Books Organization ID
     */
    public $organizationId;

    /**
     * Result of the last query
     */
    public $lastRequest = array(
        'httpCode' => null,
        'dataRaw' => null,
        'data' => null,
        'zohoCode' => null,
        'zohoMessage' => null,
        'zohoPaging' => null,
        'zohoResponse' => null
    );

    /**
     * Base URL for Zoho Books API
     */
    private $baseURL = 'https://books.zoho.com/api/v3/';

    /**
     * Limit of requests per minute, after reaching the limit API 
     * will sleep before making next request
     * 0 - disabled
     */
    private $apiRequestsLimit = 0;

    /**
     * Number of API requests during period (to check limit)
     */
    private $apiRequestsCount = 0;

    /**
     * Timestamp from which we check API limit
     */
    private $apiRequestsTs = 0;

    /**
     * Timeout for HTTP requests
     */
    private $timeout = 30;

    /**
     * Full list of available methods
     * Specific methods may be listed here, general methods will be auto-populated
     * The format for specification is like:
     *    'ContactsList' => array('url' => 'contacts', 'method' => 'GET')
     */
    private $methods = array();

    /**
     * Map of actions to HTTP methods
     */
    private $smartActions = array(
        'List' => 'GET',
        'ListAll' => 'GET',
        'Get' => 'GET',
        'Create' => 'POST',
        'Update' => 'PUT',
        'Delete' => 'DELETE'
    );

    /**
     * List of available objects 
     */
    private $smartObjects = array(
        'Contacts', 'Estimates', 'SalesOrders', 'Invoices', 'RecurringInvoices', 'CreditNotes', 'CustomerPayments',
        'Expenses', 'RecurringExpenses', 'PurchaseOrders', 'Bills', 'VendorCredits', 'VendorPayments', 'BankAccounts',
        'BankTransactions', 'ChartOfAccounts', 'Journals', 'Projects'
    );

    /**
     * Init Interface
     *
     * @param string $authToken Auth Token generated for the API
     * @param integer $organizationId Zoho Books Organization ID
     */
    public function __construct($authToken, $organizationId, $apiRequestsLimit = 150)
    {
        // save auth info
        $this->authToken = $authToken;
        $this->organizationId = $organizationId;

        // init counters to check for limits
        $this->apiRequestsLimit = $apiRequestsLimit;

        // prepare methods
        $this->prepareMethods();
    }

    /**
     * Populates $this->methods on base of $smartActions / $smartObjects
     */
    protected function prepareMethods()
    {
        // iterate over all objects
        foreach($this->smartObjects as $object) {
            // iterate over all actions
            foreach($this->smartActions as $action => $httpMethod) {
                // build URL
                $url = strtolower($object);
                if (in_array($action, array('Get', 'Update', 'Delete'))) {
                    $url .= '/%s';
                }

                // append method
                $this->methods[$object.$action] = array('url' => $url, 'method' => $httpMethod);
            }
        }
    }

    /**
     * Check API Requests limit, if we reached it - sleep 
     */
    protected function checkApiLimit()
    {
        // if check is disabled - just exit
        if ($this->apiRequestsLimit <= 0) {
            return;
        }

        // check time of last request
        $_delta = time() - $this->apiRequestsTs;

        // if more then 60 seconds passed - re-init
        if ($_delta > 60) {
            $this->apiRequestsTs = time();
            $this->apiRequestsCount = 1;
            return;
        }

        // if we reached limit - sleep
        if ($this->apiRequestsCount >= $this->apiRequestsLimit) {
            sleep(60 - $_delta);
        }

        // and increase counter
        $this->apiRequestsCount++;
    }

    /**
     * Make actual API request using cURL
     *
     * @param string $url URL to send request to (relative, without domain)
     * @param string $method HTTP method to use (GET, POST, DELETE, etc)
     * @param array $query Array of parameters to send
     * @param boolean $raw If true - do not do JSON decode (default = false)
     */
    public function makeApiRequest($url, $method = 'GET', $query = array(), $raw = false)
    {
        // reset lastRequest
        $this->lastRequest = array(
            'httpCode' => null,
            'dataRaw' => null,
            'data' => null,
            'zohoCode' => null,
            'zohoMessage' => null,
            'zohoPaging' => null,
            'zohoResponse' => null
        );

        // first check API requests limit
        $this->checkApiLimit();

        // validate method
        $method = strtoupper($method);
        if (!in_array($method, array('GET', 'POST', 'PUT', 'DELETE'))) {
            throw new ZohoBooksApiException('incorrect method requested - "'.$method.'"', 1);
        }

        // init cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Expect:'));

        // add auth info to URL
        $auth = array(
            'authtoken' => $this->authToken,
            'organization_id' => $this->organizationId
        );
        $fullURL = $this->baseURL.$url.'?'.http_build_query($auth);

        // build parameters
        if ($method == 'GET') {
            $fullURL .= $query ? '&'.http_build_query($query) : '';
        } else {
            $Q = array('JSONString' => json_encode($query));
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($Q));
        }

        // set URL
        curl_setopt($ch, CURLOPT_URL, $fullURL);

        // execute request
        $this->lastRequest['dataRaw'] = curl_exec($ch);

        // check for timeout
        if ($this->lastRequest['dataRaw'] === false) {
            throw new ZohoBooksApiException('request timed out', 11);
        }

        // get httpCode
        $this->lastRequest['httpCode'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // close curl
        curl_close($ch);

        // check http code
        if (substr($this->lastRequest['httpCode'], 0, 1) != '2') {
            throw new ZohoBooksApiException('HTTP code "'.$this->lastRequest['httpCode'].'" - waiting for 2XX', 12);
        }

        // if raw is true - stop here, do not do further decoding
        if ($raw) {
            return $this->lastRequest;
        }

        // parse json
        $this->lastRequest['data'] = @json_decode($this->lastRequest['dataRaw'], true);
        if (is_null($this->lastRequest['data'])) {
            throw new ZohoBooksApiException("can not decode JSON from response", 21);
        }

        // check Zoho Books response code
        $this->lastRequest['zohoCode'] = $this->lastRequest['data']['code'];
        $this->lastRequest['zohoMessage'] = $this->lastRequest['data']['message'];

        // verify zoho code
        if ($this->lastRequest['zohoCode'] != 0) {
            throw new ZohoBooksApiRequestException('Zoho Books returned code #'.$this->lastRequest['zohoCode'].' "'.$this->lastRequest['zohoMessage'].'"', $this->lastRequest['zohoCode']);
        }

        // check if we have paging info
        if (array_key_exists('page_context', $this->lastRequest['data'])) {
            $this->lastRequest['zohoPaging'] = $this->lastRequest['data']['page_context'];
        }

        // find final response
        $_toRemove = array('code' => null, 'message' => null, 'page_context' => null);
        $jsonData = array_diff_key($this->lastRequest['data'], $_toRemove);
        switch(count($jsonData)) {
            case 0:
                $this->lastRequest['zohoResponse'] = $this->lastRequest['zohoCode'];
                break;
            case 1:
                $this->lastRequest['zohoResponse'] = current($jsonData);
                break;
            default:
                $this->lastRequest['zohoResponse'] = $jsonData;
                break;
        }

        return $this->lastRequest;
    }

    /**
     * Call API method - universal handler
     * It will detect object and action required
     *
     * Generally accepted format is: ActionObject
     * For example: ListBills, CreateBill, DeleteBill
     *
     * Depending on the method, it may get different set of agruments
     *
     * @param string $method Original called method name
     * @param array $args List of arguments passed to function
     */
    public function __call($method, $args)
    {
        // check method 
        if (!array_key_exists($method, $this->methods)) {
            throw new ZohoBooksApiException('requested method "'.$method.'" does not exists', 2);
        }

        // check if it is special ListAll method
        if (substr($method, -7) == 'ListAll') {
            return $this->callListAll($method, $args);
        }

        // get method info
        $methodInfo = $this->methods[$method];

        // check if we have params in URL
        $paramsCount = substr_count($methodInfo['url'], '%s');

        // check if we have required number of arguments
        if (!in_array(count($args), array($paramsCount, $paramsCount+1))) {
            throw new ZohoBooksApiException('method "'.$method.'" requires '.$paramsCount.' or '.($paramsCount+1).' arguments, '.count($args).' received', 3);
        }

        // replace params in URL
        if ($paramsCount > 0) {
            // replace params
            $methodInfo['url'] = vsprintf($methodInfo['url'], array_slice($args, 0, $paramsCount));

            // remove params from the args
            $args = array_slice($args, $paramsCount);
        }

        // get query array
        $query = count($args) ? current($args) : array();
        if (!is_array($query)) {
            throw new ZohoBooksApiException('query data should be array, '.gettype($query).' received', 4);
        }

        // call this method and get response
        $R = $this->makeApiRequest($methodInfo['url'], $methodInfo['method'], $query, isset($methodInfo['raw']) && $methodInfo['raw']);

        return $R['zohoResponse'];
    }

    /**
     * Handler for special case - list all pages
     *
     * @param string $method Full name of the method called
     * @param array $args Original list of the arguments
     */
    private function callListAll($method, $args)
    {
        // rename method
        $method = str_replace('ListAll', 'List', $method);

        // init vars
        $page = 1;
        $rows = array();

        // run queries
        while(true) {
            // build args
            $args[0]['page'] = $page++;

            // get data and append to array
            $R = $this->__call($method, $args);
            $rows = array_merge($rows, $R);

            // check if we have more pages - if not, stop
            if (!$this->lastRequest['zohoPaging'] || !$this->lastRequest['zohoPaging']['has_more_page']) {
                break;
            }
        }

        return $rows;
    }
}

/**
 * Exception class for ZohoBooks API Interface for network and general cases
 */
class ZohoBooksApiException extends Exception {}

/**
 * Exception class for ZohoBooks API Interface for non-zero zoho result code
 */
class ZohoBooksApiRequestException extends Exception {}
