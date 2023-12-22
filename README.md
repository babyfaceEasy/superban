# Superban Package

## Introduction
Superban is a Laravel package that gives your Laravel application the ability to ban clients from accessing endpoint(s) for a period of time.

## Installation

Currently, "superban" is still been developed and can only be used locally.
Follow the steps below, in order to use superban:
1. Download the code to your system.
2. Create a folder inside your laravel app named `packages\<vendor>`. Make sure the folder is on the same level ass app.<br>
*Note:* `<vendor>` should be replaced with any value but should be the same value for all the steps during installation. 
3. Go into your `composer.json` and add the following: <br>
```
"repositories":{
        "superban": {
            "type": "path",
            "url": "packages/<vendor>/superban",
            "options": {
                "symlink": true
            }
        } 
    },
```
4. Go into your `composer.json` inside the require object and add <br> ```"<vendor>/superban": "@dev"```

## Usage
1. Go to the file where your routes are declared e.g `web.php` .
2. Then make use of the middleware alias `superban` for a single route / group of routes e.g 
<br>
```
Route::get('/test', [TestController::class, 'test'])->middleware('superban:A,B,C');
``` 
```
Route::middleware(['superban:10,1,1', 'api'])->group(function () {
    Route::get('/', function () {
    });
 
    Route::get('/user/profile', function () {
    });
});
```
where `A` it the number of requests<br>
`B` is the number of minutes in which requests can happen<br>
`C` is the number of minutes in which the user is banned for. <br>
*NB* `A,B & C` are all integer values.
