<?php
	namespace ScheduleControl\Core;
	use DateTime;

	final class DBTableRegistryCollections
	{
		private static array $collections = array();
		private static array $order = array();
		private static DateTime $actdate;

		public static function RegisterCollection(string $name, DBTable $table, DBTableColumnInfo|array $idcolumns, DBTableColumnInfo $datecolumn, bool $internal = false, string|array|null $privileges = null): void
		{ self::$collections[$name] = self::$order[] = new DBTableRegistryCollection($table, $idcolumns, $datecolumn, $internal, $privileges); }

		public static function GetCollections(bool $order = false): array
		{ return $order ? self::$order : self::$collections; }

		public static function GetCollection(string|DBTable $nameortable): ?DBTableRegistryCollection
		{
			if ($nameortable instanceof DBTable)
			{
				foreach (self::$collections as $name => $collection)
					if ($collection->GetTable()->GetInfo()->GetName() == $nameortable->GetInfo()->GetName())
						return $collection;

				return null;
			}

			return self::$collections[$nameortable] ?? null;
		}

		public static function GetRightForeignObject(DBTableForeignValue $value): ?RegistryObject
		{
			$collection = self::GetCollection($value->row->table);
			if (!isset($collection)) return null;

			$rows = $value->GetForeignRows();
			$ftable = isset($rows[0]) ? $rows[0]->table : null;
			if (!isset($ftable)) return null;

			$fcollection = self::GetCollection($ftable);
			if (!isset($fcollection)) return null;

			$object = null;

			foreach ($rows as $row)
			{
				$ids = array();

				foreach ($fcollection->GetIDColumns() as $key => $column)
					$ids[$key] = $row->GetDBValue($column->GetName());
				
				$object = $fcollection->GetLastChange($ids);
				if (isset($object)) break;
			}

			return $object;
		}

		public static function UpdateTableStructures(): void
		{
			DBTablesAdapter::CreateDBIfNotExists();

			DataBase::SetForeignKeyCheck(false);

			$tables = array();

			foreach (self::$order as $collection)
			{
				$tables[$collection->GetTable()->GetInfo()->GetName()] = true;
				$collection->UpdateTableStructure();
			}

			foreach (DBTablesAdapter::GetAllTables() as $table)
				if (!isset($tables[$table]))
					DBTablesAdapter::UpdateTableStructure($table, null);

			DataBase::SetForeignKeyCheck(true);
		}

		public static function SetActualDate(DateTime $actdate): void { self::$actdate = $actdate; }
		public static function GetActualDate(): DateTime { return self::$actdate; }
	}
	DBTableRegistryCollections::SetActualDate(new DateTime());
?>