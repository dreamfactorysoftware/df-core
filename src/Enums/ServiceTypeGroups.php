<?php

namespace DreamFactory\Core\Enums;

use DreamFactory\Library\Utility\Enums\FactoryEnum;

class ServiceTypeGroups extends FactoryEnum
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    const API_DOC = 'API Doc';

    const CUSTOM = 'Custom';

    const DATABASE = 'Database';

    const EMAIL = 'Email';

    const EVENT = 'Event';

    const FILE = 'File';

    const LDAP = 'LDAP';

    const NOTIFICATION = 'Notification';

    const OAUTH = 'OAuth';

    const SYSTEM = 'System';

    const USER = 'User';
}