<?php
/**
 * Whoops - php errors for cool kids
 * @author Filipe Dobreira <http://github.com/filp>
 */

namespace App\Exceptions;

use Whoops\Handler\JsonResponseHandler;
use Whoops\Exception\Formatter;
use Whoops\Handler\Handler;

/**
 * Catches an exception and converts it to a JSON
 * response. Additionally can also return exception
 * frames for consumption by an API.
 */
class ResponseHandler extends JsonResponseHandler
{
    public function handle()
    {
        $response = [
                'statusCode' => 500,
                'title' => 'Oops Something went wrong!', 
                'message' => 'Oops Something went wrong!',
                'responseData' => [],
                'error' => Formatter::formatExceptionAsDataArray(
                    $this->getInspector(),
                    $this->addTraceToOutput()
                ),
            ];

        echo json_encode($response, defined('JSON_PARTIAL_OUTPUT_ON_ERROR') ? JSON_PARTIAL_OUTPUT_ON_ERROR : 0);

        return Handler::QUIT;
    }
}
