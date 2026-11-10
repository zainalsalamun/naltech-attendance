<?php

namespace Tests\MySql;

class AttendanceCorrectionRecapMySqlTest extends \Tests\Feature\AttendanceCorrectionRecapTest
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
