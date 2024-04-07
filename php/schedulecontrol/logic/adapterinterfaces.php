<?php
	namespace ScheduleControl\Logic;
	use ScheduleControl\Core\DataBase;
	use DateTime;

	interface JSONAdaptive
	{
		public function ToJSON(bool $names = false): ?array;
		public static function FromJSON(array $data): JSONAdaptive;
	}

	interface DatabaseAdaptive
	{
		public function SaveToDB(): void;
		public function DeleteFromDB(): void;
		public static function GetFromDatabase(mixed $param): ?DatabaseAdaptive;
	}

	trait DatabaseTransaction
	{
		public function SaveToDatabase(): void
		{ DataBase::Transaction(function() { $this->SaveToDB(); }); }

		public function DeleteFromDatabase(): void
		{ DataBase::Transaction(function() { $this->DeleteFromDB(); }); }
	}
?>