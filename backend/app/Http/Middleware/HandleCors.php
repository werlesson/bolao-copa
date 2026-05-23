<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\HandleCors as BaseHandleCors;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleCors extends BaseHandleCors
{
    public function handle($request, Closure $next): Response
    {
        if (! $this->hasMatchingPath($request)) {
            return $next($request);
        }

        $this->cors->setOptions($this->container['config']->get('cors', []));

        // Qualquer OPTIONS com Origin (ex.: reload) recebe preflight completo.
        if ($request->isMethod('OPTIONS') && $this->cors->isCorsRequest($request)) {
            $response = $this->cors->handlePreflightRequest($request);
            $this->cors->varyHeader($response, 'Access-Control-Request-Method');

            return $response;
        }

        return parent::handle($request, $next);
    }
}
