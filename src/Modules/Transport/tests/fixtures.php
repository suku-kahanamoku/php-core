<?php

declare(strict_types=1);
function gtfsFixture(string $path, array $override = []): void
{
    $files = [
        'agency.txt' => "agency_id,agency_name,agency_url,agency_timezone\nA,TRAM Test,https://example.test,Europe/Prague\n",
        'stops.txt' => "stop_id,stop_name,stop_lat,stop_lon\nS1,Start,50.075,14.42\nS2,End,50.082,14.44\n",
        'routes.txt' => "route_id,agency_id,route_short_name,route_long_name,route_type\nR1,A,22,Test tram,0\n",
        'calendar.txt' => "service_id,monday,tuesday,wednesday,thursday,friday,saturday,sunday,start_date,end_date\nDAILY,1,1,1,1,1,1,1,20260101,20271231\n",
        'calendar_dates.txt' => "service_id,date,exception_type\nDAILY,20261005,2\nEXTRA,20261005,1\n",
        'trips.txt' => "route_id,service_id,trip_id,trip_headsign\nR1,DAILY,T1,End\nR1,DAILY,Tnight,End\nR1,EXTRA,TX,End\n",
        'stop_times.txt' => "trip_id,arrival_time,departure_time,stop_id,stop_sequence\nT1,10:00:00,10:00:00,S1,1\nT1,10:10:00,10:10:00,S2,2\nTnight,24:05:00,24:05:00,S1,1\nTnight,24:15:00,24:15:00,S2,2\nTX,11:00:00,11:00:00,S1,1\nTX,11:10:00,11:10:00,S2,2\n",
        'feed_info.txt' => "feed_publisher_name,feed_publisher_url,feed_lang\nTRAM Test,https://example.test,cs\n",
    ];
    $z = new ZipArchive();
    $z->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach (array_replace($files, $override) as $name => $value) {
        if ($value !== null) {
            $z->addFromString($name, $value);
        }
    }
    $z->close();
}
