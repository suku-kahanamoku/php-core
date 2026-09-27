<?php

declare(strict_types=1);
require dirname(__DIR__, 4).'/vendor/autoload.php';
use App\Modules\Transport\DTO\{ProviderDefinition,JourneyQuery};
use App\Modules\Transport\Providers\TransmodelProvider;
use App\Modules\Http\HttpService;

$otp = getenv('TRANSPORT_TEST_OTP_URL');
$http = new HttpService();
$provider = new TransmodelProvider(new ProviderDefinition('tram', $otp ? 'pid-otp' : 'entur', $otp ? 'otp_transmodel' : 'entur', [
    'url' => $otp ?: 'https://api.entur.io/journey-planner/v3/graphql','client_name' => 'collega-tram','geocoder_url' => 'https://api.entur.io/geocoder/v1/autocomplete',
], []));
$input = ['from-dest' => ['type' => 'coordinates','lat' => $otp ? 50.075 : 59.911,'lon' => $otp ? 14.42 : 10.752],
    'to-dest' => ['type' => 'coordinates','lat' => $otp ? 50.082 : 59.958,'lon' => $otp ? 14.44 : 10.774],
    'from-date' => $otp ? '2026-10-06T09:55:00+02:00' : (new DateTimeImmutable('tomorrow 10:00', new DateTimeZone('Europe/Oslo')))->format(DATE_RFC3339)];
$query = JourneyQuery::fromArray($input);
$response = $http->sendAll(['search' => $provider->searchRequest($query)])['search'];
try {
    $journeys = $provider->searchResult($response);
} catch (Throwable $e) {
    fwrite(STDERR, substr($response->body, 0, 1500)."\n");
    throw $e;
}
if (!$journeys) {
    throw new RuntimeException('No live journeys returned.');
}
$transit = [];
foreach ($journeys as $j) {
    foreach ($j['legs'] as $l) {
        if ($l['trip_id']) {
            $transit[] = $l;
        }
    }
}
if (!$transit) {
    throw new RuntimeException('No transit legs returned.');
}
echo 'PASS '.($otp ? 'OTP' : 'Entur').' live journey search: '.count($journeys).' journeys, '.count($transit)." transit legs\n";
$leg = $transit[0];
$stop = App\Modules\Transport\ResourceIdCodec::decode($leg['from']['id'], 'tram', 'stop');
foreach (['stop','departures'] as $operation) {
    $args = $stop + ['at' => $input['from-date'],'limit' => 10];
    $result = $http->sendAll(['resource' => $provider->resourceRequest($operation, $args)])['resource'];
    try {
        $data = $provider->resourceResult($operation, $result, $args);
    } catch (Throwable $e) {
        fwrite(STDERR, substr($result->body, 0, 1500)."\n");
        throw $e;
    }
    if (!$data) {
        throw new RuntimeException('No '.$operation.' returned.');
    }echo "PASS live $operation\n";
}
$trip = App\Modules\Transport\ResourceIdCodec::decode($leg['trip_id'], 'tram', 'trip');
$result = $http->sendAll(['resource' => $provider->resourceRequest('trip', $trip)])['resource'];
$data = $provider->resourceResult('trip', $result, $trip);
if (count($data['stops']) < 2) {
    throw new RuntimeException('Incomplete trip detail.');
}
echo "PASS live dated trip detail\n";
if (!$otp) {
    $args = ['query' => 'Oslo S','limit' => 5];
    $result = $http->sendAll(['places' => $provider->resourceRequest('places', $args)])['places'];
    if (!$provider->resourceResult('places', $result, $args)) {
        throw new RuntimeException('Geocoder returned no stops.');
    }
    echo "PASS live Entur autocomplete\n";
}
