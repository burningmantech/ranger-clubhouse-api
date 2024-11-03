<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $name = DB::connection()->getDatabaseName();

        $rows = DB::select("SELECT table_name, column_name FROM information_schema.`COLUMNS`  WHERE table_schema = ? and column_name='timestamp'", [$name]);
        $cached = [];

        foreach ($rows as $row) {
            $desc = $cached[$row->table_name] ?? null;
            if (!$desc) {
                $desc = DB::select("desc {$row->table_name}");
                $cached[$row->table_name] = $desc;
            }
            foreach ($desc as $column) {
                if ($column->Field != 'timestamp') {
                    continue;
                }
                $alert = "ALTER TABLE {$row->table_name} MODIFY {$row->column_name} {$column->Type}";
                if ($column->Null == 'NO') {
                    $alert .= " NOT NULL";
                } else {
                    $alert .= " NULL";
                }

                $alert .= " DEFAULT CURRENT_TIMESTAMP";
                error_log($alert);
                DB::statement($alert);
            }
        }

        $rows = DB::select("SELECT table_name, column_name FROM information_schema.`COLUMNS`  WHERE table_schema = ? and character_set_name='utf8mb3'", [$name]);


        foreach ($rows as $row) {
            $desc = $cached[$row->table_name] ?? null;
            if (!$desc) {
                $desc = DB::select("desc {$row->table_name}");
                $cached[$row->table_name] = $desc;
            }

            foreach ($desc as $column) {
                if ($column->Field == $row->column_name) {
                    $alert = "ALTER TABLE {$row->table_name} MODIFY {$row->column_name} {$column->Type}";
                    if ($column->Field != 'timestamp') {
                        $alert .= " CHARACTER SET utf8mb4";
                    }
                    if ($column->Null == 'NO') {
                        $alert .= " NOT NULL";
                    } else {
                        $alert .= " NULL";
                    }

                    $default = $column->Default;
                    if ($default != null) {
                        if (is_numeric($default)) {
                            $alert .= " DEFAULT {$default}";
                        } else {
                            $alert .= " DEFAULT '{$default}'";
                        }
                    }
                    error_log($alert);
                    DB::statement($alert);
                }
            }
        }        //
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
