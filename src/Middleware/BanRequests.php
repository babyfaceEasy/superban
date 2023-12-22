<?php

namespace Babyfaceeasy\Superban\Middleware;

use Closure;
use RuntimeException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\InteractsWithTime;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Exceptions\HttpResponseException;
use Babyfaceeasy\Superban\Exceptions\BanRequestsException;

class BanRequests
{
    use InteractsWithTime;
    /**
     * The rate limiter key.
     *
     * @var string
     */
    protected $rateLimiterKey;

    /**
     * The rate limiter instance.
     *
     * @var \Illuminate\Cache\RateLimiter
     */
    protected $limiter;

    /**
     * Create a new request banner.
     *
     * @param  \Illuminate\Cache\RateLimiter  $limiter
     * @return void
     */
    public function __construct(RateLimiter $limiter)
    {
        $this->limiter =  $limiter;
        $this->rateLimiterKey = 'ban';
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $config
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Babyfaceeasy\Superban\Exceptions\BanRequestsException
     */
    public function handle( $request, \Closure $next,  int $maxAttempts, int $period, int $decayMinutes)
    {
        // nb : decayMinutes is how long a client is blocked for
        $key = 'ban'.$this->resolveRequestSignature($request);
        $maxAttempts = $this->resolveMaxAttempts($request, $maxAttempts);

        if ($this->limiter->limiter($key) === null) {
            // create it 
            $this->limiter->for($key, function(Request $request) use ($decayMinutes, $maxAttempts) {
                return Limit::perMinutes($decayMinutes, $maxAttempts);
            });
        }
        
        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            throw $this->buildException($request, $key, $maxAttempts, null);
        }

        $this->limiter->hit($key, $decayMinutes * 60);

        $response = $next($request);

        $response = $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );

        return $response;
    }

    /**
     * Add the limit header information to the given response.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  int  $maxAttempts
     * @param  int  $remainingAttempts
     * @param  int|null  $retryAfter
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function addHeaders(Response $response, $maxAttempts, $remainingAttempts, $retryAfter = null)
    {
        $response->headers->add(
            $this->getHeaders($maxAttempts, $remainingAttempts, $retryAfter, $response)
        );

        return $response;
    }

    /**
     * Resolve the number of attempts if the user is authenticated or not.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int|string  $maxAttempts
     * @return int
     */
    protected function resolveMaxAttempts($request, $maxAttempts)
    {
        if (str_contains($maxAttempts, '|')) {
            $maxAttempts = explode('|', $maxAttempts, 2)[$request->user() ? 1 : 0];
        }

        if (! is_numeric($maxAttempts) && $request->user()) {
            $maxAttempts = $request->user()->{$maxAttempts};
        }

        return (int) $maxAttempts;
    }

     /**
     * Resolve request signature.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     *
     * @throws \RuntimeException
     */
    protected function resolveRequestSignature($request)
    {
        /** @var $user \App\Models\User */
        if ($user = $request->user()) {
            return $user->getAuthIdentifier();
        } elseif ($route = $request->route()) {
            return $route->getDomain().'|'.$request->ip();
        }

        throw new RuntimeException('Unable to generate the request signature. Route unavailable.');
    }

    /**
     * Create a 'too many attempts' exception.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $key
     * @param  int  $maxAttempts
     * @param  callable|null  $responseCallback
     * @return \Babyfaceeasy\Superban\Exceptions\BanRequestsException|\Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function buildException($request, $key, $maxAttempts, $responseCallback = null)
    {
        $retryAfter = $this->getTimeUntilNextRetry($key);

        $headers = $this->getHeaders(
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts, $retryAfter),
            $retryAfter
        );

        return is_callable($responseCallback)
                    ? new HttpResponseException($responseCallback($request, $headers))
                    : new BanRequestsException('Too Many Attempts.', null, $headers);
    }

    /**
     * Get the number of seconds until the next retry.
     *
     * @param  string  $key
     * @return int
     */
    protected function getTimeUntilNextRetry($key)
    {
        return $this->limiter->availableIn($key);
    }


    /**
     * Get the limit headers information.
     *
     * @param  int  $maxAttempts
     * @param  int  $remainingAttempts
     * @param  int|null  $retryAfter
     * @param  \Symfony\Component\HttpFoundation\Response|null  $response
     * @return array
     */
    protected function getHeaders($maxAttempts,
                                  $remainingAttempts,
                                  $retryAfter = null,
                                  ?Response $response = null)
    {
        if ($response &&
            ! is_null($response->headers->get('X-RateLimit-Remaining')) &&
            (int) $response->headers->get('X-RateLimit-Remaining') <= (int) $remainingAttempts) {
            return [];
        }

        $headers = [
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ];

        if (! is_null($retryAfter)) {
            $headers['Retry-After'] = $retryAfter;
            $headers['X-RateLimit-Reset'] = $this->availableAt($retryAfter);
        }

        return $headers;
    }

    /**
     * Calculate the number of remaining attempts.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @param  int|null  $retryAfter
     * @return int
     */
    protected function calculateRemainingAttempts($key, $maxAttempts, $retryAfter = null)
    {
        return is_null($retryAfter) ? $this->limiter->retriesLeft($key, $maxAttempts) : 0;
    }

    /**
     * Convert parameter passed to middleware into config
     * 
     * @param string $config
     * @return array[string]
     * 
     * @throws \RuntimeException
     */
    protected function resolveConfigs($config): array
    {
        $configs = explode(",", $config);
        if (count($configs) !== 3) {
            throw new RuntimeException('Arguments passed are not enough.');
        }

        foreach ($configs as $$config) {
            if (is_int($config) == false) {
                throw new RuntimeException('Arguments passed must all be integers.');
            }
        }

        return $configs;
    }

}