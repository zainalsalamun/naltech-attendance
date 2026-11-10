<?php

namespace Tests\MySql;

class LeaveBalanceMySqlTest extends \Tests\Feature\LeaveBalanceTest
{
    protected function beforeRefreshingDatabase()
    {
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
    }

    public function createApplication()
    {
        $app = parent::createApplication();
        Connection::configure($app);

        return $app;
    }
}
