"""Create a tiny deterministic OSM PBF fixture using only Python's standard library."""
import pathlib, struct, sys, zlib

def varint(value):
    result = bytearray()
    while value > 127:
        result.append((value & 127) | 128)
        value >>= 7
    result.append(value)
    return bytes(result)

def integer(tag, value):
    return varint(tag << 3) + varint(value)

def sint(value):
    return varint((value << 1) ^ (value >> 63))

def blob(tag, value):
    return varint((tag << 3) | 2) + varint(len(value)) + value

def block(kind, raw):
    compressed = zlib.compress(raw)
    data = integer(2, len(raw)) + blob(3, compressed)
    header = blob(1, kind.encode()) + integer(3, len(data))
    return struct.pack('>I', len(header)) + header + data

strings = [b'', b'highway', b'residential', b'name', b'TRAM test street']
string_table = b''.join(blob(1, value) for value in strings)
# Connected streets around the two GTFS stops. IDs and coordinates are synthetic.
coords = [(14.419, 50.075), (14.42, 50.075), (14.43, 50.079), (14.44, 50.082), (14.441, 50.083)]
nodes = b''
for ident, (lon, lat) in enumerate(coords, 1):
    node = varint(1 << 3) + sint(ident) + varint(8 << 3) + sint(round(lat * 1e7)) + varint(9 << 3) + sint(round(lon * 1e7))
    nodes += blob(1, node)
way = integer(1, 100) + blob(2, varint(1) + varint(3)) + blob(3, varint(2) + varint(4)) + blob(8, b''.join(sint(1) for _ in coords))
primitive = blob(1, string_table) + blob(2, nodes + blob(3, way)) + integer(17, 100)
header = blob(4, b'OsmSchema-V0.6') + blob(16, b'TRAM test fixture')
pathlib.Path(sys.argv[1]).write_bytes(block('OSMHeader', header) + block('OSMData', primitive))
