<?php

namespace App\Services\Messaging;

use RuntimeException;

/**
 * The provider couldn't be reached or was busy (timeout, 5xx, 429): the
 * message is tried again later.
 */
class TemporaryFailure extends RuntimeException {}
