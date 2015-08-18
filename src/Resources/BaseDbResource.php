<?php
namespace DreamFactory\Core\Resources;

use DreamFactory\Core\Components\DbSchemaExtras;
use DreamFactory\Core\Contracts\RequestHandlerInterface;
use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Inflector;

abstract class BaseDbResource extends BaseRestResource
{
    use DbSchemaExtras;

    const RESOURCE_IDENTIFIER = 'name';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var integer Service identifier
     */
    protected $serviceId = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * {@inheritdoc}
     */
    protected function getResourceIdentifier()
    {
        return static::RESOURCE_IDENTIFIER;
    }

    /**
     * @param RequestHandlerInterface $parent
     */
    public function setParent(RequestHandlerInterface $parent)
    {
        parent::setParent($parent);

        /** @var BaseRestService $parent */
        $this->serviceId = $parent->getServiceId();
    }

    /**
     * @return string
     */
    abstract public function getResourceName();

    /**
     * @param null $schema
     * @param bool $refresh
     *
     * @return array
     */
    abstract public function listResources(
        /** @noinspection PhpUnusedParameterInspection */
        $schema = null,
        $refresh = false
    );

    /**
     * {@inheritdoc}
     */
    public function getResources($only_handlers = false)
    {
        if ($only_handlers) {
            return [];
        }

        $refresh = $this->request->getParameterAsBool('refresh');

        $names = $this->listResources(null, $refresh);

        $extras = $this->getSchemaExtrasForTables($names, false, 'table,label,plural');

        $tables = [];
        foreach ($names as $name) {
            if ('_' != substr($name, 0, 1)) {
                $label = '';
                $plural = '';
                foreach ($extras as $each) {
                    if (0 == strcasecmp($name, ArrayUtils::get($each, 'table', ''))) {
                        $label = ArrayUtils::get($each, 'label');
                        $plural = ArrayUtils::get($each, 'plural');
                        break;
                    }
                }

                if (empty($label)) {
                    $label = Inflector::camelize($name, ['_', '.'], true);
                }

                if (empty($plural)) {
                    $plural = Inflector::pluralize($label);
                }

                $tables[] = ['name' => $name, 'label' => $label, 'plural' => $plural];
            }
        }

        return $tables;
    }

    /**
     * @param null $schema
     * @param bool $refresh
     *
     * @return array
     */
    public function listAccessComponents($schema = null, $refresh = false)
    {
        $output = [];
        $result = $this->listResources($schema, $refresh);
        foreach ($result as $name) {
            $output[] = $this->getResourceName() . '/' . $name;
        }

        return $output;
    }

}