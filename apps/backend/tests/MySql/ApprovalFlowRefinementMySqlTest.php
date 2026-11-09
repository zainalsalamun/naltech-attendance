<?php

namespace Tests\MySql;

class ApprovalFlowRefinementMySqlTest extends \Tests\Feature\ApprovalFlowRefinementTest
{
    protected function beforeRefreshingDatabase()
    {
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
    }

    public function createApplication()
    {
        $app = parent::createApplication();
        Connection::configure($app);
        $app['config']->set('session.driver', 'array');

        return $app;
    }
}
