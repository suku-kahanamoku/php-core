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

fails(fn() => $wiener->resourceRequest('departures', ['external' => '147', 'at' => '2030-01-01T00:00:00Z']), 'unsupported_time');
fails(fn() => $wiener->resourceRequest('departures', ['external' => '147', 'at' => gmdate(DATE_RFC3339, time() - 120)]), 'unsupported_time');
check(str_contains($wiener->resourceRequest('departures', ['external' => '147', 'at' => gmdate(DATE_RFC3339)])->url, 'monitor?'), 'Wiener monitor accepts the current departure window');
$wienerFixture['data']['monitors'][0]['lines'][0]['barrierFree'] = true;
$wienerFixture['data']['monitors'][0]['refTrafficInfoNames'] = ['notice-1'];
$wienerFixture['data']['trafficInfos'] = [
    ['name' => 'notice-1', 'title' => 'Diversion', 'description' => 'Use another stop', 'descriptionHTML' => '<script>bad()</script>', 'status' => 'active'],
    ['name' => 'unrelated', 'title' => 'Elsewhere'],
];
$wienerFixture['data']['monitors'][0]['lines'][0]['departures']['departure'] = [
    ['departureTime' => ['timePlanned' => '2026-10-01T18:30:00.000+0200', 'timeReal' => '2026-10-01T18:40:00.000+0200']],
    ['departureTime' => ['timePlanned' => '2026-10-01T18:35:00.000+0200'], 'vehicle' => ['name' => '41', 'towards' => 'Other terminus', 'type' => 'ptBus', 'barrierFree' => false, 'foldingRamp' => false, 'cooling' => true, 'onStop' => false]],
    ['departureTime' => ['timePlanned' => '2026-10-01T18:20:00.000+0200']],
];
$wienerDepartureRows = $wiener->resourceResult('departures', new HttpResponse(200, json_encode($wienerFixture)), ['external' => '147', 'at' => '2026-10-01T18:25:00+02:00', 'limit' => 1]);
check(count($wienerDepartureRows) === 1 && $wienerDepartureRows[0]['scheduled_departure'] === '2026-10-01T18:35:00+02:00', 'Wiener departures filter by requested instant and limit after realtime sorting');
check($wienerDepartureRows[0]['line']['code'] === '41' && $wienerDepartureRows[0]['line']['mode'] === 'bus' && $wienerDepartureRows[0]['headsign'] === 'Other terminus', 'Wiener individual vehicle overrides line identity, mode and destination');
check($wienerDepartureRows[0]['metadata']['wheelchair_accessible'] === false && $wienerDepartureRows[0]['metadata']['folding_ramp'] === false && $wienerDepartureRows[0]['metadata']['features'] === ['AIR_CONDITIONING'] && $wienerDepartureRows[0]['at_stop'] === false, 'Wiener equipment preserves explicit false and unknown values');
check(count($wienerDepartureRows[0]['alerts']) === 1 && $wienerDepartureRows[0]['alerts'][0]['id'] === 'notice-1' && !str_contains(json_encode($wienerDepartureRows), '<script>'), 'Wiener returns only linked notices with plain text');
$wienerAllRows = $wiener->resourceResult('departures', new HttpResponse(200, json_encode($wienerFixture)), ['external' => '147', 'at' => '2026-10-01T18:25:00+02:00', 'limit' => 20]);
check(count($wienerAllRows) === 2 && $wienerAllRows[1]['metadata']['wheelchair_accessible'] === true && $wienerAllRows[1]['metadata']['folding_ramp'] === null, 'Wiener line equipment applies only when vehicle has no override');
$wienerFixture['data']['monitors'][0]['locationStop']['geometry']['coordinates'] = [16, 100];
fails(fn() => $wiener->resourceResult('stop', new HttpResponse(200, json_encode($wienerFixture)), ['external' => '147']), 'invalid_upstream');
