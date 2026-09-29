#!/usr/bin/env python3
"""Compile portable MySQL 8 additive DDL from the definitions in *_schema.sql.

No stored routines or MySQL-client DELIMITER/SOURCE directives are required.
Foreign keys live as '-- deferred CONSTRAINT ...' lines beside their table;
apply them after all missing tables, columns and indexes have been added.
"""
from pathlib import Path
import argparse
import re
import sys

ROOT = Path(__file__).resolve().parents[1] / 'migrations'
MARKER = '-- BEGIN GENERATED ADDITIVE DDL (scripts/build-schemas.py)'
TABLE = re.compile(r'CREATE TABLE IF NOT EXISTS `([^`]+)` \(\n(.*?)\n\) ([^;]+);', re.S)


def quote(value):
    return "'" + value.replace('\\', '\\\\').replace("'", "''") + "'"


def guarded(query, ddl):
    return (f'SET @schema_ddl = IF(({query}), {quote(ddl)}, \'DO 0\');\n'
            'PREPARE schema_stmt FROM @schema_ddl;\n'
            'EXECUTE schema_stmt;\n'
            'DEALLOCATE PREPARE schema_stmt;\n')


def compile_schema(source):
    source = source.split(MARKER)[0].rstrip() + '\n'
    columns, indexes, constraints = [], [], []
    # Normalize inline constraints once; subsequent builds preserve the source.
    def table(match):
        name, body, options = match.groups()
        definitions, deferred = [], []
        for raw in body.splitlines():
            definition = raw.strip().rstrip(',')
            if definition.startswith('-- deferred '):
                definition = definition[len('-- deferred '):]
            if definition.startswith('CONSTRAINT '):
                deferred.append(definition)
                key = re.match(r'CONSTRAINT `([^`]+)`', definition).group(1)
                query = ("SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS "
                         f"WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME={quote(name)} AND CONSTRAINT_NAME={quote(key)}")
                constraints.append(guarded(query, f'ALTER TABLE `{name}` ADD {definition}'))
                continue
            definitions.append(definition)
            if definition.startswith('`'):
                col = re.match(r'`([^`]+)`', definition).group(1)
                query = ("SELECT COUNT(*)=0 FROM information_schema.COLUMNS "
                         f"WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME={quote(name)} AND COLUMN_NAME={quote(col)}")
                ddl = f'ALTER TABLE `{name}` ADD COLUMN {definition}'
                # AUTO_INCREMENT needs an index in the same ALTER on a partial table.
                if 'AUTO_INCREMENT' in definition:
                    ddl += ', ADD PRIMARY KEY (`' + col + '`)'
                columns.append(guarded(query, ddl))
            elif re.match(r'(PRIMARY KEY|UNIQUE KEY|KEY) ', definition):
                key = 'PRIMARY' if definition.startswith('PRIMARY KEY') else re.search(r'`([^`]+)`', definition).group(1)
                query = ("SELECT COUNT(*)=0 FROM information_schema.STATISTICS "
                         f"WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME={quote(name)} AND INDEX_NAME={quote(key)}")
                indexes.append(guarded(query, f'ALTER TABLE `{name}` ADD {definition}'))
            else:
                raise ValueError(f'Unsupported definition in {name}: {definition}')
        body = ',\n'.join('  '+d for d in definitions)
        body += ''.join('\n  -- deferred '+d for d in deferred)
        return f'CREATE TABLE IF NOT EXISTS `{name}` (\n{body}\n) {options};'
    source = TABLE.sub(table, source)
    if not columns:
        return source
    result = source + '\n' + MARKER + '\n-- Missing columns first, then indexes and foreign keys. Existing data stay intact.\n'
    result += '\n'.join(columns)
    # These historical widening changes are required by story/calendar imports.
    for name in ['etymolog_entry', 'etymolog_external_record']:
        if f'CREATE TABLE IF NOT EXISTS `{name}`' in source:
            result += '\n' + guarded(
                "SELECT COUNT(*)>0 FROM information_schema.COLUMNS "
                f"WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{name}' AND COLUMN_NAME='name_id' AND IS_NULLABLE='NO'",
                f'ALTER TABLE `{name}` MODIFY COLUMN `name_id` int unsigned NULL')
    if 'CREATE TABLE IF NOT EXISTS `etymolog_sync_job`' in source:
        result += '\n' + guarded(
            "SELECT COUNT(*)>0 FROM information_schema.COLUMNS "
            "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' "
            "AND COLUMN_NAME='cursor' AND DATA_TYPE='varchar' AND CHARACTER_MAXIMUM_LENGTH<2048",
            'ALTER TABLE `etymolog_sync_job` MODIFY COLUMN `cursor` varchar(2048) NULL')
    if 'CREATE TABLE IF NOT EXISTS `etymolog_import_record`' in source:
        # Keep every source entity when several Wikidata IDs reuse the same name.
        result += '\n' + guarded(
            "SELECT COALESCE(GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX),'')='franchise_code,name_id,provider' "
            "FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() "
            "AND TABLE_NAME='etymolog_import_record' AND INDEX_NAME='uq_etymolog_import'",
            'ALTER TABLE `etymolog_import_record` DROP INDEX `uq_etymolog_import`, '
            'ADD UNIQUE KEY `uq_etymolog_import` (`franchise_code`,`name_id`,`provider`,`external_id`)')
    result += '\n-- Missing indexes. Conflicting existing rows cause an error, never data removal.\n' + '\n'.join(indexes)
    result += '\n-- Missing constraints.\n' + '\n'.join(constraints)
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--check', action='store_true', help='fail if generated SQL is stale')
    args = parser.parse_args()
    stale = []
    for path in [ROOT/'schema.sql', *sorted(ROOT.glob('*_schema.sql'))]:
        original = path.read_text()
        generated = compile_schema(original)
        if original != generated:
            if args.check:
                stale.append(path.name)
            else:
                path.write_text(generated)
    if stale:
        sys.exit('Regenerate schemas: '+', '.join(stale))


if __name__ == '__main__':
    main()
