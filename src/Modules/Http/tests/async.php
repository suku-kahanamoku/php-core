<?php

// Uses only the disposable loopback servers owned by integration.php.
$event = new \Workerman\Events\Select();
\Workerman\Worker::$globalEvent = $event;
\Workerman\Timer::init($event);
$async = \App\Modules\Http\HttpModule::asyncClient();
$asyncResults = [];
$heartbeat = 0;
$timer = \Workerman\Timer::add(0.02, function () use (&$heartbeat) {
    ++$heartbeat;
});
$guard = \Workerman\Timer::add(4, fn () => $event->stop(), [], false);
$jobs = [
    'slow' => new \App\Modules\Http\HttpRequest($base.'/slow'),
    'large' => new \App\Modules\Http\HttpRequest($second.'/large', maxBytes:100),
    'redirect' => new \App\Modules\Http\HttpRequest($second.'/redirect'),
    'echo' => new \App\Modules\Http\HttpRequest($second.'/echo', headers:['X-Test-Tenant' => 'isolated']),
];
foreach ($jobs as $name => $request) {
    $async->sendAsync($request, function ($response) use (&$asyncResults, $name, $event, $jobs) {
        $asyncResults[$name] = $response;
        if (count($asyncResults) === count($jobs)) {
            $event->stop();
        }
    });
}
$event->run();
\Workerman\Timer::del($timer);
\Workerman\Timer::del($guard);
check(count($asyncResults) === 4, 'async HTTP callbacks all complete');
check($asyncResults['slow']->successful() && $heartbeat >= 5, 'async HTTP does not block heartbeat during upstream wait');
check(!$asyncResults['large']->successful(), 'async response size bound aborts oversized body');
check(!$asyncResults['redirect']->successful(), 'async HTTP cannot forward credentials through a redirect');
check(($asyncResults['echo']->json()['headers']['X-Test-Tenant'] ?? '') === 'isolated', 'async headers belong to individual requests');
\Workerman\Timer::delAll();
