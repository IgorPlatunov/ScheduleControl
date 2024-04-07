<?php
	namespace ScheduleControl\Core;
	use ScheduleControl\{Utils, Config};

	final class DBTablesAdapter
	{
		const CharSetSQL = "COLLATE utf8mb4_0900_ai_ci";

		static function CreateDBIfNotExists(): void
		{
			$name = Config::GetParameter("DatabaseInfo", "Name");
			DataBase::Query("CREATE DATABASE IF NOT EXISTS `$name` ".self::CharSetSQL);
			DataBase::SelectDB($name);
		}

		static function ClearAllForeignKeys(): void
		{
			foreach (self::GetAllTables() as $table)
			{
				$exprs = array();

				foreach (self::GetTableStructure($table)["foreigns"] as $foreign)
					$exprs[] = "DROP FOREIGN KEY `$foreign`";

				if (count($exprs) > 0)
					DataBase::Query("ALTER TABLE ".self::TableToSql($table)." ".Utils::ConcatArray($exprs, ", "));
			}
		}

		static function UpdateTableStructure(string $table, ?array $struct): void
		{
			$oldstruct = self::GetTableStructure($table);

			if (isset($oldstruct))
				if (isset($struct))
				{
					$expressions = array();

					foreach ($oldstruct["indexes"] as $index => $_)
						$expressions[] = "DROP INDEX `$index`";

					$prevcol = null;
					foreach ($struct["columns"] as $name => $column)
					{
						if (isset($oldstruct["columns"][$name]))
							$expressions[] = "CHANGE COLUMN `$name` ".self::ColumnToSql($name, $column, true, $prevcol);
						else
							$expressions[] = "ADD COLUMN ".self::ColumnToSql($name, $column, true, $prevcol);

						$prevcol = $name;
					}

					if (count($oldstruct["primary"]) > 0)
						$expressions[] = "DROP PRIMARY KEY";

					foreach ($oldstruct["foreigns"] as $name)
						$expressions[] = "DROP FOREIGN KEY `$name`";

					foreach ($oldstruct["checks"] as $name)
						$expressions[] = "DROP CHECK `$name`";

					foreach ($oldstruct["columns"] as $name => $column)
						if (!isset($struct["columns"][$name]))
							$expressions[] = "DROP COLUMN `$name`";

					foreach ($struct["primary"] as $column)
						$expressions[] = "ADD INDEX `$column`(`$column`)";

					if (count($struct["primary"]) > 0)
						$expressions[] = "ADD ".self::PrimaryKeyToSql($struct["primary"]);

					foreach ($struct["foreigns"] as $data)
						$expressions[] = "ADD ".self::ForeignKeyToSql($data["table"], $data["columns"]);

					if (count($struct["checks"]) > 0)
						$expressions[] = "ADD ".self::CheckToSql($struct["checks"]);

					if (count($expressions) > 0)
						DataBase::Query("ALTER TABLE ".self::TableToSql($table)." ".Utils::ConcatArray($expressions, ", "));
				}
				else
					DataBase::Query("DROP TABLE ".self::TableToSql($table));
			else if (isset($struct))
			{
				$expressions = array();

				foreach ($struct["columns"] as $name => $column)
					$expressions[] = self::ColumnToSql($name, $column);

				foreach ($struct["primary"] as $column)
					$expressions[] = "INDEX `$column`(`$column`)";

				if (count($struct["primary"]) > 0)
					$expressions[] = self::PrimaryKeyToSql($struct["primary"]);

				foreach ($struct["foreigns"] as $data)
					$expressions[] = self::ForeignKeyToSql($data["table"], $data["columns"]);

				if (count($struct["checks"]) > 0)
					$expressions[] = self::CheckToSql($struct["checks"]);

				DataBase::Query("CREATE TABLE ".self::TableToSql($table)."(".Utils::ConcatArray($expressions, ", ").") ENGINE=InnoDB DEFAULT ".self::CharSetSQL);
			}
		}

		static function ColumnToSql(string $name, array $column, bool $updating = false, ?string $prevcol = null): string
		{
			$sql = "`$name` ".$column["type"];

			if (!$column["null"])
				$sql .= " NOT NULL";
			
			if (isset($column["default"]))
				$sql .= " DEFAULT ".$column["default"];

			if (isset($column["extra"]))
				$sql .= " ".$column["extra"];

			$sql .= " ".self::CharSetSQL;

			if ($updating)
				if (isset($prevcol)) $sql .= " AFTER `$prevcol`";
				else $sql .= " FIRST";

			return $sql;
		}

		static function TableToSql(string $name): string
		{
			return "`".Config::GetParameter("DatabaseInfo", "Name")."`.`".$name."`";
		}

		static function PrimaryKeyToSql(array $columns): string
		{
			$primary = array();

			foreach ($columns as $column)
					$primary[] = "`".$column."`";

			return "PRIMARY KEY (".Utils::ConcatArray($primary, ", ").")";
		}

		static function ForeignKeyToSql(string $ftable, array $columns): string
		{
			$cols = array();
			$fcols = array();

			foreach ($columns as $col => $fcol)
			{
				$cols[] = "`".$col."`";
				$fcols[] = "`".$fcol."`";
			}

			return "FOREIGN KEY (".Utils::ConcatArray($cols, ", ").") REFERENCES ".self::TableToSql($ftable)."(".Utils::ConcatArray($fcols, ", ").") ON DELETE NO ACTION ON UPDATE RESTRICT";
		}

		static function CheckToSql(array $checks): string
		{
			$expressions = array();

			foreach ($checks as $data)
				$expressions[] = "`".$data[0]."` ".$data[1]." '".$data[2]."'";

			return "CHECK (".Utils::ConcatArray($expressions, " AND ").")";
		}

		static function SchemaSelect(string $type, array|string $columns, ?array $filter = null): array
		{
			$cols = array();

			foreach (is_string($columns) ? array($columns) : $columns as $column)
				$cols[] = "`".$column."`";

			$filtcols = array("`TABLE_SCHEMA`");
			$filtvals = array("'".Config::GetParameter("DatabaseInfo", "Name")."'");

			if (isset($filter))
				foreach ($filter as $col => $val)
				{
					$filtcols[] = "`".$col."`";
					$filtvals[] = "'".$val."'";
				}

			return DataBase::Query("SELECT ".Utils::ConcatArray($cols, ", ")." FROM `information_schema`.`$type` WHERE ".Utils::ConcatArray(Utils::ConcatArrays(" = ", $filtcols, $filtvals), " AND "));
		}

		static function GetAllTables(): array
		{
			$tables = array();

			foreach (self::SchemaSelect("Tables", "TABLE_NAME") as $table)
				$tables[] = $table["TABLE_NAME"];

			return $tables;
		}

		static function GetTableStructure(string $table): ?array
		{
			if (array_search($table, self::GetAllTables()) === false) return null;

			$struct = array(
				"columns" => array(),
				"primary" => array(),
				"indexes" => array(),
				"foreigns" => array(),
				"checks" => array(),
			);

			foreach (self::SchemaSelect("Columns", array("COLUMN_NAME", "COLUMN_DEFAULT", "IS_NULLABLE", "COLUMN_TYPE", "EXTRA"), array("TABLE_NAME" => $table)) as $column)
				$struct["columns"][$column["COLUMN_NAME"]] = array(
					"default" => $column["COLUMN_DEFAULT"],
					"null" => $column["IS_NULLABLE"] == "YES",
					"type" => $column["COLUMN_TYPE"],
					"extra" => $column["EXTRA"],
				);

			foreach (self::SchemaSelect("Table_Constraints", array("CONSTRAINT_NAME", "CONSTRAINT_TYPE"), array("TABLE_NAME" => $table)) as $constraint)
				if ($constraint["CONSTRAINT_TYPE"] == "PRIMARY KEY")
					$struct["primary"][] = $constraint["CONSTRAINT_NAME"];
				else if ($constraint["CONSTRAINT_TYPE"] == "FOREIGN KEY")
					$struct["foreigns"][] = $constraint["CONSTRAINT_NAME"];
				else if ($constraint["CONSTRAINT_TYPE"] == "CHECK")
					$struct["checks"][] = $constraint["CONSTRAINT_NAME"];

			foreach (Database::Query("SHOW INDEX FROM ".self::TableToSql($table)) as $index)
				if ($index["Key_name"] != "PRIMARY")
					$struct["indexes"][$index["Key_name"]] = true;
				
			return $struct;
		}
	}
?>