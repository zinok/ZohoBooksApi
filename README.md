# ZohoBook API Interface

## Init

To init API Interface call:

```php
$zbApi = new ZohoBooksApi('<authToken>', '<organizationId>');
```

Refer to ZohoBook API documentation for details on `authToken` and `organizationId`:
https://www.zoho.com/books/api/v3/


## API Requests Limit

At the moment of development ZohoBooks had limitation of 150 API requests per second.
The library includes check for the number of requests and in case if limit is reached 
will sleep for respective amount of time.

You can pass third argument to the constructor to change this limit or pass 0 to disable.

## Methods

The library is implemented using PHP `__call` handler. At constructor library inits general
list of allowed methods. It includes all general objects and typical actions: `List`, `Get`, 
`Create`, `Update`, `Delete`. Plus magic action `ListAll`. As result constructor fills 
internal `ZohoBooksApi::methods` array with list of allowed methods. 

### Parameters

Each method that has ID in the URL takes respective number of arguments. 

For methods like Create or Update you can add one more argument as array with details 
about the object. For List method this argument may contain page number or order field.

Please refer to ZohoBooks API documentation for the list of allowed fields:
https://www.zoho.com/books/api/v3/

### Return Values

By default return value is main element of the API Response. For Get - it is the object 
itself. For List - it is array of the objects, etc. If there are no data in return 
(eg Delete) - return will be set to Zoho API return code. 

As addition to this, ZohoBooksApi class saves `$zbApi->lastRequest` array with next details:
* `httpCode` - HTTP return code
* `dataRaw` - Raw data returned from ZohoBooks API
* `data` - Response decoded from JSON as array
* `zohoCode` - ZohoBooks API return code
* `zohoMessage` - ZohoBooks API return message
* `zohoPaging` - ZohoBooks API paging info (only for list methods)
* `zohoResponse` - Alias for "main" return value

### Example

```php
// get details about contact with ID = 7
$contact = $zbApi->ContactsGet(7);

// create contact
$contact = array(
    'name' => 'Super Company',
    ...
);
$zbApi->ContactsCreate($contact);
```

### Magic ListAll method

In case if the name of method ends with `ListAll` library will handle it specially.
It will case `List` method instead, taking all pages starting from 1 and merging 
all results in a single array, which is then returned. 

Obviously more than one call to API may be performed.

### Adding other methods

It will be possible to add methods on your own to this array. Here is an example:
```php
$this->methods['ContactsDisable'] = array(
    'url' => '/contacts/%s/inactive',
    'method' => 'POST',
    'raw' => false
);
```

Array key is the name of the method. Parameters are:
* `url` - API URL, all IDs should be written as %s
* `method` - HTTP method for the request
* `raw` - If set to true, library will not try to decode JSON


## Exceptions

Library defines own exceptions.

### ZohoBooksApiRequestException

Thrown if ZohoBooks returns non-zero return code. The code means that request has some 
logical issue.

### ZohoBooksApiHttpException

Exception for HTTP non 2xx codes. The code of exception equals to HTTP return code.

### ZohoBooksApiException

General exception. Thrown in case of incorrect method naming, non-2xx HTTP status code, 
incorrect return data format, etc.


## License

The MIT License

Copyright (c) 2015 Andrii Zinchenko (http://www.zinok.org/)

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.