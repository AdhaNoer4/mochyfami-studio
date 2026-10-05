<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Stop the Authenticate middleware from resolving a login redirect.
         *
         * Laravel registers redirectGuestsTo(fn () => route('login')) by default,
         * and Authenticate::unauthenticated() evaluates that callback while it
         * builds the AuthenticationException, as an argument, before anything is
         * thrown. This application has no login route, so on any request that
         * was not asking for JSON the callback itself threw
         * RouteNotFoundException and the caller received a 500 carrying a stack
         * trace, for a request that was merely missing a token.
         *
         * Returning null removes the redirect target. The middleware then throws
         * the AuthenticationException it was always meant to throw, and
         * shouldRenderJsonWhen below answers api/* as the 401 JSON body the
         * contract requires. That second part already existed: it is the piece
         * that was never given a chance to run.
         *
         * It also keeps the framework from inventing a browser login flow this
         * application does not have. No web route sits behind auth:sanctum, so
         * nothing here can redirect a browser anywhere.
         */
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
