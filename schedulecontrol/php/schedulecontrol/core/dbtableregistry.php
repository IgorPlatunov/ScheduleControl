<?php
	namespace ScheduleControl\Core;
	use ScheduleControl\Utils, DateTime, RuntimeException;

	class DBTableRegistry extends Registry
	{
		private array $idcolumnsa = array();

		public function __construct(
			string|DBTableValue|array $ids,
			private DBTable $table,
			private DBTableColumnInfo|array $idcolumns,
			private DBTableColumnInfo $datecolumn,
		) {
			if (is_string($ids)) $ids = array($ids);
			if ($ids instanceof DBTableValue) $ids = array($ids->GetDBValue());

			foreach ($ids as $key => $id)
				if ($id instanceof DBTableValue) $ids[$key] = $id->GetDBValue();

			if ($idcolumns instanceof DBTableColumnInfo) $this->idcolumns = array($idcolumns);

			foreach ($idcolumns as $key => $column)
				$this->idcolumnsa[$column->GetName()] = $key;

			parent::__construct($ids);
		}

		public function GetIDColumnKey(string $column): ?int { return $this->idcolumnsa[$column] ?? null; }
		public function IsDataColumn(string $column): bool { return $column !== $this->datecolumn->GetName() && $this->GetIDColumnKey($column) === null; }

		public function GetLastChange(): ?RegistryObject
		{
			$date = DBTableRegistryCollections::GetActualDate();
			$datec = "`".$this->datecolumn->GetName()."`";
			$columns = array();
			$values = array();

			foreach ($this->idcolumns as $key => $column)
			{
				$columns[] = "`".$column->GetName()."`";
				$values[] = "'".$this->GetID()[$key]."'";
			}

			$where = Utils::ConcatArray(Utils::ConcatArrays(" = ", $columns, $values), " AND ");
			$result = $this->table->SelectQuery("WHERE ".(strlen($where) > 0 ? $where." AND " : "")."$datec <= '".Utils::SqlDate($date)."' ORDER BY $datec DESC LIMIT 1");

			if (count($result) == 0)
				return null;

			foreach ($result[0]->values as $column => $value)
			{
				if (!$this->IsDataColumn($column)) continue;
				
				if ($value->GetDBValue() !== null)
					return $this->BuildRegistryObject(new DateTime($result[0]->GetValue($this->datecolumn->GetName())), $result[0]);
			}

			return null;
		}

		public function AddChange(RegistryObject|DBTableRow|array|null $value): ?int
		{
			$values = $value == null ? array() : (is_array($value) ? $value : $this->ExtractRegistryObject($value instanceof DBTableRow ? $this->BuildRegistryObject($date, $value) : $value));
			$isnull = null;

			foreach ($this->table->GetColumns() as $name => $column)
			{
				if (!$this->IsDataColumn($name)) continue;

				$valisnull = !isset($values[$name]) || $values[$name] === null;

				if ($isnull === null) $isnull = $valisnull;
				else if ($isnull !== $valisnull)
					throw new RuntimeException("Попытка добавить изменения в регистр с недопустимыми значениями", 500);
			}

			if (count($values) == 0)
					foreach ($this->table->GetColumns() as $name => $column)
						if ($this->IsDataColumn($name))
							$values[$name] = null;

			$date = DBTableRegistryCollections::GetActualDate();
			$datec = $this->datecolumn->GetName();
			$datev = Utils::SqlDate($date);
			$where = array($datec => $datev);

			foreach ($this->idcolumns as $key => $column)
				$where[$column->GetName()] = $this->GetID()[$key];

			$exists = $this->table->Select($where);

			if (count($exists) > 0)
			{
				unset($values[$datec]);

				foreach ($this->idcolumns as $key => $column)
					unset($values[$column->GetName()]);

				$this->table->Update($where, $values);
			}
			else
			{
				$values[$datec] = $datev;

				foreach ($this->idcolumns as $key => $column)
					$values[$column->GetName()] = $this->GetID()[$key];

				$this->table->Insert($values);

				if (array_key_exists("ID", $values) && !isset($values["ID"]) && DataBase::RowCount() > 0)
					return DataBase::LastInsertID();
			}

			return null;
		}

		public function ClearOldChanges(): void
		{
			$date = $this->GetLastChange()?->GetDate() ?? DBTableRegistryCollections::GetActualDate();
			$tovalidate = array($this->datecolumn->GetName() => Utils::SqlDate($date));
			$operators = array("<");

			foreach ($this->idcolumns as $key => $column)
			{
				$tovalidate[$column->GetName()] = $this->GetID()[$key];
				$operators[] = "=";
			}

			$validated = $this->table->GetValidatedColumnValues($tovalidate);
			$columns = array_keys($validated);
			$values = array_values($validated);

			DataBase::Query("DELETE FROM `".$this->table->GetInfo()->GetName()."` WHERE ".$this->table->QueryIsNullSafe(Utils::ConcatArray(Utils::ConcatArrays(" ", $columns, $operators, $values), " AND ")));
		}

		private function BuildRegistryObject(DateTime $date, DBTableRow $row): ?RegistryObject
		{
			if ($row->table != $this->table) return null;

			return new RegistryObject($this, $this->GetID(), $date, $row);
		}

		private function ExtractRegistryObject(RegistryObject $object): ?array
		{
			if ($object->GetRegistry() != $this || $object->GetID() != $this->GetID()) return null;

			$values = array();

			foreach ($object->GetData()->values as $column => $value)
				if (isset($this->table->GetColumns()[$column]) && $this->IsDataColumn($column))
					$values[$column] = $value->GetDBValue();

			return $values;
		}
	}
?>