<?php
	namespace ScheduleControl\Core;
	use ScheduleControl\Utils;

	abstract class DBTable
	{
		private array $columns = array();
		private static array $selectquerycache = array();

		public function __construct(
			private DBTableInfo $info,
			array $columns,
		) {
			$info->SetTable($this);

			foreach ($columns as $key => $value)
			{
				$value->SetTable($this);

				$this->columns[$value->GetName()] = $value;
			}
		}

		public function GetInfo(): DBTableInfo { return $this->info; }
		public function GetColumns(): array { return $this->columns; }

		public function HasColumn(string $name): bool { return isset($this->columns[$name]); }

		public function IsForeignKey(string $column): bool
		{ return $this->HasColumn($column) && $this->columns[$column]->GetForeignInfo() !== null; }

		public function IsPrimaryKey(string $column): bool
		{ return $this->HasColumn($column) && $this->columns[$column]->IsPrimary(); }

		public function GetValidatedColumnValues(array $values): array
		{
			$validated = array();

			foreach ($values as $column => $value)
				if ($this->HasColumn($column))
					$validated["`$column`"] = $this->GetValidatedColumnValue($value, $column);

			return $validated;
		}

		public function GetValidatedColumnValue(DBTableValue|string|null $value, string $column): string
		{
			if ($value === null || !$this->HasColumn($column)) return "NULL";

			$sqlvalue = $this->columns[$column]->SqlValue($value instanceof DBTableValue ? $value->GetValue() : $value);

			return "'".str_replace("'", "", $sqlvalue)."'";
		}

		public function Insert(array $values): void
		{
			$validated = $this->GetValidatedColumnValues($values);
			$columns = array_keys($validated);
			$vals = array_values($validated);

			if (count($columns) > 0)
			{
				DataBase::Query("INSERT INTO `".$this->info->GetName()."`(".Utils::ConcatArray($columns, ", ").") VALUES(".Utils::ConcatArray($vals, ", ").")");
				self::$selectquerycache = array();
			}
		}

		public function Update(array $what, array $values): void
		{
			$validated = $this->GetValidatedColumnValues($what);
			$wherecolumns = array_keys($validated);
			$wherevalues = array_values($validated);

			if (count($wherecolumns) > 0)
			{
				$validated = $this->GetValidatedColumnValues($values);
				$columns = array_keys($validated);
				$vals = array_values($validated);

				if (count($columns) > 0)
				{
					DataBase::Query("UPDATE `".$this->info->GetName()."` SET ".Utils::ConcatArray(Utils::ConcatArrays(" = ", $columns, $vals), ", ")." WHERE ".self::QueryIsNullSafe(Utils::ConcatArray(Utils::ConcatArrays(" = ", $wherecolumns, $wherevalues), " AND ")));
					self::$selectquerycache = array();
				}
			}
		}

		public function Delete(array $what): void
		{
			$validated = $this->GetValidatedColumnValues($what);
			$wherecolumns = array_keys($validated);
			$wherevalues = array_values($validated);

			if (count($wherecolumns) > 0)
			{
				DataBase::Query("DELETE FROM `".$this->info->GetName()."` WHERE ".self::QueryIsNullSafe(Utils::ConcatArray(Utils::ConcatArrays(" = ", $wherecolumns, $wherevalues), " AND ")));
				self::$selectquerycache = array();
			}
		}

		public function Select(?array $columns = null): array
		{
			$where = null;

			if (isset($columns))
			{
				$validated = $this->GetValidatedColumnValues($columns);
				$wherecolumns = array_keys($validated);
				$wherevalues = array_values($validated);

				if (count($wherecolumns) > 0)
					$where = "WHERE ".Utils::ConcatArray(Utils::ConcatArrays(" = ", $wherecolumns, $wherevalues), " AND ");
			}

			return $this->SelectQuery($where);
		}

		public function SelectQuery(?string $options = null, ?string $select = null): array
		{
			$query = "SELECT ".($select ?? "*")." FROM `".$this->info->GetName()."`".(isset($options) ? " ".self::QueryIsNullSafe($options) : "");

			if (isset(self::$selectquerycache[$query]))
				return self::$selectquerycache[$query];

			$result = DataBase::Query($query);
			$res = array();
			
			foreach ($result as $row)
				$res[] = $this->MakeTableRow($row);

			self::$selectquerycache[$query] = $res;

			return $res;
		}

		public function GetForeignRows(string $column, string $value): ?array
		{
			if ($this->HasColumn($column) && $this->IsForeignKey($column))
			{
				$foreign = $this->columns[$column]->GetForeignInfo();
				$result = $foreign->GetTable()->Select(array($foreign->GetColumn()->GetName() => $value));

				return $result;
			}

			return null;
		}

		public static function QueryIsNullSafe(string $query): string
		{
			return str_replace(" = NULL", " IS NULL", $query);
		}

		abstract public function MakeTableRow(array $values): DBTableRow;

		public function UpdateTableStructure(bool $nofkeys = false): void
		{
			$struct = array(
				"columns" => array(),
				"primary" => array(),
				"foreigns" => array(),
				"checks" => array(),
			);

			foreach ($this->columns as $name => $column)
			{
				$struct["columns"][$name] = array(
					"type" => $column->GetDBType(),
					"null" => $this->IsPrimaryKey($name) ? 0 : 1,
					"default" => match($name)
					{
						"ID" => null,
						default => $column->GetDBDefault(),
					},
					"extra" => $name == "ID" ? "AUTO_INCREMENT" : null,
				);

				if ($this->IsPrimaryKey($name))
					if ($name == "ID")
						array_unshift($struct["primary"], $name);
					else
						$struct["primary"][] = $name;

				if (!$nofkeys && $column->GetForeignInfo() !== null)
					$struct["foreigns"][] = array(
						"table" => $column->GetForeignInfo()->GetTable()->GetInfo()->GetName(),
						"columns" => array($name => $column->GetForeignInfo()->GetColumn()->GetName()),
					);

				if ($name != "ID")
					foreach ($column->GetDBChecks() as $check)
						$struct["checks"][] = array($name, $check[0], $check[1]);
			}

			DBTablesAdapter::UpdateTableStructure($this->info->GetName(), $struct);
		}
	}
?>