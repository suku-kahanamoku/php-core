<?php
declare(strict_types=1);
use App\Modules\Transport\Model\ProviderDefinition;
use App\Modules\Transport\Protocols\Transmodel\TransmodelProvider;
use App\Modules\Http\HttpResponse;

// Raw protocol has no Entur geocoder or fixed Norwegian timezone.
$enturDefinition = new ProviderDefinition('modular','entur','entur',['url'=>'https://fixture.invalid','geocoder_url'=>'https://fixture.invalid/autocomplete','client_name'=>'fixture','timezone'=>'Europe/Copenhagen'],$coverage);
$enturProvider = new \App\Modules\Transport\Integrations\Entur\EnturProvider($enturDefinition);
$enturPlace = $enturProvider->resourceResult('places',new HttpResponse(200,json_encode(['features'=>[['properties'=>['id'=>'NSR:StopPlace:1','name'=>'Test'],'geometry'=>['coordinates'=>[12.5,55.6]]]]])),[]);
check($enturPlace[0]['timezone'] === 'Europe/Copenhagen' && !in_array('places',(new TransmodelProvider($enturDefinition))->capabilities(),true),'Entur metadata and geocoding stay outside shared Transmodel');
