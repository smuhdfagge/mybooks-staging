<?php

namespace App\Services\BankFeeds;

/** No provider keys are set (the log driver is in use), so nothing can be linked. */
class NotSetUp extends BankFeedException
{
    public function __construct(string $message = 'Bank feeds are not set up yet. The MyBooks team needs to add the Mono keys.')
    {
        parent::__construct($message);
    }
}
