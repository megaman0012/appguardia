<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsService
{
    protected $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'paths' => ['api/*', 'sanctum/csrf-cookie'],
            'allowed_methods' => ['*'],
            'allowed_origins' => ['*'],
            'allowed_origins_patterns' => [],
            'allowed_headers' => ['*'],
            'exposed_headers' => [],
            'max_age' => 0,
            'supports_credentials' => false,
        ], $config);
    }

    /**
     * Determine if the request has a URI that should pass through the CORS flow.
     */
    public function shouldRun(Request $request): bool
    {
        return $this->isMatchingPath($request);
    }

    /**
     * Check if the request matches the configured paths.
     */
    protected function isMatchingPath(Request $request): bool
    {
        // Get the paths from the config
        $paths = $this->getPathsByHost($request->getHost());

        foreach ($paths as $path) {
            if ($path !== '/') {
                $path = trim($path, '/');
            }

            if ($request->fullUrlIs($path) || $request->is($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Paths by given host or string values in config by default
     */
    public function getPathsByHost(string $host): array
    {
        $paths = $this->config['paths'];

        // If where are paths by given host
        if (isset($paths[$host])) {
            return $paths[$host];
        }

        // Defaults
        return array_filter($paths, function ($path) {
            return is_string($path);
        });
    }

    /**
     * Check if the request is a preflight request.
     */
    public function isPreflightRequest(Request $request): bool
    {
        return $request->getMethod() === 'OPTIONS'
            && $request->headers->has('Access-Control-Request-Method');
    }

    /**
     * Handle a preflight request.
     */
    public function handlePreflightRequest(Request $request): Response
    {
        $response = new Response();

        // Set allowed methods
        if ($this->config['allowed_methods'] === ['*']) {
            $response->headers->set('Access-Control-Allow-Methods', '*');
        } else {
            $response->headers->set(
                'Access-Control-Allow-Methods',
                implode(', ', $this->config['allowed_methods'])
            );
        }

        // Set allowed origins
        if ($this->config['allowed_origins'] === ['*']) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        } else {
            $origin = $request->headers->get('Origin');
            if ($this->isOriginAllowed($origin)) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
            }
        }

        // Set allowed headers
        if ($this->config['allowed_headers'] === ['*']) {
            $response->headers->set('Access-Control-Allow-Headers', '*');
        } else {
            $response->headers->set(
                'Access-Control-Allow-Headers',
                implode(', ', $this->config['allowed_headers'])
            );
        }

        // Set max age
        if ($this->config['max_age'] > 0) {
            $response->headers->set('Access-Control-Max-Age', (string) $this->config['max_age']);
        }

        // Set credentials
        if ($this->config['supports_credentials']) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        // Set exposed headers
        if (!empty($this->config['exposed_headers'])) {
            $response->headers->set(
                'Access-Control-Expose-Headers',
                implode(', ', $this->config['exposed_headers'])
            );
        }

        return $response;
    }

    /**
     * Check if the origin is allowed.
     */
    protected function isOriginAllowed(?string $origin): bool
    {
        if (!$origin) {
            return false;
        }

        // Check exact matches
        if (in_array($origin, $this->config['allowed_origins'], true)) {
            return true;
        }

        // Check pattern matches
        foreach ($this->config['allowed_origins_patterns'] as $pattern) {
            if (preg_match($pattern, $origin)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add the actual request headers to the response.
     */
    public function addActualRequestHeaders(Response $response, Request $request): Response
    {
        // Set allowed origin
        if ($this->config['allowed_origins'] === ['*']) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        } else {
            $origin = $request->headers->get('Origin');
            if ($this->isOriginAllowed($origin)) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
            }
        }

        // Set exposed headers
        if (!empty($this->config['exposed_headers'])) {
            $response->headers->set(
                'Access-Control-Expose-Headers',
                implode(', ', $this->config['exposed_headers'])
            );
        }

        // Set max age
        if ($this->config['max_age'] > 0) {
            $response->headers->set('Access-Control-Max-Age', (string) $this->config['max_age']);
        }

        // Set credentials
        if ($this->config['supports_credentials']) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    /**
     * Vary header based on request.
     */
    public function varyHeader(Response $response, string $header): Response
    {
        $vary = $response->headers->get('Vary');
        $varyArray = $vary ? explode(', ', $vary) : [];

        if (!in_array($header, $varyArray, true)) {
            $varyArray[] = $header;
            $response->headers->set('Vary', implode(', ', $varyArray));
        }

        return $response;
    }
}