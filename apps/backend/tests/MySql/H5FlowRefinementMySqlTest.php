<?php

namespace Tests\MySql;

class H5FlowRefinementMySqlTest extends \Tests\Feature\H5FlowRefinementTest
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
