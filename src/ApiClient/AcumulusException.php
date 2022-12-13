<?php

declare(strict_types=1);

namespace Siel\Acumulus\ApiClient;

use RuntimeException;

/**
 * Class AcumulusException defines an error condition that occurred during the
 * execution of an API request.
 *
 * It can be:
 * - A caught and repacked {@see \RuntimeException} from the
 *   {@see HttpRequest} (or {@see HttpResponse}).
 * - An exception that occurred during format conversion on a place that does
 *   not have access to the http request or response. To log the exception
 *   together with the request and, if any, received response, these
 *   exceptions should be caught and rethrown on a higher level
 */
class AcumulusException extends RuntimeException {}
