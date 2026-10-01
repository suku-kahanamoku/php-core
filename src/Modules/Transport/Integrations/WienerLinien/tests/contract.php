<?php

declare(strict_types=1);

use App\Modules\Http\HttpResponse;
use App\Modules\Transport\Integrations\WienerLinien\WienerLinienProvider;
use App\Modules\Transport\Model\ProviderDefinition;

$wiener = new WienerLinienProvider(new ProviderDefinition('tram', 'wiener-linien', 'wiener_linien', [
    'url' => 'https://www.wienerlinien.at/ogd_realtime',
], [$coverage]));
$wienerRequest = $wiener->resourceRequest('departures', ['external' => '147']);
check($wienerRequest->method === 'GET' && str_contains($wienerRequest->url, '/monitor?stopId=147')
    && str_contains($wienerRequest->url, 'activateTrafficInfo=stoerungkurz'), 'Wiener Linien adapter requests the documented monitor endpoint');
fails(fn() => $wiener->resourceRequest('departures', ['external' => 'not-rbl']), 'invalid_id');

$wienerFixture = ['data' => ['monitors' => [[
    'locationStop' => [
        'geometry' => ['coordinates' => [16.3492933, 48.2248218]],
        'properties' => ['title' => 'Volksoper U', 'municipality' => 'Wien', 'gate' => 'C', 'attributes' => ['rbl' => 147]],
    ],
    'lines' => [[
        'name' => '40',
        'towards' => 'Schottentor U',
        'type' => 'ptTram',
        'departures' => ['departure' => [[
            'departureTime' => ['timePlanned' => '2026-10-01T18:33:00.000+0200', 'timeReal' => '2026-10-01T18:33:54.000+0200'],
        ]]],
    ]],
]]]];
$wienerDepartures = $wiener->resourceResult('departures', new HttpResponse(200, json_encode($wienerFixture)), ['external' => '147']);
check(
    count($wienerDepartures) === 1 && $wienerDepartures[0]['realtime'] === true
        && $wienerDepartures[0]['line']['mode'] === 'tram' && $wienerDepartures[0]['expected_departure'] === '2026-10-01T18:33:54+02:00',
    'Wiener Linien adapter maps planned and realtime departures without persistence'
);
$wienerStop = $wiener->resourceResult('stop', new HttpResponse(200, json_encode($wienerFixture)), ['external' => '147']);
check($wienerStop['name'] === 'Volksoper U' && $wienerStop['city'] === 'Wien' && $wienerStop['timezone'] === 'Europe/Vienna', 'Wiener Linien adapter maps the monitor stop identity');
fails(fn() => $wiener->resourceResult('departures', new HttpResponse(200, '{"data":{"monitors":[]}}'), ['external' => '147']), 'not_found');
