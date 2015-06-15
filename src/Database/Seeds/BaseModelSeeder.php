<?php
namespace DreamFactory\Core\Database\Seeds;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Inflector;
use DreamFactory\Core\Models\BaseModel;
use Illuminate\Database\Seeder;

class BaseModelSeeder extends Seeder
{
    protected $modelClass = null;

    protected $recordIdentifier = 'name';

    protected $allowUpdate = false;

    //  Add the default system_resources
    protected $records = [];

    /**
     * Run the database seeds.
     *
     * @throws \Exception
     */
    public function run()
    {
        BaseModel::unguard();

        if (empty($this->modelClass)) {
            throw new \Exception("Invalid seeder model. No value for {$this->modelClass}.");
        }

        /** @var BaseModel $modelName */
        $modelName = $this->modelClass;
        $created = [];
        $updated = [];
        foreach ($this->records as $record) {
            $name = ArrayUtils::get($record, $this->recordIdentifier);
            if (empty($name)) {
                throw new \Exception("Invalid seeder record. No value for {$this->recordIdentifier}.");
            }

            if (!$modelName::where($this->recordIdentifier, $record[$this->recordIdentifier])->exists()) {
                // seed the record
                $modelName::create(array_merge($record, $this->getRecordExtras()));
                $created[] = $name;
            } elseif ($this->allowUpdate) {
                // update an existing record
                $updated[] = $name;
            }
        }

        $this->outputMessage($created, $updated);
    }

    protected function outputMessage(array $created = [], array $updated = [])
    {
        $msg = static::separateWords(static::getModelBaseName($this->modelClass)) . ' resources';

        if (!empty($created)) {
            $this->command->info($msg . ' created: ' . implode(', ', $created));
        }
        if (!empty($updated)) {
            $this->command->info($msg . ' updated: ' . implode(', ', $updated));
        }
    }

    protected function getRecordExtras()
    {
        return [];
    }

    public static function getModelBaseName($fqcn)
    {
        if (preg_match('@\\\\([\w]+)$@', $fqcn, $matches)) {
            $fqcn = $matches[1];
        }

        return $fqcn;
    }

    public static function separateWords($string)
    {
        return preg_replace("/([a-z])([A-Z])/", "\\1 \\2", $string);
    }
}
