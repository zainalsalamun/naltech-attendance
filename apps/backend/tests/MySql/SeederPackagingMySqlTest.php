<?php

namespace Tests\MySql;

class SeederPackagingMySqlTest extends \Tests\Feature\SeederPackagingTest
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
