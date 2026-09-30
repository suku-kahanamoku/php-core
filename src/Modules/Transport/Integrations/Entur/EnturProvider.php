<?php
declare(strict_types=1);
namespace App\Modules\Transport\Integrations\Entur;

use App\Modules\Http\{HttpRequest,HttpResponse};
use App\Modules\Transport\Model\{ProviderDefinition,TransportException,UpstreamResponseMapper};
use App\Modules\Transport\Protocols\Transmodel\TransmodelProvider;

final class EnturProvider extends TransmodelProvider
{
    public function __construct(ProviderDefinition $definition)
    {
        parent::__construct($definition, isset($definition->config['client_name']) ? ['ET-Client-Name: '.$definition->config['client_name']] : []);
    }

    public function capabilities(): array
    {
        return array_merge(parent::capabilities(), isset($this->definition->config['geocoder_url'])
            ? (str_ends_with($this->definition->config['geocoder_url'], '/autocomplete') ? ['places','nearby_stops'] : ['places']) : []);
    }

    public function resourceRequest(string $operation, array $input): HttpRequest
    {
        if ($operation === 'nearby_stops' && in_array('nearby_stops', $this->capabilities(), true)) {
            $url = substr($this->definition->config['geocoder_url'], 0, -strlen('/autocomplete')).'/reverse';
            return new HttpRequest($url.'?'.http_build_query(['point.lat'=>$input['location']['lat'],
                'point.lon'=>$input['location']['lon'],'boundary.circle.radius'=>$input['radius_m']/1000,
                'size'=>$input['limit'],'layers'=>'venue']), headers:['ET-Client-Name: '.$this->definition->config['client_name']]);
        }
        if ($operation === 'places' && isset($this->definition->config['geocoder_url'])) {
            return new HttpRequest($this->definition->config['geocoder_url'].'?'.http_build_query(['text' => trim(($input['city'] ?? '').' '.$input['query']),'size' => $input['limit'],'focus.point.lat'=>$input['location']['lat'] ?? null,'focus.point.lon'=>$input['location']['lon'] ?? null,'layers' => 'venue','boundary.country' => $this->definition->config['geocoder_country'] ?? null]), headers:['ET-Client-Name: '.$this->definition->config['client_name']]);
        }
        return parent::resourceRequest($operation, $input);
    }

    public function resourceResult(string $operation, HttpResponse $result, array $input): array
    {
        if (!in_array($operation, ['places','nearby_stops'], true)) { return parent::resourceResult($operation, $result, $input); }
        $json = UpstreamResponseMapper::json($result);
        if (in_array($operation, ['places','nearby_stops'], true)) {
            if (!isset($json['features']) || !is_array($json['features'])) {
                throw new TransportException('invalid_upstream', 'Invalid place response.', 502);
            }
            $items = [];
            foreach ($json['features'] as $f) {
                $id = $f['properties']['id'] ?? null;
                $xy = $f['geometry']['coordinates'] ?? [];
                if (!$id || count($xy) !== 2 || !str_contains($id, ':StopPlace:')) {
                    continue;
                }
                if (!empty($input['city']) && \App\Modules\Transport\Core\PlaceSearchService::normalize((string)($f['properties']['locality'] ?? '')) !== \App\Modules\Transport\Core\PlaceSearchService::normalize($input['city'])) { continue; }
                $items[] = ['id' => $this->id('stop', $id),'name' => $f['properties']['name'] ?? '', 'lat' => $xy[1],'lon' => $xy[0],'timezone' => $this->definition->config['timezone'] ?? 'Europe/Oslo'];
            }
            return $items;
        }
        throw new TransportException('unsupported_capability', 'Unsupported Entur operation.', 422);
    }
}
