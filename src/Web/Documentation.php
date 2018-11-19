<?php
/**
 * The Web namespace handles the external communication with the Acumulus API
 * and contains the following classes:
 * - {@see Service}: Provides abstracted access to the Acumulus API methods.
 * - {@see Result}: Contains the result of a service call: the actual response
 *   and any other information like the status, exceptions, error messages, and
 *   warnings.
 * - {@see Communicator}: Handles the actual communication (connecting, sending
 *   requests, and receiving responses), including conversion between PHP
 *   variables and message formats (xml and json), and error handling.
 *
 * When implementing a new extension, you should not have to override any of the
 * classes in this namespace.
 */
namespace Siel\Acumulus\Web;
