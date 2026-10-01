<?php
declare(strict_types=1);
use App\Modules\Transport\Integrations\IdsJmk\{IdsJmkModule,IdsJmkScheduleService,IdsJmkObservationMapper};
use App\Modules\Transport\Protocols\GtfsRealtime\{ProtobufReader,GtfsRealtimeReader};

$idsConfig = json_decode(file_get_contents(dirname(__DIR__, 6).'/config/transport.idsjmk.example.json'), true)['providers'][2]['config'];
$idsDefinition = new \App\Modules\Transport\Model\ProviderDefinition('tram', 'jmk-live', 'idsjmk', $idsConfig, [$cz]);
$idsModule = new IdsJmkModule(); $idsModule->validate($idsDefinition);
$idsProvider = $idsModule->create($idsDefinition, []);
$idsExternal = 'L:CISJR:738068,CN:CISJR:1050,SD:2026-10-01,FS:crz:1,FSI:0,FT:10:20:00,TS:crz:2,TSI:1,TT:10:44:00';
$idsReference = ['provider' => 'spojenka', 'external' => $idsExternal, 'date' => '2026-10-01'];
check($idsProvider->realtimeReference($idsReference)['provider'] === 'jmk-live', 'IDS JMK links explicit dated CIS identity to configured provider code');
check($idsProvider->realtimeReference(array_replace($idsReference, ['provider' => 'foreign'])) === null, 'IDS JMK cannot claim a foreign source namespace');
check($idsProvider->realtimeReference(array_replace($idsReference, ['date' => '2026-10-02'])) === null, 'IDS JMK rejects conflicting embedded service dates');
check($idsProvider->realtimeReference(array_replace($idsReference, ['external' => str_replace('738068','123068',$idsExternal)])) === null, 'same public line number outside configured CIS range is not mapped');
$idsRegistry = new \App\Modules\Transport\Core\ProviderRegistry([$sp, $idsProvider]);
check($idsRegistry->canonicalReference('realtime', $idsReference)['provider'] === 'jmk-live', 'generic registry routes the verified bridge despite static source lacking realtime');

$idsDir = getenv('TRANSPORT_TEST_DIR').'/idsjmk-fixture'; mkdir($idsDir, 0700);
$idsZip = $idsDir.'/schedule.zip'; $zip = new ZipArchive(); $zip->open($idsZip, ZipArchive::CREATE);
$zip->addFromString('api.txt', "Linka/CVlaku = trip_id: 68/1050 = 100\r\nLinka/CVlaku = trip_id: 68/1050 = 101\r\n");
$zip->addFromString('calendar.txt', "service_id,monday,tuesday,wednesday,thursday,friday,saturday,sunday,start_date,end_date\nweekday,1,1,1,1,1,0,0,20260101,20261231\nweekend,0,0,0,0,0,1,1,20260101,20261231\n");
$zip->addFromString('calendar_dates.txt', "service_id,date,exception_type\nweekday,20261002,2\n");
$zip->addFromString('trips.txt', "route_id,service_id,trip_id\nL68D99,weekday,100\nL68D99,weekend,101\n");
$zip->addFromString('stop_times.txt', "trip_id,stop_id,stop_sequence,arrival_time,departure_time\n100,U1,1,10:20:00,10:20:00\n100,U2,2,10:44:00,10:44:00\n101,U1,1,10:20:00,10:20:00\n101,U2,2,10:44:00,10:44:00\n"); $zip->close();
$idsSchedule = new IdsJmkScheduleService($idsDir.'/cache', $idsConfig['schedule_url']);
$idsRef = ['line'=>68,'number'=>1050,'date'=>'2026-10-01','from_index'=>0,'from_time'=>'10:20:00','to_index'=>1,'to_time'=>'10:44:00'];
$idsTrip = $idsSchedule->match($idsZip, $idsRef);
check($idsTrip['trip_id'] === '100', 'calendar resolves duplicate line/run mappings to actual service day');
check($idsSchedule->match($idsZip, array_replace($idsRef, ['date'=>'2026-10-02'])) === null, 'service cancellation exception excludes GPS association');
check($idsSchedule->match($idsZip, array_replace($idsRef, ['from_time'=>'11:20:00'])) === null, 'different stop occurrence time cannot bind to the same line/run');
$idsHttp = new class(file_get_contents($idsZip)) implements \App\Modules\Http\Contracts\HttpClient {
    public array $requests=[]; public int $status=200;
    public function __construct(private string $bytes) {}
    public function send(\App\Modules\Http\HttpRequest $r): \App\Modules\Http\HttpResponse { $this->requests[]=$r; return new \App\Modules\Http\HttpResponse($this->status, $this->status===200?$this->bytes:'', headers:['ETag'=>'"v1"']); }
    public function sendAll(array $r,int $budgetMs=6000,int $concurrency=4): array { return array_map($this->send(...),$r); }
};
check($idsSchedule->resolve($idsRef, $idsHttp)['trip_id']==='100','online static identity resolves via shared HTTP contract');
$idsHttp->status=304;
check($idsSchedule->resolve($idsRef, $idsHttp)['trip_id']==='100' && end($idsHttp->requests)->headers['If-None-Match']==='"v1"', 'static identity cache is used only after online conditional validation');
$idsHttp->status=503;
fails(fn()=>$idsSchedule->resolve($idsRef,$idsHttp),'source_unavailable');

$idsVar = static function(int $n): string { $s=''; do { $b=$n&127; $n>>=7; $s.=chr($b|($n?128:0)); } while($n); return $s; };
$idsBytes = static fn(int $n,string $v): string=>$idsVar(($n<<3)|2).$idsVar(strlen($v)).$v;
$idsInt = static fn(int $n,int $v): string=>$idsVar($n<<3).$idsVar($v);
$idsFloat = static fn(int $n,float $v): string=>$idsVar(($n<<3)|5).pack('g',$v);
$idsNow = strtotime('2026-10-01T10:25:00+02:00');
$idsVehicle = $idsBytes(1,$idsBytes(1,'100')).$idsBytes(2,$idsFloat(1,49.2).$idsFloat(2,16.6)).$idsInt(5,$idsNow).$idsBytes(8,$idsBytes(1,'75430').$idsBytes(2,'7543'));
$idsFeed = $idsBytes(1,$idsBytes(1,'2.0').$idsInt(3,$idsNow)).$idsBytes(2,$idsBytes(1,'vehicle').$idsBytes(4,$idsVehicle));
$idsVehicles = GtfsRealtimeReader::vehicles($idsFeed);
check(count($idsVehicles)===1 && $idsVehicles[0]['trip_id']==='100' && abs($idsVehicles[0]['lat']-49.2)<0.00001, 'wire reader parses official vehicle fields including float coordinates and timestamp');
$idsTraffic = ['lineId'=>68,'cars'=>[['lineId'=>68,'routeId'=>1050,'carNum'=>7543,'latitude'=>49.2,'longitude'=>16.6,'delayInMins'=>8]]];
$idsLive = IdsJmkObservationMapper::map($idsVehicles,$idsTraffic,$idsTrip,$idsNow);
check($idsLive['realtime'] && $idsLive['delay_seconds']===480, 'dated trip, vehicle and consistent GPS fix bind actual eight minute delay');
check(!IdsJmkObservationMapper::map($idsVehicles,$idsTraffic,$idsTrip,$idsNow+30)['realtime'], 'stale source timestamp removes IDS JMK GPS and delay');
check(!IdsJmkObservationMapper::map([...$idsVehicles,...$idsVehicles],$idsTraffic,$idsTrip,$idsNow)['realtime'], 'ambiguous vehicles are never guessed');
$idsTraffic['cars'][0]['latitude']=49.201;
check(IdsJmkObservationMapper::map($idsVehicles,$idsTraffic,$idsTrip,$idsNow)['delay_seconds']===480,'independently refreshed current feeds allow normal vehicle movement');
$idsTraffic['cars'][0]['latitude']=49.2;
$idsTraffic['cars'][0]['routeId']=1052;
check(IdsJmkObservationMapper::map($idsVehicles,$idsTraffic,$idsTrip,$idsNow)['delay_seconds']===null,'another trip of the same vehicle does not supply delay');
$idsTraffic['cars'][0]['routeId']=1050; $idsTraffic['cars'][0]['latitude']=49.3;
check(IdsJmkObservationMapper::map($idsVehicles,$idsTraffic,$idsTrip,$idsNow)['delay_seconds']===null,'different or uncorrelated GPS fix does not supply timestamp-less delay');
$idsVehicles[0]['start_date']='20260930';
check(!IdsJmkObservationMapper::map($idsVehicles,[],$idsTrip,$idsNow)['realtime'],'wrong GTFS service date cannot provide live GPS');
fails(fn()=>GtfsRealtimeReader::vehicles(substr($idsFeed,0,-1)),'invalid_upstream');
fails(fn()=>ProtobufReader::fields("\x0a\xff\xff\xff\xff\xff\xff\xff\xff\xff\x7f"),'invalid_upstream');
