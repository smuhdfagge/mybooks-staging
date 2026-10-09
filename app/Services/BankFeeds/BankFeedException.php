<?php

namespace App\Services\BankFeeds;

use RuntimeException;

/** A bank feed call failed. The message is plain enough to show to the user. */
class BankFeedException extends RuntimeException {}
