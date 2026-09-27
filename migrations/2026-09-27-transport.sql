-- TRAM Transport: additive, repeatable MySQL 8 migration. No existing tables are dropped.
-- Provision providers and mode enumerations separately with transport-configure.php.
CREATE TABLE IF NOT EXISTS transport_provider (
 franchise_code VARCHAR(64) NOT NULL,
 code VARCHAR(64) NOT NULL,
 adapter VARCHAR(64) NOT NULL,
 role VARCHAR(16) NOT NULL DEFAULT 'primary',
 config JSON NOT NULL,
 coverage JSON NOT NULL,
 fallback_for JSON NOT NULL,
 published TINYINT NOT NULL DEFAULT 0,
 failure_count INT UNSIGNED NOT NULL DEFAULT 0,
 open_until DATETIME NULL,
 probe_until DATETIME NULL,
 next_request_at DATETIME(3) NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (franchise_code,code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS transport_feed (
 franchise_code VARCHAR(64) NOT NULL,
 code VARCHAR(64) NOT NULL,
 provider_code VARCHAR(64) NOT NULL,
 url TEXT NOT NULL,
 timezone VARCHAR(64) NOT NULL,
 config JSON NOT NULL,
 active_version_id BIGINT UNSIGNED NULL,
 PRIMARY KEY (franchise_code,code),
 CONSTRAINT fk_transport_feed_provider FOREIGN KEY (franchise_code,provider_code) REFERENCES transport_provider(franchise_code,code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS transport_feed_version (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 franchise_code VARCHAR(64) NOT NULL,
 feed_code VARCHAR(64) NOT NULL,
 checksum CHAR(64) NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'importing',
 valid_from DATE NULL,
 valid_until DATE NULL,
 archive_path TEXT NOT NULL,
 row_counts JSON NULL,
 graph_url TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 UNIQUE KEY uq_transport_version_scope (franchise_code,id),
 UNIQUE KEY uq_transport_version_hash (franchise_code,feed_code,checksum),
 CONSTRAINT fk_transport_version_feed FOREIGN KEY (franchise_code,feed_code) REFERENCES transport_feed(franchise_code,code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS transport_sync_run (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 franchise_code VARCHAR(64) NOT NULL,
 feed_code VARCHAR(64) NOT NULL,
 version_id BIGINT UNSIGNED NULL,
 status VARCHAR(20) NOT NULL,
 error_code VARCHAR(64) NULL,
 started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 finished_at DATETIME NULL,
 PRIMARY KEY (id),
 KEY idx_transport_sync (franchise_code,feed_code,started_at),
 CONSTRAINT fk_transport_sync_feed FOREIGN KEY (franchise_code,feed_code) REFERENCES transport_feed(franchise_code,code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS transport_operator (
 franchise_code VARCHAR(64) NOT NULL,
 version_id BIGINT UNSIGNED NOT NULL,
 external_id VARCHAR(255) NOT NULL,
 name VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 timezone VARCHAR(64) NOT NULL,
 data JSON NOT NULL,
 PRIMARY KEY (franchise_code,version_id,external_id),
 CONSTRAINT fk_transport_operator_version FOREIGN KEY (franchise_code,version_id) REFERENCES transport_feed_version(franchise_code,id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS transport_stop (
 franchise_code VARCHAR(64) NOT NULL,
 version_id BIGINT UNSIGNED NOT NULL,
 external_id VARCHAR(255) NOT NULL,
 name VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 lat DECIMAL(10,7) NULL,
 lon DECIMAL(10,7) NULL,
 parent_id VARCHAR(255) NULL,
 location_type SMALLINT NOT NULL DEFAULT 0,
 platform VARCHAR(64) NULL,
 data JSON NOT NULL,
 PRIMARY KEY (franchise_code,version_id,external_id),
 KEY idx_transport_stop_name (franchise_code,version_id,name),
 KEY idx_transport_stop_geo (franchise_code,version_id,lat,lon),
 CONSTRAINT fk_transport_stop_version FOREIGN KEY (franchise_code,version_id) REFERENCES transport_feed_version(franchise_code,id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS transport_route (
 franchise_code VARCHAR(64) NOT NULL,
 version_id BIGINT UNSIGNED NOT NULL,
 external_id VARCHAR(255) NOT NULL,
 operator_id VARCHAR(255) NOT NULL,
 name VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 mode VARCHAR(32) NOT NULL,
 data JSON NOT NULL,
 PRIMARY KEY (franchise_code,version_id,external_id),
 CONSTRAINT fk_transport_route_operator FOREIGN KEY (franchise_code,version_id,operator_id) REFERENCES transport_operator(franchise_code,version_id,external_id),
 CONSTRAINT fk_transport_route_version FOREIGN KEY (franchise_code,version_id) REFERENCES transport_feed_version(franchise_code,id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS transport_service (
 franchise_code VARCHAR(64) NOT NULL,
 version_id BIGINT UNSIGNED NOT NULL,
 external_id VARCHAR(255) NOT NULL,
 weekdays CHAR(7) NOT NULL DEFAULT '0000000',
 start_date DATE NULL,
 end_date DATE NULL,
 data JSON NOT NULL,
 PRIMARY KEY (franchise_code,version_id,external_id),
 CONSTRAINT fk_transport_service_version FOREIGN KEY (franchise_code,version_id) REFERENCES transport_feed_version(franchise_code,id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS transport_service_exception (
 franchise_code VARCHAR(64) NOT NULL,
 version_id BIGINT UNSIGNED NOT NULL,
 service_id VARCHAR(255) NOT NULL,
 service_date DATE NOT NULL,
 exception_type TINYINT NOT NULL,
 PRIMARY KEY (franchise_code,version_id,service_id,service_date),
 CONSTRAINT fk_transport_exception_service FOREIGN KEY (franchise_code,version_id,service_id) REFERENCES transport_service(franchise_code,version_id,external_id),
 CONSTRAINT fk_transport_service_exception_version FOREIGN KEY (franchise_code,version_id) REFERENCES transport_feed_version(franchise_code,id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS transport_shape (
 franchise_code VARCHAR(64) NOT NULL,
 version_id BIGINT UNSIGNED NOT NULL,
 external_id VARCHAR(255) NOT NULL,
 sequence INT UNSIGNED NOT NULL,
 lat DECIMAL(10,7) NOT NULL,
 lon DECIMAL(10,7) NOT NULL,
 distance DOUBLE NULL,
 PRIMARY KEY (franchise_code,version_id,external_id,sequence),
 CONSTRAINT fk_transport_shape_version FOREIGN KEY (franchise_code,version_id) REFERENCES transport_feed_version(franchise_code,id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS transport_trip (
 franchise_code VARCHAR(64) NOT NULL,
 version_id BIGINT UNSIGNED NOT NULL,
 external_id VARCHAR(255) NOT NULL,
 route_id VARCHAR(255) NOT NULL,
 service_id VARCHAR(255) NOT NULL,
 shape_id VARCHAR(255) NULL,
 headsign VARCHAR(255) NULL,
 data JSON NOT NULL,
 PRIMARY KEY (franchise_code,version_id,external_id),
 KEY idx_transport_trip_service (franchise_code,version_id,service_id),
 CONSTRAINT fk_transport_trip_route FOREIGN KEY (franchise_code,version_id,route_id) REFERENCES transport_route(franchise_code,version_id,external_id),
 CONSTRAINT fk_transport_trip_service FOREIGN KEY (franchise_code,version_id,service_id) REFERENCES transport_service(franchise_code,version_id,external_id),
 CONSTRAINT fk_transport_trip_version FOREIGN KEY (franchise_code,version_id) REFERENCES transport_feed_version(franchise_code,id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS transport_stop_time (
 franchise_code VARCHAR(64) NOT NULL,
 version_id BIGINT UNSIGNED NOT NULL,
 trip_id VARCHAR(255) NOT NULL,
 sequence INT UNSIGNED NOT NULL,
 stop_id VARCHAR(255) NOT NULL,
 arrival_seconds INT UNSIGNED NULL,
 departure_seconds INT UNSIGNED NULL,
 pickup_type TINYINT NOT NULL DEFAULT 0,
 drop_off_type TINYINT NOT NULL DEFAULT 0,
 data JSON NOT NULL,
 PRIMARY KEY (franchise_code,version_id,trip_id,sequence),
 KEY idx_transport_departures (franchise_code,version_id,stop_id,departure_seconds),
 CONSTRAINT fk_transport_time_trip FOREIGN KEY (franchise_code,version_id,trip_id) REFERENCES transport_trip(franchise_code,version_id,external_id),
 CONSTRAINT fk_transport_time_stop FOREIGN KEY (franchise_code,version_id,stop_id) REFERENCES transport_stop(franchise_code,version_id,external_id),
 CONSTRAINT fk_transport_stop_time_version FOREIGN KEY (franchise_code,version_id) REFERENCES transport_feed_version(franchise_code,id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS transport_frequency (
 franchise_code VARCHAR(64) NOT NULL,
 version_id BIGINT UNSIGNED NOT NULL,
 trip_id VARCHAR(255) NOT NULL,
 start_seconds INT UNSIGNED NOT NULL,
 end_seconds INT UNSIGNED NOT NULL,
 headway_seconds INT UNSIGNED NOT NULL,
 exact_times TINYINT NOT NULL DEFAULT 0,
 PRIMARY KEY (franchise_code,version_id,trip_id,start_seconds),
 CONSTRAINT fk_transport_frequency_trip FOREIGN KEY (franchise_code,version_id,trip_id) REFERENCES transport_trip(franchise_code,version_id,external_id),
 CONSTRAINT fk_transport_frequency_version FOREIGN KEY (franchise_code,version_id) REFERENCES transport_feed_version(franchise_code,id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS transport_transfer (
 franchise_code VARCHAR(64) NOT NULL,
 version_id BIGINT UNSIGNED NOT NULL,
 sequence INT UNSIGNED NOT NULL,
 from_stop_id VARCHAR(255) NULL,
 to_stop_id VARCHAR(255) NULL,
 transfer_type TINYINT NOT NULL,
 min_transfer_seconds INT UNSIGNED NULL,
 data JSON NOT NULL,
 PRIMARY KEY (franchise_code,version_id,sequence),
 CONSTRAINT fk_transport_transfer_version FOREIGN KEY (franchise_code,version_id) REFERENCES transport_feed_version(franchise_code,id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS transport_journey_cache (
 franchise_code VARCHAR(64) NOT NULL,
 id CHAR(32) NOT NULL,
 payload JSON NOT NULL,
 expires_at DATETIME NOT NULL,
 PRIMARY KEY (franchise_code,id),
 KEY idx_transport_cache_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

