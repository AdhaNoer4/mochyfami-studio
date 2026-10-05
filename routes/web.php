<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| SPA Fallback
|--------------------------------------------------------------------------
|
| The studio is a single page application, so React owns what a path means.
| Laravel only has to hand back the shell for any web path React renders itself,
| which is what this fallback does. Declaring every React route here instead
| would only duplicate the router that already exists in the frontend, and
| would let the two disagree.
|
| Without it, a deep link or a refresh on /dashboard or /projects/1 was a 404,
| because the only web route was /. React now receives the same shell for these
| paths as it does for / and resolves the path on the client.
|
| This is written as Route::any rather than Route::fallback because
| Route::fallback() registers a GET route only. Symfony answers an unmatched path
| whose method no route accepts with 405 and an Allow header naming the methods
| that do, so a GET only fallback would make a mistyped API endpoint called with
| POST answer "405 GET" instead of "404". That would claim a read is possible on
| a path this server does not have. Any() puts the route in every method's
| collection, so the 404 below is what a caller actually receives.
|
| The api/* guard is the other half of the same care: an unmatched API path must
| stay a 404 from the API and never receive an HTML shell, otherwise a mistyped
| endpoint would answer 200 with a page of markup instead of saying it does not
| exist.
|
*/
Route::any('/{fallbackPlaceholder}', function (Request $request) {
    if ($request->is('api/*')) {
        abort(404);
    }

    // Nothing under the web layer handles a write, and answering one with the
    // shell would let a mistyped form submission look like it had worked.
    //
    // HEAD counts as a read. Symfony matched this route for a HEAD request by
    // falling back to the GET verbs, so refusing it here would contradict the
    // routing layer that had just accepted it, and would fail a link checker or
    // a monitoring probe on /dashboard with 405.
    abort_unless($request->isMethod('GET') || $request->isMethod('HEAD'), 405);

    return view('welcome');
})
    ->where('fallbackPlaceholder', '.*')
    /*
     * This route lives in the web middleware group, which means it inherits CSRF
     * verification, and that would answer before the code above gets to run. An
     * unmatched POST to a mistyped API endpoint would then come back 419 Page
     * Expired instead of 404, telling the caller their session is the problem
     * when the real problem is that the endpoint does not exist.
     *
     * Nothing is lost by excluding it. This route never reads the request body
     * and never performs the write it carries: it answers 404 for the API, 405
     * for a web write, and the static shell for a web read.
     */
    ->withoutMiddleware(PreventRequestForgery::class)
    ->fallback();
