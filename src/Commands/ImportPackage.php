<?php

namespace DreamFactory\Core\Commands;

use DreamFactory\Core\Components\Package\Importer;
use DreamFactory\Core\Components\Package\Package;
use DreamFactory\Core\Utility\FileUtilities;
use DreamFactory\Core\Utility\Packager;
use Illuminate\Console\Command;

class ImportPackage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'df:import-pkg 
                            {path? : Relative path to the package file/folder/url}
                            {--password= : Password for encrypted package}
                            {--delete : Delete package after import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import resources from a DreamFactory package (dfpkg) or zip file.';

    /**
     * Keeps track of errors when a file fails to import.
     *
     * @type array
     */
    protected $errors = [];

    /**
     * Keeps track of import logs of all files being imported.
     *
     * @type array
     */
    protected $importLog = [];

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Runs the command.
     */
    public function handle()
    {
        $path = $this->getPath();

        if (is_file($path)) {
            $this->importPackage($path);
            $this->printResult();
        } elseif (static::isRemoteUrl($path)) {
            // The path comes from the operator, so the package may live on any host it can reach. Download it
            // here and import the local copy.
            try {
                $file = FileUtilities::importUrlFileToTemp($path, '', true);
            } catch (\Exception $e) {
                $this->error('Could not download package from ' . $path . ': ' . $e->getMessage());

                return 1;
            }

            try {
                $this->importPackage($file, $path, true);
            } finally {
                @unlink($file);
            }
            $this->printResult();
        } elseif (is_dir($path)) {
            $files = static::getFilesFromPath($path);
            if (count($files) == 0) {
                $this->warn('No package to import from ' . $path);
            } else {
                foreach ($files as $file) {
                    try {
                        $this->importPackage($file);
                    } catch (\Exception $e) {
                        $this->errors[] = [
                            'file' => $file,
                            'msg'  => $e->getMessage()
                        ];
                    }
                }
                $this->printResult();
            }
        } else {
            $this->error('Invalid path ' . $path);
        }
    }

    /**
     * Prints result.
     */
    protected function printResult()
    {
        $errorCount = count($this->errors);
        if ($errorCount > 0) {
            $fileCount = count(static::getFilesFromPath($this->getPath()));
            if ($errorCount < $fileCount) {
                $this->warn('Not all files were imported successfully. See details below.');
            } elseif ($errorCount == $fileCount) {
                $this->error('None of your files where imported successfully. See details below.');
            }
            $this->warn('---------------------------------------------------------------------------------------');
            foreach ($this->errors as $error) {
                $this->warn('Failed importing: ' . array_get($error, 'file'));
                $this->warn('Error: ' . array_get($error, 'msg'));
                $this->warn('---------------------------------------------------------------------------------------');
            }
        } else {
            $this->info('Successfully imported your file(s).');
        }
        $this->printImportLog();
    }

    /**
     * Imports package.
     *
     * @param string      $file       Local package file.
     * @param string|null $source     Where the package came from, for the import log. Defaults to $file.
     * @param bool        $downloaded True when $file is a temporary copy downloaded by this command.
     *
     * @throws \Exception
     */
    protected function importPackage($file, $source = null, $downloaded = false)
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if ($extension === Packager::FILE_EXTENSION) {
            $this->importOldPackage($file, $downloaded);
        } else {
            $package = new Package($file, $downloaded || $this->option('delete'), $this->option('password'));
            $importer = new Importer($package);
            $importer->import();
            $log = $importer->getLog();
            $this->importLog[] = [
                'file' => $source ?: $file,
                'log'  => $log
            ];
        }
    }

    /**
     * Prints import log.
     */
    protected function printImportLog()
    {
        if (count($this->importLog) > 0) {
            $this->info('Import log');
            $this->info('---------------------------------------------------------------------------------------');
            foreach ($this->importLog as $il) {
                $this->info('Import File: ' . $il['file']);
                foreach ($il['log'] as $level => $logs) {
                    $this->info(strtoupper($level));
                    foreach ($logs as $log) {
                        $this->info('   -> ' . $log);
                    }
                }
                $this->info('---------------------------------------------------------------------------------------');
            }
        }
    }

    /**
     * Returns packaged file(s) from import path.
     *
     * @param $path
     *
     * @return array
     */
    protected static function getFilesFromPath($path)
    {
        $files = [];
        $path = rtrim($path, '/') . DIRECTORY_SEPARATOR;

        if (false !== $items = scandir($path)) {
            foreach ($items as $item) {
                $file = $path . $item;
                if (is_file($file)) {
                    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (in_array($extension, [Packager::FILE_EXTENSION, Package::FILE_EXTENSION])) {
                        $files[] = $file;
                    }
                }
            }
        }

        return $files;
    }

    /**
     * Imports old .dfpkg files.
     *
     * @param string $file
     * @param bool   $downloaded True when $file is a temporary copy downloaded by this command.
     *
     * @throws \Exception
     */
    protected function importOldPackage($file, $downloaded = false)
    {
        if ($downloaded) {
            // Hand the downloaded copy over as a local file; Packager removes it when it is done.
            $file = ['name' => basename($file), 'tmp_name' => $file, 'error' => UPLOAD_ERR_OK];
        }
        $packager = new Packager($file);
        $packager->importAppFromPackage();
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    protected static function isRemoteUrl($path)
    {
        $scheme = strtolower((string)parse_url((string)$path, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * Gets path to import package(s) from.
     *
     * @return array|string
     */
    protected function getPath()
    {
        $path = $this->argument('path');
        if (empty($path)) {
            $path = config('df.package_path');
        }

        return $path;
    }
}
