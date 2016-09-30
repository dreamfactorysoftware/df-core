<?php

namespace DreamFactory\Core\Enums;

use DreamFactory\Library\Utility\Enums\FactoryEnum;

class ServiceTypeGroups extends FactoryEnum
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    const API_DOC = 'API Doc';

    const CACHE = 'Cache';

    const DATABASE = 'Database';

    const EMAIL = 'Email';

    const EVENT = 'Event';

    const FILE = 'File';

    const LDAP = 'LDAP';

    const NOTIFICATION = 'Notification';

    const OAUTH = 'OAuth';

    const REMOTE = 'Remote Service';

    const SCRIPT = 'Script';

    const SYSTEM = 'System';

    const USER = 'User';

    const LOG = 'Log';
}