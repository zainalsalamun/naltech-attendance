<?php

namespace Tests\MySql;

class H1FlowRefinementMySqlTest extends \Tests\Feature\H1FlowRefinementTest
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
