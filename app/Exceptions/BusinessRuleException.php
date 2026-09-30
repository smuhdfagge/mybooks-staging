<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business rule the user's request broke (e.g. posting an unbalanced
 * journal). The message is safe to show, and the API answers 422 (I6).
 */
class BusinessRuleException extends RuntimeException {}
