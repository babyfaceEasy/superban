<?php

namespace Babyfaceeasy\Superban;

use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Route;

class BanRequestsTest extends TestCase 
{
    public function testSingleRouteWithIncompleteConfigs(): void
    {
        $this->assertTrue(true);
        // given a route with incomplete configs
        //$request =  Request::create();

        // when middleware is set up

        // then assert a runtime error is returned
        // assert the error message is correct too

    }
    

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/dummy-test-route', function () {
            return 'nice';
        })->middleware([\Babyfaceeasy\Superban\Middleware\BanRequests::class.':10,1,1']);
    }

    public function testSuperbanIsActive()
    {
        $this->get('/dummy-test-route')
            ->assertOk()
            ->assertHeader('X-Ratelimit-Limit', 10)
            ->assertHeader('X-Ratelimit-Remaining', 9);
    }

    public function testSuperbanDecreasesRemaining()
    {
        foreach(range(1, 10) as $i) {
            $this->get('/dummy-test-route')
                ->assertOk()
                ->assertHeader('X-Ratelimit-Remaining', 10 - $i);
        }

        $this->get('/dummy-test-route')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', 60);
    }

    public function testSuperbanWorksWithIPAddressUsers()
    {
        foreach(range(1, 10) as $i) {
            $this->get('/dummy-test-route')
                ->assertOk()
                ->assertHeader('X-Ratelimit-Remaining', 10 - $i);
        }
        $this->get('/dummy-test-route')
            ->assertTooManyRequests();
    }
}