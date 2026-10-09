<?php

namespace App\Services\BankFeeds;

/** The bank wants the account holder to log in again before more data can be read. */
class ReauthorisationRequired extends BankFeedException {}
