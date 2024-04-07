<?php
	use ScheduleControl\{Core as Core, Core\DBTableRegistryCollections as Collections};
	use ScheduleControl\Data\UsersControl;

	require_once("open.php");

	final class DataBaseUpdater
	{
		public static function Update()
		{
			Core\DataBase::Transaction(function() {
				Core\DBTablesAdapter::CreateDBIfNotExists();
				Core\DataBase::SetForeignKeyCheck(false);

				Core\DBTablesAdapter::ClearAllForeignKeys();
				
				$tables = array();

				$updateTable = function($table, $i) use(&$tables) {
					$tables[$table->GetInfo()->GetName()] = true;
					$table->UpdateTableStructure($i == 0);
				};
			
				for ($i = 0; $i <= 1; $i++)
				{
					foreach (Collections::GetCollections(true) as $collection)
						$updateTable($collection->GetTable(), $i);
				
					$updateTable(UsersControl::GetUsersTable(), $i);
					$updateTable(UsersControl::GetRolesTable(), $i);
				}

				foreach (Core\DBTablesAdapter::GetAllTables() as $table)
					if (!isset($tables[$table]))
						Core\DBTablesAdapter::UpdateTableStructure($table, null);
			
				Core\DataBase::SetForeignKeyCheck(true);

				UsersControl::CreateDefaultUserIfNeed();
			});
		}
	}
?>