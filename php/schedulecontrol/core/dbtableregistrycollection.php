<?php
	namespace ScheduleControl\Core;
	use DateTime, ScheduleControl\Utils;

	class DBTableRegistryCollection extends RegistryCollection
	{
		private array $idcolumnsa = array();

		public function __construct(
			private DBTable $table,
			private DBTableColumnInfo|array $idcolumns,
			private DBTableColumnInfo $datecolumn,
			private bool $internal = false,
			private string|array|null $privileges = null,
		) {
			if ($idcolumns instanceof DBTableColumnInfo) $this->idcolumns = array($idcolumns);

			foreach ($this->idcolumns as $key => $column)
				$this->idcolumnsa[$column->GetName()] = $key;
		}

		private function prepareIds(string|DBTableValue|array|null $ids): array
		{
			if ($ids == null || is_string($ids)) $ids = array($ids);
			if ($ids instanceof DBTableValue) $ids = array($ids->GetDBValue());

			foreach ($ids as $key => $id)
				if ($id instanceof DBTableValue) $ids[$key] = array($id->GetDBValue());

			return $ids;
		}

		public function GetTable(): DBTable { return $this->table; }
		public function GetIDColumns(): array { return $this->idcolumns; }
		public function GetDateColumn(): DBTableColumnInfo { return $this->datecolumn; }
		public function GetIDColumnKey(string $column): ?int { return $this->idcolumnsa[$column] ?? null; }
		public function IsDataColumn(string $column): bool { return $column !== $this->datecolumn->GetName() && $this->GetIDColumnKey($column) === null; }

		public function GetRegistries(): array
		{
			$columns = array();
			foreach ($this->idcolumns as $key => $column)
				$columns[] = "`".$column->GetName()."`";

			$columns = Utils::ConcatArray($columns, ", ");

			$options = "WHERE `".$this->datecolumn->GetName()."` <= '".Utils::SqlDate(DBTableRegistryCollections::GetActualDate())."'";
			if (strlen($columns) > 0)
				$options .= " GROUP BY $columns ORDER BY $columns ASC";

			$result = $this->table->SelectQuery($options, strlen($columns) > 0 ? "DISTINCT $columns" : null);
			$registries = array();

			foreach ($result as $row)
			{
				$values = array();

				foreach ($this->idcolumns as $key => $column)
					$values[$key] = $row->GetDBValue($column->GetName());

				$registries[] = $this->CreateRegistry($values);
			}

			return $registries;
		}

		public function GetAllLastChanges(?array $filter = null, ?int $limit = null, ?int $offset = null): array
		{
			$columns = array();
			$wherecol = array();

			foreach ($this->idcolumns as $key => $column)
			{
				$columns[] = "`".$column->GetName()."`";
				$wherecol["b.`".$column->GetName()."`"] = "a.`".$column->GetName()."`";
			}

			$columns = Utils::ConcatArray($columns, ", ");
			$wherecol = count($wherecol) > 0 ? " AND ".Utils::ConcatArrayKV($wherecol, " = ", " AND ") : "";

			$datecol = "`".$this->datecolumn->GetName()."`";
			$tabname = "`".$this->table->GetInfo()->GetName()."`";
			$sqldate = "'".Utils::SqlDate(DBTableRegistryCollections::GetActualDate())."'";

			$inner = "SELECT $datecol FROM $tabname AS b WHERE b.$datecol <= $sqldate$wherecol ORDER BY $datecol DESC LIMIT 1";

			$nullcoltest = "";
			foreach ($this->table->GetColumns() as $name => $column)
				if ($this->IsDataColumn($name))
				{ $nullcoltest = "`$name` IS NOT NULL AND"; break; }

			if (isset($filter))
			{
				$f = array();

				foreach ($filter as $column => $value)
					$f["`$column`"] = "'$value'";

				$filter = " AND ".Utils::ConcatArrayKV($f, " = ", " AND ");
			}

			$options = "AS a WHERE $nullcoltest $datecol = ($inner)".($filter ?? "");
			if (strlen($columns) > 0)
				$options .= " ORDER BY $columns ASC";

			if (isset($limit))
				$options .= " LIMIT $limit".(isset($offset) ? " OFFSET $offset" : "");

			$result = $this->table->SelectQuery($options);
			$objects = array();

			foreach ($result as $row)
			{
				$values = array();

				foreach ($this->idcolumns as $key => $column)
					$values[$key] = $row->GetDBValue($column->GetName());

				$registry = $this->CreateRegistry($values);
				$objects[] = new RegistryObject($registry, $values, new DateTime($row->GetValue($this->datecolumn->GetName())), $row);
			}

			return $objects;
		}

		public function GetRegistry(string|DBTableValue|array|null $ids, bool $create = false): ?DBTableRegistry
		{
			$date = DBTableRegistryCollections::GetActualDate();
			$ids = $this->prepareIds($ids);

			if (!$create)
			{
				$columns = array();
				$values = array();

				foreach ($this->idcolumns as $key => $column)
				{
					$columns[] = "`".$column->GetName()."`";
					$values[] = "'".$ids[$key]."'";
				}

				$result = $this->table->SelectQuery("WHERE ".Utils::ConcatArray(Utils::ConcatArrays(" = ", $columns, $values), " AND ")." AND `".$this->datecolumn->GetName()."` <= '".Utils::SqlDate($date)."' LIMIT 1", "1");
				if (count($result) == 0) return null;
			}

			$vals = array();

			foreach ($this->idcolumns as $key => $column)
				$vals[$key] = $ids[$key];

			return $this->CreateRegistry($vals);
		}

		public function GetLastChange(string|DBTableValue|array|null $ids): ?RegistryObject
		{
			$registry = $this->GetRegistry($ids);

			return isset($registry) ? $registry->GetLastChange() : null;
		}

		public function AddChange(string|DBTableValue|array|null $ids, RegistryObject|DBTableRow|array|null $value): ?int
		{ return $this->GetRegistry($ids, true)->AddChange($value); }

		public function ClearAllOldChanges(): void
		{
			DataBase::SetForeignKeyCheck(false);

			foreach ($this->GetRegistries() as $registry)
				$registry->ClearOldChanges();
			
			DataBase::SetForeignKeyCheck(true);
		}

		public function IsRegistryExists(string|DBTableValue|array $ids): bool
		{ return $this->GetRegistry($ids) != null; }

		public function IsInternal(): bool { return $this->internal; }
		
		public function HasAccess(User $user): bool {
			if (!isset($this->privileges)) return true;

			if (is_array($this->privileges))
				return UserPrivileges::HasAny($user->GetPrivileges(), ...$this->privileges);
			
			return $user->HasPrivilege($this->privileges);
		}

		protected function CreateRegistry(string|DBTableValue|array|null $ids): DBTableRegistry
		{ return new DBTableRegistry($ids, $this->table, $this->idcolumns, $this->datecolumn); }
	}
?>