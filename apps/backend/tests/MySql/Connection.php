<?php

namespace Tests\MySql;

class Connection
{
    public static function configure($app): void
    {
        $dir = getenv('NALTECH_MYSQL_QA_DIR');
        if (! $dir || ! is_file($dir.'/.naltech-mysql-qa')) {
            throw new \RuntimeException('Prepare an isolated MySQL QA directory first.');
        }
        $config = json_decode(file_get_contents($dir.'/connection.json'), true, flags: JSON_THROW_ON_ERROR);
        if (! preg_match('/^naltech_qa_[a-f0-9]{12}$/', $config['database'] ?? '') ||
            $config['username'] !== $config['database'] ||
            trim(file_get_contents($dir.'/.naltech-mysql-qa')) !== $config['database']) {
            throw new \RuntimeException('MySQL QA isolation check failed.');
        }
        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql', $config);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('t', 32)));
        $app['db']->purge('mysql');
        $connection = $app['db']->connection('mysql');
        if ($connection->getDriverName() !== 'mysql' || $connection->getDatabaseName() !== $config['database']) {
            throw new \RuntimeException('MySQL QA connection mismatch.');
        }
    }
}
