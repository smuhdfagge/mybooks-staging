<?php

namespace Tests;

use Illuminate\Testing\PendingCommand;

/**
 * A command run from a test as cron runs it: with nobody signed in. The
 * signed-in test user would otherwise carry into it, and its tenant-scoped
 * queries would quietly have a business they never have on the server
 * (audit MB-26). Whoever was signed in is put back afterwards.
 */
class PendingCommandAsCron extends PendingCommand
{
    public function run()
    {
        $user = auth()->user();
        auth()->forgetGuards();

        try {
            return parent::run();
        } finally {
            if ($user) {
                auth()->setUser($user);
            }
        }
    }
}
