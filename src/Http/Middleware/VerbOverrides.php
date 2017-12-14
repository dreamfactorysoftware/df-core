<?php

namespace DreamFactory\Core\Http\Middleware;

use Closure;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use ServiceManager;

class VerbOverrides
{
    /**
     * Check for verb tunneling by the various method override headers or query params
     * Tunnelling verb overrides:
     *      X-Http-Method (Microsoft)
     *      X-Http-Method-Override (Google/GData)
     *      X-Method-Override (IBM)
     * Symfony natively supports X-HTTP-METHOD-OVERRIDE header and "_method" URL parameter
     * we just need to add our historical support for other options, including "method" URL parameter
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return array|mixed|string
     */
    public function handle($request, Closure $next)
    {
        $request->enableHttpMethodParameterOverride(); // enables _method URL parameter
        $method = $request->getMethod();
        if (('POST' === $method) &&
            (!empty($dfOverride = $request->header('X-HTTP-Method',
                $request->header('X-Method-Override', $request->query('method')))))
        ) {
            $request->setMethod($method = strtoupper($dfOverride));
        }
        // support old MERGE as PATCH
        if ('MERGE' === strtoupper($method)) {
            $request->setMethod('PATCH');
        }

        return $next($request);
    }
}
