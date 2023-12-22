# Superban Package

## Introduction
Superban is a Laravel package that gives your Laravel application the ability to ban clients from accessing endpoint(s) for a period of time.

## Installation

Currently, "superban" is still been developed and can only be used locally.
Follow the steps below, in order to use superban:
1. Download the code to your system.
2. Create a folder inside your laravel app named `packages\<vendor>`. Make sure the folder is on the same level ass app.
3. Go into your `composer.json` and add the following: <br>
```
"repositories":{
        "superban": {
            "type": "path",
            "url": "packages/babyfaceeasy/superban",
            "options": {
                "symlink": true
            }
        } 
    },
```
4. Go into your `composer.json` inside the require object and add <br> ```"<vendor>/superban": "@dev"```

## Usage
