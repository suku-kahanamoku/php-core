<?php

declare(strict_types=1);

use App\Modules\Http\{HttpRequest, HttpResponse};
use App\Modules\Transport\Integrations\OpenTripPlanner\OtpProvider;
use App\Modules\Transport\Model\ProviderDefinition;

$otpNoGeocoder = new OtpProvider(new ProviderDefinition('tram', 'otp-sk', 'otp_transmodel', [
    'url' => 'http://127.0.0.1:8080/otp/transmodel/v3',
    'feed_code' => 'dpb',
], $coverage));
check(!in_array('places', $otpNoGeocoder->capabilities(), true), 'OTP without a geocoder does not advertise place search');
fails(fn() => $otpNoGeocoder->resourceRequest('places', ['query' => 'Hlavna']), 'unsupported_capability');

$otp = new OtpProvider(new ProviderDefinition('tram', 'otp-sk', 'otp_transmodel', [
    'url' => 'http://127.0.0.1:8080/otp/transmodel/v3',
    'feed_code' => 'dpb',
    'geocoder_url' => 'http://127.0.0.1:8080/otp/geocode',
], $coverage));
check(in_array('places', $otp->capabilities(), true), 'OTP with a configured geocoder advertises place search');

$placesRequest = $otp->resourceRequest('places', ['query' => 'Hlavna', 'location' => ['lat' => 48.15, 'lon' => 17.11]]);
check(
    str_contains($placesRequest->url, '/otp/geocode/stopClusters?')
        && str_contains($placesRequest->url, 'query=Hlavna')
        && str_contains($placesRequest->url, 'focusLatitude=48.15')
        && str_contains($placesRequest->url, 'focusLongitude=17.11'),
    'OTP place search uses the documented stopClusters geocoder endpoint'
);

$geocoderFixture = json_encode([
    ['primaryId' => 'dpb:1001', 'secondaryIds' => [], 'names' => [], 'codes' => [], 'coordinate' => ['lat' => 48.151, 'lon' => 17.111]],
    ['primaryId' => 'dpb:bad', 'secondaryIds' => [], 'names' => [], 'codes' => [], 'coordinate' => null],
]);
$placesRows = $otp->resourceResult('places', new HttpResponse(200, $geocoderFixture), []);
check(
    count($placesRows) === 2 && $placesRows[0]['name'] === null && $placesRows[0]['lat'] === 48.151 && $placesRows[1]['lat'] === null,
    'OTP geocoder mapping keeps real coordinates and defers the name to enrichment'
);

$stopHttp = new class implements \App\Modules\Http\Contracts\HttpClient {
    public array $requests = [];
    public function send(HttpRequest $request): HttpResponse
    {
        return $this->sendAll([$request])[0];
    }
    public function sendAll(array $requests, int $budgetMs = 6000, int $concurrency = 4): array
    {
        $responses = [];
        foreach ($requests as $key => $request) {
            $this->requests[] = $request->url;
            $responses[$key] = ($request->body['variables']['id'] ?? null) === 'dpb:1001'
                ? new HttpResponse(200, json_encode(['data' => ['quay' => [
                    'id' => 'dpb:1001',
                    'name' => 'Hlavná stanica',
                    'latitude' => 48.151,
                    'longitude' => 17.111,
                    'publicCode' => '1',
                    'timeZone' => 'Europe/Bratislava',
                ]]]))
                : new HttpResponse(200, '{"data":{"quay":null}}');
        }
        return $responses;
    }
};
$enrichedRows = $otp->enrichResource('places', $placesRows, [], $stopHttp);
check(
    $enrichedRows[0]['name'] === 'Hlavná stanica' && $enrichedRows[0]['timezone'] === 'Europe/Bratislava'
        && $enrichedRows[1]['name'] === null && count($stopHttp->requests) === 2,
    'OTP place enrichment resolves real stop names through the existing Transmodel stop query'
);

$otpModule = new \App\Modules\Transport\Integrations\OpenTripPlanner\OpenTripPlannerModule();
$otpDefinition = $otp->definition();
$otpModule->validate($otpDefinition);
$otpInvalidGeocoder = $otpDefinition->withConfig(array_replace($otpDefinition->config, ['geocoder_url' => 'http://localhost/wrong']));
fails(fn() => $otpModule->validate($otpInvalidGeocoder), 'invalid_configuration');
// The integration fixture already has an active graph; point that graph at a different immutable instance.
$activeOtpFeed = $r->activeFeed('pid');
if ($activeOtpFeed) {
    $previousGraphUrl = $activeOtpFeed['graph_url'];
    try {
        $r->execute('UPDATE transport_feed_version SET graph_url=? WHERE franchise_code=? AND id=?', ['http://127.0.0.1:8089/version-2/otp/transmodel/v3', $r->tenant, $activeOtpFeed['id']]);
        $runtimeOtp = $otpModule->create($otpDefinition->withConfig(array_replace($otpDefinition->config, ['feed_code' => 'pid'])), [], $r);
        check(str_starts_with($runtimeOtp->resourceRequest('places', ['query' => 'Hlavna'])->url, 'http://127.0.0.1:8089/version-2/otp/geocode/stopClusters'), 'OTP geocoder follows the active immutable graph endpoint and context path');
    } finally {
        $r->execute('UPDATE transport_feed_version SET graph_url=? WHERE franchise_code=? AND id=?', [$previousGraphUrl, $r->tenant, $activeOtpFeed['id']]);
    }
}
