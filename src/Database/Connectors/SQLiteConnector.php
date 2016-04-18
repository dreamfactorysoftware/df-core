<?php

namespace DreamFactory\Core\Database\Connectors;

class SQLiteConnector extends \Illuminate\Database\Connectors\SQLiteConnector
{
    /**
     * Establish a database connection.
     *
     * @param  array  $config
     * @return \PDO
     */
    public function connect(array $config)
    {
        $options = $this->getOptions($config);

        // SQLite supports "in-memory" databases that only last as long as the owning
        // connection does. These are useful for tests or for short lifetime store
        // querying. In-memory databases may only have a single open connection.
        if ($config['database'] == ':memory:') {
            return $this->createConnection('sqlite::memory:', $config, $options);
        }

        // PDO driver will automatically create the file if permissions are granted.
        $file = $config['database'];
        if (false === strpos($file, DIRECTORY_SEPARATOR)) {
            // no directories involved, store it where we want to store it
            if (!empty($storage = config('df.db.sqlite_storage'))) {
                $file = rtrim($storage, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
            }
        }
        if (false === $path = realpath($file)) {
            $storage = dirname($file);
            if (false === $path = realpath($storage)) {
                // Attempt
                @mkdir($storage);
            }
            if (false === $path = realpath($storage)) {
                logger('Failed to access storage path ' . $storage);
                throw new \InvalidArgumentException('Failed to create storage location for SQLite database.');
            }
            $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($file);
        }

        return $this->createConnection("sqlite:{$path}", $config, $options);
    }
}
