<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class OrderImport extends Base
{
    protected static $yearMonth;

    private $name = "order_import";

    public function setYearMonth(string $yearMonth)
    {
        static::$yearMonth= $yearMonth;
    }

    public function setNowTable(string $yearMonth)
    {
        static::$yearMonth = $yearMonth;
        $tableName = $this->name . '_'. static::$yearMonth;
        if (! Schema::hasTable($tableName)) {
            $this->createTable($tableName);
        }

        return $this;
    }

    public function getTable()
    {
        if(static::$yearMonth)
        {
            $tableName = $this->name . '_'. static::$yearMonth;
            if (! Schema::hasTable($tableName)) {
                $this->createTable($tableName);
            }

            return $tableName;
        }

        return Parent::getTable();
    }

    public function createTable(string $tableName)
    {
        Schema::create($tableName, function (Blueprint $table) {
            $table->bigIncrements('id')->unsigned();
            $table->integer("order_log_id")->comment("导入记录ID");
            $table->tinyInteger("type")->default(0)->comment("1不符合条件 2失败 3成功");
            $table->longText("row")->comment("导入数据");
            $table->text("messages")->nullable()->comment("错误信息");
            $table->timestamps();

            $table->index("order_log_id");
        });
    }

    public function setUnionAllTable($attributes = ['*'])
    {
        static::$yearMonth = null;
        
        // 获取所有分表数据库
        $dbName = DB::connection()->getDatabaseName();
        $prefix = DB::getTablePrefix();
        $sql = sprintf('SELECT table_name FROM information_schema.tables WHERE table_schema = "%s" AND table_name REGEXP "%s"', $dbName, '^'.$prefix.$this->name.'_[0-9_]');
        $logTables = DB::select($sql);

        $tables = [];
        foreach ($logTables as $table) {
            $tables[] = sprintf('SELECT %s FROM %s', implode(',', $attributes), $table->table_name);
        }

        // union all
        return $this->setTable(DB::raw(sprintf('( %s ) as %s', implode(' UNION ALL ', $tables), $this->name)));
    }

    public function allTruncate()
    {
        // 获取所有分表数据库
        $dbName = DB::connection()->getDatabaseName();
        $prefix = DB::getTablePrefix();
        $sql = sprintf('SELECT table_name FROM information_schema.tables WHERE table_schema = "%s" AND table_name REGEXP "%s"', $dbName, '^'.$prefix.$this->name.'_[0-9_]');
        $logTables = DB::select($sql);

        foreach ($logTables as $table) {
            $sql = sprintf('TRUNCATE TABLE %s', $table->TABLE_NAME);
            DB::statement($sql);
        }
    }
}
