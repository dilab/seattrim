<?php

namespace App\Zoom\Exceptions;

use RuntimeException;

/** Raised when a refresh fails permanently and the org must reconnect. */
class ConnectionRevokedException extends RuntimeException {}
