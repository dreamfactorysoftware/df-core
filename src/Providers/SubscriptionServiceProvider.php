<?php
namespace DreamFactory\Core\Providers;

use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Services\ServiceManager;
use DreamFactory\Core\Services\ServiceType;
use Illuminate\Support\ServiceProvider;

class SubscriptionServiceProvider extends ServiceProvider
{
    public function register()
    {
        // subscription required, here for advertising
        $this->app->resolving('df.service', function (ServiceManager $df) {
            $df->addType(new ServiceType(
                [
                    'name'                  => 'adldap',
                    'label'                 => 'Active Directory',
                    'description'           => 'A service for supporting Active Directory integration',
                    'group'                 => ServiceTypeGroups::LDAP,
                    'subscription_required' => true,
                ]));
            $df->addType(new ServiceType(
                [
                    'name'                  => 'ldap',
                    'label'                 => 'Standard LDAP',
                    'description'           => 'A service for supporting Open LDAP integration',
                    'group'                 => ServiceTypeGroups::LDAP,
                    'subscription_required' => true,
                ]));
            $df->addType(new ServiceType(
                [
                    'name'                  => 'soap',
                    'label'                 => 'SOAP Service',
                    'description'           => 'A service to handle SOAP Services',
                    'group'                 => ServiceTypeGroups::REMOTE,
                    'subscription_required' => true,
                ]));
            $df->addType(new ServiceType(
                [
                    'name'                  => 'sqlanywhere',
                    'label'                 => 'SAP SQL Anywhere',
                    'description'           => 'Database service supporting SAP SQL Anywhere connections.',
                    'group'                 => ServiceTypeGroups::DATABASE,
                    'subscription_required' => true,
                ]));
            $df->addType(new ServiceType(
                [
                    'name'                  => 'salesforce_db',
                    'label'                 => 'Salesforce',
                    'description'           => 'Database service for Salesforce connections.',
                    'group'                 => ServiceTypeGroups::DATABASE,
                    'subscription_required' => true,
                ]));
            $df->addType(new ServiceType(
                [
                    'name'                  => 'sqlsrv',
                    'label'                 => 'SQL Server',
                    'description'           => 'Database service supporting SQL Server connections.',
                    'group'                 => ServiceTypeGroups::DATABASE,
                    'subscription_required' => true,
                ]));
            $df->addType(new ServiceType(
                [
                    'name'                  => 'oracle',
                    'label'                 => 'Oracle',
                    'description'           => 'Database service supporting SQL connections.',
                    'group'                 => ServiceTypeGroups::DATABASE,
                    'subscription_required' => true,
                ]));
            $df->addType(new ServiceType(
                [
                    'name'                  => 'ibmdb2',
                    'label'                 => 'IBM DB2',
                    'description'           => 'Database service supporting IBM DB2 SQL connections.',
                    'group'                 => ServiceTypeGroups::DATABASE,
                    'subscription_required' => true,
                ]));
        });
    }
}
