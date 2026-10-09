<?php

namespace App\Services\BankFeeds;

/** The provider could not be reached or was busy (timeout, 5xx, 429): try again later. */
class TemporaryFailure extends BankFeedException {}
