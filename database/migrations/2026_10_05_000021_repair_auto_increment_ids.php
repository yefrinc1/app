<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->repairAutoIncrement('users');
        $this->repairAutoIncrement(config('permission.table_names.roles', 'roles'));
        $this->repairAutoIncrement(config('permission.table_names.permissions', 'permissions'));
    }

    private function repairAutoIncrement(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
            return;
        }

        $column = DB::selectOne(
            'SELECT COLUMN_TYPE, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [DB::getDatabaseName(), $table, 'id']
        );

        if (! $column || str_contains(strtolower((string) $column->EXTRA), 'auto_increment')) {
            return;
        }

        $columnType = strtolower((string) $column->COLUMN_TYPE);
        $allowedTypes = ['tinyint', 'tinyint unsigned', 'smallint', 'smallint unsigned', 'mediumint', 'mediumint unsigned', 'int', 'int unsigned', 'bigint', 'bigint unsigned'];

        if (! in_array($columnType, $allowedTypes, true)) {
            throw new \RuntimeException("No se puede reparar {$table}.id porque su tipo es {$columnType}.");
        }

        $safeTable = str_replace('`', '``', $table);
        $foreignKeys = $this->incomingForeignKeys($table);
        $droppedForeignKeys = [];

        try {
            foreach ($foreignKeys as $foreignKey) {
                DB::statement(sprintf(
                    'ALTER TABLE `%s` DROP FOREIGN KEY `%s`',
                    $this->escapeIdentifier($foreignKey['table']),
                    $this->escapeIdentifier($foreignKey['name'])
                ));
                $droppedForeignKeys[] = $foreignKey;
            }

            DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY `id` %s NOT NULL AUTO_INCREMENT',
                $safeTable,
                strtoupper($columnType)
            ));
        } finally {
            foreach ($droppedForeignKeys as $foreignKey) {
                $this->restoreForeignKey($foreignKey);
            }
        }
    }

    private function incomingForeignKeys(string $referencedTable): array
    {
        $rows = DB::select(
            <<<'SQL'
                SELECT
                    kcu.CONSTRAINT_NAME,
                    kcu.TABLE_NAME,
                    kcu.COLUMN_NAME,
                    kcu.REFERENCED_COLUMN_NAME,
                    kcu.ORDINAL_POSITION,
                    rc.UPDATE_RULE,
                    rc.DELETE_RULE
                FROM information_schema.KEY_COLUMN_USAGE AS kcu
                INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS AS rc
                    ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                    AND rc.TABLE_NAME = kcu.TABLE_NAME
                    AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                WHERE kcu.CONSTRAINT_SCHEMA = ?
                    AND kcu.REFERENCED_TABLE_SCHEMA = ?
                    AND kcu.REFERENCED_TABLE_NAME = ?
                    AND kcu.REFERENCED_COLUMN_NAME IS NOT NULL
                ORDER BY kcu.TABLE_NAME, kcu.CONSTRAINT_NAME, kcu.ORDINAL_POSITION
                SQL,
            [DB::getDatabaseName(), DB::getDatabaseName(), $referencedTable]
        );

        $foreignKeys = [];

        foreach ($rows as $row) {
            $key = $row->TABLE_NAME.'|'.$row->CONSTRAINT_NAME;

            if (! isset($foreignKeys[$key])) {
                $foreignKeys[$key] = [
                    'table' => $row->TABLE_NAME,
                    'name' => $row->CONSTRAINT_NAME,
                    'columns' => [],
                    'referenced_table' => $referencedTable,
                    'referenced_columns' => [],
                    'update_rule' => $this->validRule($row->UPDATE_RULE),
                    'delete_rule' => $this->validRule($row->DELETE_RULE),
                ];
            }

            $foreignKeys[$key]['columns'][] = $row->COLUMN_NAME;
            $foreignKeys[$key]['referenced_columns'][] = $row->REFERENCED_COLUMN_NAME;
        }

        return array_values($foreignKeys);
    }

    private function restoreForeignKey(array $foreignKey): void
    {
        $columns = implode(', ', array_map(
            fn (string $column) => '`'.$this->escapeIdentifier($column).'`',
            $foreignKey['columns']
        ));
        $referencedColumns = implode(', ', array_map(
            fn (string $column) => '`'.$this->escapeIdentifier($column).'`',
            $foreignKey['referenced_columns']
        ));

        DB::statement(sprintf(
            'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (%s) REFERENCES `%s` (%s) ON UPDATE %s ON DELETE %s',
            $this->escapeIdentifier($foreignKey['table']),
            $this->escapeIdentifier($foreignKey['name']),
            $columns,
            $this->escapeIdentifier($foreignKey['referenced_table']),
            $referencedColumns,
            $foreignKey['update_rule'],
            $foreignKey['delete_rule']
        ));
    }

    private function validRule(string $rule): string
    {
        $rule = strtoupper($rule);
        $allowedRules = ['RESTRICT', 'CASCADE', 'SET NULL', 'NO ACTION', 'SET DEFAULT'];

        if (! in_array($rule, $allowedRules, true)) {
            throw new \RuntimeException("Regla de llave foranea no admitida: {$rule}.");
        }

        return $rule;
    }

    private function escapeIdentifier(string $identifier): string
    {
        return str_replace('`', '``', $identifier);
    }

    public function down(): void
    {
        // No se elimina AUTO_INCREMENT: esta migracion repara la integridad del esquema.
    }
};
